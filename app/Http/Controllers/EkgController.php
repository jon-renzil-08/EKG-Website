<?php

namespace App\Http\Controllers;

use App\Models\EkgResult;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EkgController extends Controller
{
    public function index(Request $request)
    {
        $query = EkgResult::with('patient');


        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('patient_code', 'LIKE', "%{$search}%");
            });
        }

        $ekgResults = $query->latest()->paginate(10)->appends(request()->query());

        return view('ekg.index', compact('ekgResults'));
    }

    public function create()
    {
        $patients = Patient::all();
        return view('ekg.create', compact('patients'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'result_file' => 'required|file|mimes:pdf|max:2048',
            'examination_date' => 'required|date',
        ]);

        $file = $request->file('result_file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('ekg_results', $fileName, 'public');

        EkgResult::create([
            'patient_id' => $request->patient_id,
            'pdf_file_path' => $filePath,
            'examination_date' => $request->examination_date,
        ]);

        return redirect()->route('ekg.index')->with('success', 'EKG result created successfully');
    }

    public function destroy(EkgResult $ekg)
    {
        if ($ekg->pdf_file_path && Storage::disk('public')->exists($ekg->pdf_file_path)) {
            Storage::disk('public')->delete($ekg->pdf_file_path);
        }

        $ekg->delete();

        return redirect()->route('ekg.index')->with('success', 'EKG result deleted successfully');
    }


    public function query(Request $request)
    {
        try {
            // 1. Ambil XML dari alat EKG
            $xmlData = $request->getContent();

            // 2. Parse XML
            $xml = simplexml_load_string($xmlData);

            if ($xml === false) {
                throw new \Exception('Invalid XML request');
            }

            // 3. Ambil PatientID dan DeviceID
            $patientId = (string) $xml->PatientID;
            $deviceId = (string) $xml->DeviceID;

            Log::info('Dawei Query Request', [
                'patient_id' => $patientId,
                'device_id' => $deviceId,
            ]);

            // 4. Ambil data pasien dari database
            $patients = Patient::where('isInWorklist', 1)
                ->where('patient_code', $patientId)
                ->get();

            // 5. Jika pasien tidak ditemukan
            if ($patients->isEmpty()) {
                $response = new \SimpleXMLElement(
                    '<?xml version="1.0" encoding="UTF-8"?><Error/>'
                );

                $response->addAttribute('message', 'Patient not found');

                return response($response->asXML(), 200)
                    ->header('Content-Type', 'application/xml');
            }

            // 6. Buat XML response
            $root = new \SimpleXMLElement('<root/>');

            $root->addChild('Code', '1');
            $root->addChild('Message', '');

            $records = $root->addChild('records');

            foreach ($patients as $index => $patient) {
                $rows = $records->addChild('rows');

                $rows->addChild('SerialNo', $index + 1);
                $rows->addChild('PatientID', htmlspecialchars($patient->patient_code));
                $rows->addChild('PatientName', htmlspecialchars($patient->name));
                $rows->addChild('PatientSex', $patient->gender === 'Male' ? 'M' : ($patient->gender === 'Female' ? 'F' : 'O'));
                $rows->addChild('PatientAge', $patient->age);
                $rows->addChild('PatientAgeUnit', 'Y');
                $rows->addChild('PatientBirthDate', '');
                $rows->addChild('RequestDepartment', '');
                $rows->addChild('RequestID', '');
                $rows->addChild('SickBedNo', '');
                $rows->addChild('Pacemaker', $patient->pacemaker === 'Yes' ? '1' : '2');

                $sourceMap = [
                    'Outpatient' => '0',
                    'Inpatient' => '1',
                    'Physical Exam' => '2',
                ];

                $rows->addChild(
                    'PatientSource',
                    $sourceMap[$patient->source] ?? '1'
                );

                $rows->addChild('ExamDepartment', '');
                $rows->addChild('Priority', '');
                $rows->addChild('fileGuid', '');
                $rows->addChild(
                    'RequestDate',
                    $patient->created_at?->format('Y-m-d H:i:s') ?? ''
                );
            }

            // 7. Kirim XML ke alat EKG
            return response($root->asXML(), 200)
                ->header('Content-Type', 'application/xml');

        } catch (\Throwable $e) {
            Log::error('Dawei Query Error: ' . $e->getMessage());

            $error = new \SimpleXMLElement('<Error/>');
            $error->addAttribute('message', $e->getMessage());

            return response($error->asXML(), 500)
                ->header('Content-Type', 'application/xml');
        }
    }
}
