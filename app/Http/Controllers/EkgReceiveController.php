<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\EkgResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EkgReceiveController extends Controller
{
    private ?string $lastOrigin = null;
    private ?string $lastPatientCode = null;
    public function receive(Request $request)
    {
        try {
            $rawBody = $request->getContent();

            // Body kosong = ini "Test Network" dari device, bukan upload sungguhan
            if (strlen($rawBody) === 0) {
                Log::info('Network test request - no file to process');
                return $this->xmlResponse(1, 'Network test success');
            }

            $filename = $this->getFilenameFromRequest($request);
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            Log::info("[RECEIVE] Filename: {$filename}, size: " . strlen($rawBody) . ' bytes');

            $savedPath = $this->saveIncomingFile($rawBody, $filename);

            $payloadType = $this->classifyPayload($extension);
            Log::info("[CLASSIFY] Payload type: {$payloadType}");

            $this->routeToHis($payloadType, $rawBody, $filename, $savedPath);

            return $this->xmlResponse(1, 'Upload successful');

        } catch (\Throwable $e) {
            Log::error('Receive error: ' . $e->getMessage());
            return $this->xmlResponse(-1, $e->getMessage());
        }
    }



    private function getFilenameFromRequest(Request $request): string
    {
        // ASUMSI: device kirim nama file lewat header "Filename".
        // Kalau ternyata device pakai nama header lain, atau nama file ada
        // di tempat lain, sesuaikan baris ini setelah test dengan device asli.
        $filename = $request->header('Filename');
        if ($filename) {
            return $filename;
        }

        // fallback: tebak dari Content-Type kalau header Filename tidak ada
        $contentType = $request->header('Content-Type', '');
        $map = [
            'application/pdf' => 'ecg_result.pdf',
            'application/xml' => 'ecg_result.xml',
            'text/xml' => 'ecg_result.xml',
        ];
        return $map[$contentType] ?? 'ecg_result.dat';
    }

    private function classifyPayload(string $extension): string
    {
        return match ($extension) {
            'pdf' => 'RAW_PDF',
            'xml' => 'FDA_XML',
            'dat' => 'DAT',
            default => 'UNKNOWN',
        };
    }

    private function saveIncomingFile(string $rawBody, string $filename): string
    {
        $path = 'ekg-incoming/' . now()->format('Ymd_His') . '_' . Str::random(6) . '_' . $filename;
        Storage::disk('local')->put($path, $rawBody);
        return $path;
    }

    private function xmlResponse(int $code, string $message): \Illuminate\Http\Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<root><result>'
            . '<Code>' . $code . '</Code>'
            . '<Message>' . htmlspecialchars($message, ENT_XML1) . '</Message>'
            . '</result></root>';

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }



    private function extractGuidFromFilename(string $filename): ?string
    {
        // Contoh nama file device: 0000001C-0078-00EB-B443-1E4058929176.pdf
        // Ambil bagian sebelum ekstensi sebagai GUID.
        $name = pathinfo($filename, PATHINFO_FILENAME);
        return $name !== '' ? $name : null;
    }


    private function holdPendingDat(string $savedPath): void
    {
        $now = now()->timestamp;
        $list = Cache::get('ekg_pending_dats', []);

        // buang yang lebih dari 180 detik
        $list = array_values(array_filter($list, fn($d) => $now - $d['at'] <= 180));
        $list[] = ['path' => $savedPath, 'at' => $now];

        Cache::put('ekg_pending_dats', $list, now()->addMinutes(10));
        Log::info('[DAT] Ditahan menunggu PDF/XML berikutnya', ['path' => $savedPath]);
    }

    private function attachPendingDat(?string $patientCode): void
    {
        if (!$patientCode) {
            return;
        }

        $now = now()->timestamp;
        $list = Cache::get('ekg_pending_dats', []);
        $list = array_values(array_filter($list, fn($d) => $now - $d['at'] <= 180));

        if (empty($list)) {
            return;
        }

        // ambil DAT terbaru, buang sisanya supaya tidak menempel ke pemeriksaan lain
        $latest = end($list);
        Cache::forget('ekg_pending_dats');

        $this->saveDatResult($patientCode, $latest['path']);
        Log::info("[DAT] Ditempelkan ke {$patientCode}", ['path' => $latest['path']]);
    }

    private function printableTokens(string $raw): array
    {
        $text = str_replace("\0", '', $raw);
        preg_match_all('/[\x20-\x7E]{3,40}/', $text, $m);
        return array_values(array_unique(array_map('trim', $m[0])));
    }

    private function findPatientCodeInTokens(array $tokens): ?string
    {
        if (empty($tokens)) {
            return null;
        }
        // cocokkan token dengan patient_code yang sudah ada di database
        return Patient::whereIn('patient_code', $tokens)->value('patient_code');
    }

    private function parseDat(string $raw, string $guid): ?array
    {
        $text = str_replace("\0", '', $raw);
        preg_match_all('/[\x20-\x7E]{2,120}/', $text, $m);

        // string di DAT diawali 1 byte panjang ("$" = 36, "!" = 33, dst), buang byte itu
        $tokens = array_map(function ($t) {
            if (strlen($t) > 1 && ord($t[0]) === strlen($t) - 1) {
                $t = substr($t, 1);
            }
            return trim($t);
        }, $m[0]);

        $idx = array_search(strtoupper($guid), array_map('strtoupper', $tokens), true);
        if ($idx === false) {
            return null;
        }

        $patientId = $tokens[$idx + 1] ?? null;
        $name = $tokens[$idx + 2] ?? null;
        $ageToken = $tokens[$idx + 3] ?? '';
        $sexToken = $tokens[$idx + 4] ?? '';

        if (!$patientId || !preg_match('/^[A-Za-z0-9\-_\.\/]{1,64}$/', $patientId)) {
            return null;
        }

        $age = preg_match('/^(\d{1,3})Y/', $ageToken, $a) ? (string) ((int) $a[1]) : null;
        $sex = preg_match('/^([MF])/i', $sexToken, $s) ? strtoupper($s[1]) : null;

        return [
            'patient_id' => $patientId,
            'patient_name' => $name ?: null,
            'patient_sex' => $sex,
            'patient_age' => $age,
        ];
    }



    private function routeToHis(string $payloadType, string $rawBody, string $filename, string $savedPath): void
    {
        $guid = $this->extractGuidFromFilename($filename);

        if ($payloadType === 'RAW_PDF') {
            $extracted = $this->extractPatientFromPdf($rawBody);
            Log::info('[PDF] Hasil ekstraksi', ['patient_id' => $extracted['patient_id'] ?? null]);

            if ($guid && $extracted && !empty($extracted['patient_age'])) {
                Cache::put("ekg_guid_cache:{$guid}", [
                    'patient_age' => $extracted['patient_age'],
                    'patient_name' => $extracted['patient_name'] ?? null,
                ], now()->addMinutes(10));
            }

            $this->savePdfResult($extracted, $savedPath);
            $this->attachPendingDat($this->lastPatientCode);

        } elseif ($payloadType === 'FDA_XML') {
            $extracted = $this->extractPatientFromXml($rawBody);
            $xmlToStore = $rawBody;

            if ($guid && Cache::has("ekg_guid_cache:{$guid}")) {
                $cached = Cache::get("ekg_guid_cache:{$guid}");
                if (!empty($cached['patient_age'])) {
                    $xmlToStore = $this->injectAgeToXml($rawBody, $cached['patient_age']);
                }
                Cache::forget("ekg_guid_cache:{$guid}");
            }

            Storage::disk('local')->put($savedPath, $xmlToStore);

            $this->saveXmlResult($extracted, $savedPath);
            $this->attachPendingDat($this->lastPatientCode);

        } elseif ($payloadType === 'DAT') {
            $dat = $this->parseDat($rawBody, (string) $guid);

            if ($dat) {
                Log::info('[DAT] Pasien dari isi file', ['patient_id' => $dat['patient_id']]);

                $patient = $this->resolvePatient(
                    $dat['patient_id'],
                    $dat['patient_name'],
                    $dat['patient_sex'],
                    $dat['patient_age']
                );
                $this->upsertEkgResult($patient, 'dat_file_path', $savedPath);
            } else {
                Log::warning('[DAT] ID pasien tidak terbaca dari isi file, ditahan', ['path' => $savedPath]);
                $this->holdPendingDat($savedPath);
            }
        } else {
            Log::warning("Payload type tidak didukung: {$payloadType}");
        }
    }

    private function extractPatientFromPdf(string $pdfBytes): ?array
    {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $text = $parser->parseContent($pdfBytes)->getText();

            // buang karakter NUL (teks 16-bit terbaca 8-bit)
            $text = str_replace("\0", '', $text);


            $patientId = null;
            if (preg_match('/ID:\s*([A-Za-z0-9\-]+)/', $text, $m)) {
                $patientId = trim($m[1]);
            }
            if (!$patientId) {
                return null;
            }

            $patientName = null;
            if (preg_match('/ID:\s*' . preg_quote($patientId, '/') . '\s*\n?\s*(.+)/', $text, $m)) {
                $patientName = trim($m[1]);
            }

            $sex = $age = null;
            if (preg_match('/(Male|Female)\s+(\d+)\s*Years/i', $text, $m)) {
                $sex = strtoupper(substr($m[1], 0, 1));
                $age = $m[2];
            }

            $pacemaker = null;
            if (preg_match('/Pacemaker:\s*(Yes|No)/i', $text, $m)) {
                $pacemaker = ucfirst(strtolower($m[1]));
            }

            return [
                'patient_id' => $patientId,
                'patient_name' => $patientName,
                'patient_sex' => $sex,
                'patient_age' => $age,
                'patient_pacemaker' => $pacemaker,
            ];
        } catch (\Throwable $e) {
            Log::warning('Gagal ekstrak data dari PDF: ' . $e->getMessage());
            return null;
        }
    }

    private function extractPatientFromXml(string $xmlBytes): ?array
    {
        try {
            $prevSetting = libxml_use_internal_errors(true);
            $root = simplexml_load_string($xmlBytes);
            libxml_use_internal_errors($prevSetting);

            if ($root === false) {
                return null;
            }

            $patientInfo = $root->PatientInfo ?? $root;

            $patientId = (string) ($patientInfo->PatientID ?? $patientInfo->patientID ?? $patientInfo->patient_id ?? '');
            $name = (string) ($patientInfo->Name ?? $patientInfo->PatientName ?? $patientInfo->patientName ?? '');
            $sex = (string) ($patientInfo->Sex ?? $patientInfo->PatientSex ?? $patientInfo->Gender ?? '');

            if ($patientId === '') {
                return null;
            }

            return [
                'patient_id' => $patientId,
                'patient_name' => $name ?: null,
                'patient_sex' => $sex ?: null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Gagal ekstrak data dari XML: ' . $e->getMessage());
            return null;
        }
    }

    private function injectAgeToXml(string $xmlBytes, string $age): string
    {
        try {
            $doc = new \DOMDocument();
            $doc->loadXML($xmlBytes);

            $patientInfoNodes = $doc->getElementsByTagName('PatientInfo');
            if ($patientInfoNodes->length === 0) {
                return $xmlBytes;
            }
            $patientInfo = $patientInfoNodes->item(0);

            $ageNodes = $patientInfo->getElementsByTagName('Age');
            if ($ageNodes->length > 0) {
                $ageElement = $doc->createElement('Age', $age);
                $patientInfo->replaceChild($ageElement, $ageNodes->item(0));
            } else {
                $ageElement = $doc->createElement('Age', $age);
                $patientInfo->appendChild($ageElement);
            }

            return $doc->saveXML();
        } catch (\Throwable $e) {
            Log::warning('Gagal inject age ke XML: ' . $e->getMessage());
            return $xmlBytes;
        }
    }



    private function resolvePatient(?string $extractedPatientId, ?string $name, ?string $sex, ?string $age, ?string $pacemaker = null): Patient
    {
        $queried = Cache::get('ekg_last_queried_patient_id');

        if ($extractedPatientId) {
            $patientCode = $extractedPatientId;
            $source = 'dokumen';
            $origin = ($queried && $queried === $extractedPatientId) ? 'QUERY' : 'MANUAL';

            // ID di file berbeda dari query terakhir = ini pemeriksaan manual,
            // cache query lama tidak boleh dipakai lagi
            if ($queried && $queried !== $extractedPatientId) {
                Cache::forget('ekg_last_queried_patient_id');
                Log::info("[PATIENT] ID file ({$extractedPatientId}) berbeda dari query ({$queried}), cache query dibuang");
            }
        } elseif ($queried) {
            $patientCode = $queried;
            $source = 'cache-query';
            $origin = 'QUERY';
            Log::warning("[PATIENT] ID tidak terbaca dari file, PAKAI cache query {$queried}. Cek apakah ini pemeriksaan manual!");
        } else {
            throw new \RuntimeException('Tidak ada patient_id sama sekali, upload dibatalkan');
        }

        $this->lastOrigin = $origin;
        $this->lastPatientCode = $patientCode;
        Log::info("[PATIENT] code={$patientCode} source={$source} origin={$origin}");

        $patient = Patient::where('patient_code', $patientCode)->first();
        if ($patient) {
            Log::info("[PATIENT] Ditemukan id={$patient->id} origin={$origin}");
            return $patient;
        }

        $genderMap = ['M' => 'Male', 'F' => 'Female'];
        $rawSex = strtoupper($sex ?? '');

        $patient = Patient::create([
            'patient_code' => $patientCode,
            'name' => $name ?: 'Unknown',
            'gender' => $genderMap[$rawSex] ?? 'Unknown',
            'age' => $age ?: 0,
            'pacemaker' => $pacemaker ?: 'No',
            'isInWorklist' => 2,
            'source' => 'Outpatient',
        ]);

        Log::info('[PATIENT] Auto-register', [
            'id' => $patient->id,
            'origin' => $origin,
            'code' => $patient->patient_code,
            'gender' => $patient->gender,
            'age' => $patient->age,
            'pacemaker' => $patient->pacemaker,
        ]);

        return $patient;
    }

    private function savePdfResult(?array $extracted, string $savedPath): void
    {
        $patient = $this->resolvePatient(
            $extracted['patient_id'] ?? null,
            $extracted['patient_name'] ?? null,
            $extracted['patient_sex'] ?? null,
            $extracted['patient_age'] ?? null,
            $extracted['patient_pacemaker'] ?? null
        );
        $this->upsertEkgResult($patient, 'result_file_path', $savedPath);
    }

    private function saveXmlResult(?array $extracted, string $savedPath): void
    {
        $patient = $this->resolvePatient(
            $extracted['patient_id'] ?? null,
            $extracted['patient_name'] ?? null,
            $extracted['patient_sex'] ?? null,
            null
        );
        $this->upsertEkgResult($patient, 'xml_file_path', $savedPath);
    }

    private function saveDatResult(?string $patientId, string $savedPath): void
    {
        $patient = $this->resolvePatient($patientId, null, null, null);
        $this->upsertEkgResult($patient, 'dat_file_path', $savedPath);
    }


    private function upsertEkgResult(Patient $patient, string $column, string $path): void
    {
        $existing = EkgResult::where('patient_id', $patient->id)
            ->where('examination_date', '>=', now()->subSeconds(180))
            ->whereNull($column)
            ->latest('examination_date')
            ->first();

        if ($existing) {
            $existing->update([$column => $path]);
            Log::info("[EKG] Update row id={$existing->id}, kolom={$column}");
        } else {
            $created = EkgResult::create([
                'patient_id' => $patient->id,
                'origin' => $this->lastOrigin,
                $column => $path,
                'examination_date' => now(),
            ]);
            Log::info("[EKG] Insert row id={$created->id}, kolom={$column}, origin={$this->lastOrigin}");
        }

        if ((int) $patient->isInWorklist !== 2) {
            $patient->update(['isInWorklist' => 2]);
            Log::info("[WORKLIST] Pasien id={$patient->id} isInWorklist -> 2 (selesai)");
        }
    }
}
