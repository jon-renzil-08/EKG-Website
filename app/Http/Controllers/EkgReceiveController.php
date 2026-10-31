<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\EkgResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
            $incoming = $this->saveIncomingFile($request);

            if ($incoming === null) {
                Log::info('Network test request - no file to process');

                return $this->xmlResponse(1, 'Network test success');
            }

            $rawBody = $incoming['rawBody'];
            $filename = $incoming['filename'];
            $savedPath = $incoming['savedPath'];

            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            Log::info("[RECEIVE] Filename: {$filename}, size: " . strlen($rawBody) . ' bytes');

            $payloadType = $this->classifyPayload($extension);

            if ($payloadType === 'UNKNOWN') {
                throw new \RuntimeException(
                    'Unsupported file type. Allowed: PDF, XML, DAT.'
                );
            }

            Log::info("[CLASSIFY] Payload type: {$payloadType}");

            // Proses file beserta data request
            $this->routeToHis(
                $payloadType,
                $rawBody,
                $filename,
                $savedPath,
                $request
            );

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

    private function saveIncomingFile(Request $request): ?array
    {
        $uploadedFile = $this->findUploadedFile($request->allFiles());

        $isMultipart = str_contains(
            strtolower($request->header('Content-Type', '')),
            'multipart/form-data'
        );

        if ($uploadedFile) {
            // Upload multipart/form-data
            $rawBody = file_get_contents($uploadedFile->getRealPath());
            $filename = $uploadedFile->getClientOriginalName();

        } else {
            // Upload raw binary
            $rawBody = $request->getContent();

            if ($isMultipart) {
                throw new \RuntimeException('Multipart request tidak berisi file.');
            }

            if ($rawBody === '') {
                if ($request->all() !== []) {
                    throw new \RuntimeException('Request tidak berisi file.');
                }

                return null;
            }

            $filename = $request->header('Filename')
                ?: $this->getFilenameFromRequest($request);
        }

        if ($rawBody === false || strlen($rawBody) === 0) {
            throw new \RuntimeException('File kosong.');
        }

        // Amankan nama file
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($extension, ['pdf', 'xml', 'dat'], true)) {
            throw new \RuntimeException('Format file tidak didukung: ' . $extension);
        }

        $path = 'ekg-incoming/'
            . now()->format('Ymd_His')
            . '_'
            . Str::random(6)
            . '_'
            . $filename;

        $saved = Storage::disk('local')->put($path, $rawBody);

        if (!$saved) {
            throw new \RuntimeException('Gagal menyimpan file.');
        }

        return [
            'rawBody' => $rawBody,
            'filename' => $filename,
            'savedPath' => $path,
        ];
    }


    private function findUploadedFile(array $files)
    {
        foreach ($files as $file) {
            if (is_array($file)) {
                $found = $this->findUploadedFile($file);

                if ($found) {
                    return $found;
                }
            } elseif ($file instanceof \Illuminate\Http\UploadedFile) {
                return $file;
            }
        }

        return null;
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

        $tokens = array_map(function ($t) {
            if (strlen($t) > 1 && ord($t[0]) === strlen($t) - 1) {
                $t = substr($t, 1);
            }

            return trim($t);
        }, $m[0]);

        Log::info('[DAT] GUID pencarian', [
            'guid' => $guid,
            'token_count' => count($tokens),
            'tokens_sample' => array_slice($tokens, 0, 50),
        ]);

        $idx = array_search(
            strtoupper($guid),
            array_map('strtoupper', $tokens),
            true
        );

        if ($idx === false) {
            Log::warning('[DAT] GUID tidak ditemukan dalam token');

            return null;
        }

        $patientId = $tokens[$idx + 1] ?? null;
        $name = $tokens[$idx + 2] ?? null;
        $ageToken = $tokens[$idx + 3] ?? '';
        $sexToken = $tokens[$idx + 4] ?? '';

        if (!$patientId || !preg_match('/^[A-Za-z0-9\-_.\/]{1,64}$/', $patientId)) {
            return null;
        }

        $age = preg_match('/^(\d{1,3})Y/', $ageToken, $a)
            ? (string) ((int) $a[1])
            : null;

        $sex = preg_match('/^([MF])/i', $sexToken, $s)
            ? strtoupper($s[1])
            : null;

        return [
            'patient_id' => $patientId,
            'patient_name' => $name ?: null,
            'patient_sex' => $sex,
            'patient_age' => $age,
        ];
    }






    private function routeToHis(
        string $payloadType,
        string $rawBody,
        string $filename,
        string $savedPath,
        Request $request
    ): void {

        $guid = $this->extractGuidFromFilename($filename);

        // Ambil PatientID dan DeviceID dari hasil Query
        $queriedPatientId = Cache::get('ekg_last_queried_patient_id');

        $deviceNo = $queriedPatientId
            ? Cache::get("ekg_device_by_patient:{$queriedPatientId}")
            : null;

        // Jika device_no dikirim langsung melalui request, gunakan sebagai fallback
        $deviceNo = $deviceNo ?: $request->input('device_no');

        Log::info('[RECEIVE] Device information', [
            'patient_id' => $queriedPatientId,
            'device_no' => $deviceNo,
        ]);

        if ($payloadType === 'RAW_PDF') {

            $extracted = $this->extractPatientFromPdf($rawBody);

            Log::info('[PDF] Hasil ekstraksi', [
                'patient_id' => $extracted['patient_id'] ?? null,
            ]);

            if ($guid && $extracted && !empty($extracted['patient_age'])) {
                Cache::put("ekg_guid_cache:{$guid}", [
                    'patient_age' => $extracted['patient_age'],
                    'patient_name' => $extracted['patient_name'] ?? null,
                ], now()->addMinutes(10));
            }

            $patient = $this->savePdfResult(
                $extracted,
                $savedPath,
                $deviceNo
            );

            $this->sendToSimrs(
                $patient->patient_code,
                $savedPath,
                $filename,
                'application/pdf',
                $deviceNo ?? ''
            );

        } elseif ($payloadType === 'FDA_XML') {

            $extracted = $this->extractPatientFromXml($rawBody);
            $xmlToStore = $rawBody;

            if ($guid && Cache::has("ekg_guid_cache:{$guid}")) {

                $cached = Cache::get("ekg_guid_cache:{$guid}");

                if (!empty($cached['patient_age'])) {
                    $xmlToStore = $this->injectAgeToXml(
                        $rawBody,
                        $cached['patient_age']
                    );
                }

                Cache::forget("ekg_guid_cache:{$guid}");
            }

            Storage::disk('local')->put($savedPath, $xmlToStore);

            $patient = $this->saveXmlResult(
                $extracted,
                $savedPath,
                $deviceNo
            );

            $this->sendToSimrs(
                $patient->patient_code,
                $savedPath,
                $filename,
                'application/xml',
                $deviceNo ?? ''
            );

        } elseif ($payloadType === 'DAT') {

            $patientId = $request->input('patient_id');
            $patientName = $request->input('patient_name');
            $patientSex = $request->input('patient_sex');
            $patientAge = $request->input('patient_age');

            if (empty($patientId)) {
                $patientId = $queriedPatientId;
            }

            // Ambil DeviceID berdasarkan PatientID
            $deviceNo = Cache::get(
                "ekg_device_by_patient:{$patientId}"
            ) ?: $deviceNo;

            Log::info('[DAT] Data pasien dari request/cache', [
                'patient_id' => $patientId,
                'patient_name' => $patientName,
                'patient_sex' => $patientSex,
                'patient_age' => $patientAge,
                'device_no' => $deviceNo,
            ]);

            if (empty($patientId)) {
                throw new \RuntimeException(
                    'Patient ID tidak ditemukan pada request maupun cache Query.'
                );
            }

            $patient = $this->resolvePatient(
                $patientId,
                $patientName,
                $patientSex,
                $patientAge
            );

            $this->upsertEkgResult(
                $patient,
                'dat_file_path',
                $savedPath,
                $deviceNo
            );

            $this->sendToSimrs(
                $patient->patient_code,
                $savedPath,
                $filename,
                'application/octet-stream',
                $deviceNo ?? ''
            );

            Log::info('[DAT] Hasil berhasil disimpan', [
                'patient_id' => $patient->patient_code,
                'patient_name' => $patientName,
                'device_no' => $deviceNo,
                'path' => $savedPath,
            ]);
        }
    }



    private function sendToSimrs(
        string $patientCode,
        string $savedPath,
        string $filename,
        string $contentType,
        string $deviceNo = ''
    ): void {

        $simrs = config('services.simrs');

        if (empty($simrs['enable'])) {
            Log::info('[SIMRS] Pengiriman dinonaktifkan.');
            return;
        }

        $url = $simrs['send_url'] ?? null;

        if (empty($url)) {
            Log::warning('[SIMRS] SEND_URL kosong, pengiriman dilewati.');
            return;
        }

        try {
            // Membaca file yang sudah tersimpan di storage
            $fileBytes = Storage::disk('local')->get($savedPath);

            $response = Http::timeout(15)
                ->acceptJson()
                ->attach(
                    'file',
                    $fileBytes,
                    $filename,
                    ['Content-Type' => $contentType]
                )
                ->post($url, [
                    'patient_code' => $patientCode,
                    'device_no' => $deviceNo,
                ]);

            if ($response->successful()) {
                Log::info('[SIMRS] Hasil EKG berhasil dikirim.', [
                    'patient_code' => $patientCode,
                    'device_no' => $deviceNo,
                    'response' => $response->json(),
                ]);
            } else {
                Log::error('[SIMRS] Gagal mengirim hasil EKG.', [
                    'patient_code' => $patientCode,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('[SIMRS] Exception saat pengiriman.', [
                'patient_code' => $patientCode,
                'message' => $e->getMessage(),
            ]);
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




    private function resolvePatient(
        ?string $extractedPatientId,
        ?string $name,
        ?string $sex,
        ?string $age,
        ?string $pacemaker = null
    ): Patient {
        $queried = Cache::get('ekg_last_queried_patient_id');

        Log::info('[RECEIVE] Cache patient ID:', [
            'patient_id' => $queried
        ]);

        // Prioritaskan PatientID dari Query jika tersedia.
        if ($queried) {
            $patientCode = $queried;
            $source = 'cache-query';
            $origin = 'QUERY';

            if ($extractedPatientId && $extractedPatientId !== $queried) {
                Log::info(
                    "[PATIENT] ID file ({$extractedPatientId}) berbeda dari Query ({$queried}), tetap menggunakan PatientID Query"
                );
            } else {
                Log::info("[PATIENT] Menggunakan PatientID Query: {$queried}");
            }

        } elseif ($extractedPatientId) {
            // Jika belum ada Query, gunakan ID dari dokumen.
            $patientCode = $extractedPatientId;
            $source = 'dokumen';
            $origin = 'MANUAL';

        } else {
            throw new \RuntimeException(
                'Tidak ada patient_id sama sekali, upload dibatalkan'
            );
        }

        $this->lastOrigin = $origin;
        $this->lastPatientCode = $patientCode;

        Log::info("[PATIENT] code={$patientCode} source={$source} origin={$origin}");

        $patient = Patient::where('patient_code', $patientCode)->first();

        if ($patient) {
            Log::info("[PATIENT] Ditemukan id={$patient->id} origin={$origin}");
            return $patient;
        }

        $genderMap = [
            'M' => 'Male',
            'F' => 'Female'
        ];

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


    private function savePdfResult(
        ?array $extracted,
        string $savedPath,
        ?string $deviceNo = null
    ): Patient {

        $patient = $this->resolvePatient(
            $extracted['patient_id'] ?? null,
            $extracted['patient_name'] ?? null,
            $extracted['patient_sex'] ?? null,
            $extracted['patient_age'] ?? null,
            $extracted['patient_pacemaker'] ?? null
        );

        $this->upsertEkgResult(
            $patient,
            'pdf_file_path',
            $savedPath,
            $deviceNo
        );

        return $patient;
    }

    private function saveXmlResult(
        ?array $extracted,
        string $savedPath,
        ?string $deviceNo = null
    ): Patient {

        $patient = $this->resolvePatient(
            $extracted['patient_id'] ?? null,
            $extracted['patient_name'] ?? null,
            $extracted['patient_sex'] ?? null,
            null
        );

        $this->upsertEkgResult(
            $patient,
            'xml_file_path',
            $savedPath,
            $deviceNo
        );

        return $patient;
    }

    private function saveDatResult(
        ?string $patientId,
        string $savedPath,
        ?string $deviceNo = null
    ): Patient {

        $patient = $this->resolvePatient(
            $patientId,
            null,
            null,
            null
        );

        $this->upsertEkgResult(
            $patient,
            'dat_file_path',
            $savedPath,
            $deviceNo
        );

        return $patient;
    }


    private function upsertEkgResult(
        Patient $patient,
        string $column,
        string $path,
        ?string $deviceNo = null
    ): void {

        $fileTypes = [
            'pdf_file_path' => 'PDF',
            'xml_file_path' => 'XML',
            'dat_file_path' => 'DAT',
        ];

        if (!isset($fileTypes[$column])) {
            throw new \InvalidArgumentException(
                "Kolom file tidak valid: {$column}"
            );
        }

        // Setiap hasil pemeriksaan membuat record baru
        $created = EkgResult::create([
            'patient_id' => $patient->id,
            $column => $path,
            'result_file_type' => $fileTypes[$column],
            'device_no' => $deviceNo,
            'examination_date' => now(),
        ]);

        Log::info(
            "[EKG] Insert row id={$created->id}, patient_id={$patient->id}, type={$fileTypes[$column]}"
        );
    }


}
