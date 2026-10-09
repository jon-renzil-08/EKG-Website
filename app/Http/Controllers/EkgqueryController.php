<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Pengganti endpoint /query di middleware Python (query_worklist()).
 * Sekarang Laravel langsung query tabel `patients` sendiri -- tidak perlu
 * lagi panggil endpoint /worklist lewat HTTP (karena itu cuma Laravel
 * memanggil dirinya sendiri, jadi dihilangkan, langsung query DB).
 *
 * Device kirim POST dengan body RAW XML berisi <PatientID>...</PatientID>,
 * dan mengharapkan balasan XML juga (BUKAN JSON).
 */
class EkgQueryController extends Controller
{
    public function query(Request $request)
    {
        $rawXml = $request->getContent();
        Log::info('RAW XML dari device (/api/query):', ['xml' => $rawXml]);

        try {
            $patientId = $this->parsePatientIdFromXml($rawXml);
            Log::info("Received request for PatientID: {$patientId}");

            if (!$patientId) {
                return $this->errorXmlResponse('Patient not found');
            }

            // Simpan sementara PatientID ini sebagai "kandidat terakhir yang di-query",
            // dipakai di EkgReceiveController HANYA sebagai fallback terakhir kalau
            // ekstraksi dari file PDF/XML gagal total -- bukan sumber utama, supaya
            // tidak mengulang bug "stale patient id" yang dulu terjadi di middleware Python.
            Cache::put('ekg_last_queried_patient_id', $patientId, now()->addMinutes(2));

            $patients = Patient::where('isInWorklist', 1)
                ->where('patient_code', $patientId)
                ->get();

            if ($patients->isEmpty()) {
                return $this->errorXmlResponse('Patient not found');
            }

            return $this->worklistXmlResponse($patients);

        } catch (\Throwable $e) {
            Log::error('Error in EkgQueryController@query: ' . $e->getMessage());
            return response(
                $this->buildErrorXml($e->getMessage()),
                500
            )->header('Content-Type', 'application/xml');
        }
    }

    private function parsePatientIdFromXml(string $xml): ?string
    {
        if (trim($xml) === '') {
            return null;
        }
        try {
            $prevSetting = libxml_use_internal_errors(true);
            $root = simplexml_load_string($xml);
            libxml_use_internal_errors($prevSetting);

            if ($root === false) {
                return null;
            }
            $value = (string) $root->PatientID;
            return $value !== '' ? $value : null;
        } catch (\Throwable $e) {
            Log::warning('Gagal parse PatientID dari XML device: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Bentuk response XML <root><Code>1</Code>...<records><rows>...</rows></records></root>
     * SAMA PERSIS struktur/urutan tag dengan query_backend_service() di Python,
     * supaya device (yang parsing-nya kemungkinan urut posisi/nama tag) tetap bisa baca.
     */
    private function worklistXmlResponse($patients): \Illuminate\Http\Response
    {
        $sourceMap = [
            'Outpatient'    => '0',
            'Inpatient'     => '1',
            'Physical Exam' => '2',
        ];

        $rowsXml = '';
        foreach ($patients as $p) {
            $sex = $p->gender === 'Male' ? 'M' : ($p->gender === 'Female' ? 'F' : 'O');
            $pacemakerCode = $p->pacemaker === 'Yes' ? '1' : '2';
            $sourceCode = $sourceMap[$p->source] ?? '1';

            // Cache data pasien ini, dipakai EkgReceiveController untuk fallback
            // age/name/sex kalau nanti device kirim XML hasil EKG tanpa info itu.
            Cache::put("ekg_patient_data:{$p->patient_code}", [
                'patient_name' => $p->name,
                'patient_sex'  => $sex,
                'patient_age'  => (string) $p->age,
            ], now()->addMinutes(15));

            $rowsXml .= '<rows>'
                . '<SerialNo>' . e($p->patient_code) . '</SerialNo>'
                . '<PatientID>' . e($p->patient_code) . '</PatientID>'
                . '<PatientName>' . e($p->name) . '</PatientName>'
                . '<PatientSex>' . e($sex) . '</PatientSex>'
                . '<PatientAge>' . e((string) $p->age) . '</PatientAge>'
                . '<PatientAgeUnit>Y</PatientAgeUnit>'
                . '<PatientBirthDate></PatientBirthDate>'
                . '<RequestDepartment></RequestDepartment>'
                . '<RequestID>' . e('ECG' . $p->patient_code) . '</RequestID>'
                . '<SickBedNo></SickBedNo>'
                . '<Pacemaker>' . $pacemakerCode . '</Pacemaker>'
                . '<PatientSource>' . $sourceCode . '</PatientSource>'
                . '<ExamDepartment></ExamDepartment>'
                . '<Priority></Priority>'
                . '<fileGuid></fileGuid>'
                . '<RequestDate>' . $p->created_at->format('Ymd') . '</RequestDate>'
                . '</rows>';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<root><Code>1</Code><Message></Message><records>' . $rowsXml . '</records></root>';

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    /**
     * SAMA PERSIS dengan fallback di query_worklist() Python saat xml_response kosong:
     * etree.Element("Error", message="...") -- elemen self-closing dengan atribut message.
     */
    private function errorXmlResponse(string $message): \Illuminate\Http\Response
    {
        return response($this->buildErrorXml($message), 200)
            ->header('Content-Type', 'application/xml');
    }

    private function buildErrorXml(string $message): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Error message="' . htmlspecialchars($message, ENT_XML1) . '"/>';
    }
}
