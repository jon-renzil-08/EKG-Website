<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\EkgResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pengganti endpoint /receive di middleware Python.
 * Device kirim POST dengan body RAW BINARY (bukan multipart), nama file
 * lewat header custom "Filename", Content-Type sesuai jenis file
 * (application/pdf, application/xml, application/octet-stream untuk .dat).
 *
 * CATATAN: nama header "Filename" dan cara klasifikasi tipe file di sini
 * adalah ASUMSI berdasarkan raw HTTP dump yang pernah diperiksa sebelumnya.
 * Perlu divalidasi ulang begitu ada kesempatan test pakai device asli --
 * kalau header-nya ternyata beda, tinggal sesuaikan di getFilenameFromRequest().
 */
class EkgReceiveController extends Controller
{
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

    // ------------------------------------------------------------------
    // Helpers dasar
    // ------------------------------------------------------------------

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
            'text/xml'        => 'ecg_result.xml',
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

    // ------------------------------------------------------------------
    // GUID pairing (sama konsep dengan _guid_cache di Python) --
    // dipakai untuk pasangkan PDF + XML dari exam yang sama, supaya
    // Age yang diekstrak dari PDF bisa di-inject ke file XML pasangannya.
    // ------------------------------------------------------------------

    private function extractGuidFromFilename(string $filename): ?string
    {
        // Contoh nama file device: 0000001C-0078-00EB-B443-1E4058929176.pdf
        // Ambil bagian sebelum ekstensi sebagai GUID.
        $name = pathinfo($filename, PATHINFO_FILENAME);
        return $name !== '' ? $name : null;
    }

    // ------------------------------------------------------------------
    // Routing & ekstraksi data pasien
    // ------------------------------------------------------------------

    private function routeToHis(string $payloadType, string $rawBody, string $filename, string $savedPath): void
    {
        $guid = $this->extractGuidFromFilename($filename);

        if ($payloadType === 'RAW_PDF') {
            $extracted = $this->extractPatientFromPdf($rawBody);

            if ($guid && $extracted && !empty($extracted['patient_age'])) {
                Cache::put("ekg_guid_cache:{$guid}", [
                    'patient_age'  => $extracted['patient_age'],
                    'patient_name' => $extracted['patient_name'] ?? null,
                ], now()->addMinutes(10));
            }

            $this->savePdfResult($extracted, $savedPath);

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

            // simpan ulang file XML yang sudah disisipi Age (replace file yang sudah tersimpan)
            Storage::disk('local')->put($savedPath, $xmlToStore);

            $this->saveXmlResult($extracted, $savedPath);

        } elseif ($payloadType === 'DAT') {
            // DAT tidak pernah dikirim saat manual entry (sudah dikonfirmasi sebelumnya),
            // jadi cukup pakai fallback patient_id dari cache /query terakhir.
            $patientId = Cache::get('ekg_last_queried_patient_id');
            $this->saveDatResult($patientId, $savedPath);

        } else {
            Log::warning("Payload type tidak didukung: {$payloadType}");
        }
    }

    private function extractPatientFromPdf(string $pdfBytes): ?array
    {
        try {
            $tmpPath = tempnam(sys_get_temp_dir(), 'ekg_pdf_') . '.pdf';
            file_put_contents($tmpPath, $pdfBytes);

            // butuh: composer require smalot/pdfparser
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($tmpPath);
            $text = $pdf->getText();
            @unlink($tmpPath);

            $patientId = null;
            if (preg_match('/ID:\s*([A-Za-z0-9\-]+)/', $text, $m)) {
                $patientId = trim($m[1]);
            }

            $patientName = null;
            if ($patientId && preg_match('/ID:\s*' . preg_quote($patientId, '/') . '\s*\n?\s*(.+)/', $text, $m)) {
                $patientName = trim($m[1]);
            }

            $sex = null;
            $age = null;
            if (preg_match('/(Male|Female)\s+(\d+)\s*Years/i', $text, $m)) {
                $sex = strtoupper(substr($m[1], 0, 1)); // Male->M, Female->F
                $age = $m[2];
            }

            if (!$patientId) {
                return null;
            }

            return [
                'patient_id'   => $patientId,
                'patient_name' => $patientName,
                'patient_sex'  => $sex,
                'patient_age'  => $age,
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
                'patient_id'   => $patientId,
                'patient_name' => $name ?: null,
                'patient_sex'  => $sex ?: null,
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
                $ageNodes->item(0)->nodeValue = $age;
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

    // ------------------------------------------------------------------
    // Penyimpanan ke DB -- resolve pasien (extract dulu, fallback cache,
    // baru auto-register) sesuai fix bug stale-patient-id yang sudah
    // terbukti benar di middleware Python.
    // ------------------------------------------------------------------

    private function resolvePatient(?string $extractedPatientId, ?string $name, ?string $sex, ?string $age): Patient
    {
        $patientCode = $extractedPatientId ?: Cache::get('ekg_last_queried_patient_id');

        if (!$patientCode) {
            throw new \RuntimeException('Tidak ada patient_id sama sekali, upload dibatalkan');
        }

        $patient = Patient::where('patient_code', $patientCode)->first();
        if ($patient) {
            return $patient;
        }

        // auto-register pasien baru (manual entry dari device, belum ada di Laravel)
        $genderMap = ['M' => 'Male', 'F' => 'Female'];
        $rawSex = strtoupper($sex ?? '');

        return Patient::create([
            'patient_code'  => $patientCode,
            'name'          => $name ?: 'Unknown',
            'gender'        => $genderMap[$rawSex] ?? 'Unknown',
            'age'           => $age ?: 0,
            'pacemaker'     => 'No',
            'isInWorklist'  => 2,
            'source'        => 'Outpatient',
        ]);
    }

    private function savePdfResult(?array $extracted, string $savedPath): void
    {
        $patient = $this->resolvePatient(
            $extracted['patient_id'] ?? null,
            $extracted['patient_name'] ?? null,
            $extracted['patient_sex'] ?? null,
            $extracted['patient_age'] ?? null
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

    /**
     * Gabungkan ke 1 row ekg_results kalau ada row untuk pasien yang sama
     * dalam window 60 detik terakhir (exam yang sama, cuma format file beda),
     * sesuai logic yang sudah ada di route /ecg-result Laravel.
     */
    private function upsertEkgResult(Patient $patient, string $column, string $path): void
    {
        $existing = EkgResult::where('patient_id', $patient->id)
            ->where('examination_date', '>=', now()->subSeconds(60))
            ->latest('examination_date')
            ->first();

        if ($existing) {
            $existing->update([$column => $path]);
            return;
        }

        EkgResult::create([
            'patient_id'       => $patient->id,
            $column            => $path,
            'examination_date' => now(),
        ]);
    }
}
