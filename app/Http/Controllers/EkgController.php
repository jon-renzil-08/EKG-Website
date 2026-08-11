<?php
namespace App\Http\Controllers;

use App\Models\EkgResult;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EkgController extends Controller
{
    public function index()
    {
        $ekgResults = EkgResult::with('patient')->get();
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
            'patient_id'       => 'required|exists:patients,id',
            'result_file'      => 'required|file|mimes:pdf|max:2048',
            'examination_date' => 'required|date',
        ]);

        $file     = $request->file('result_file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('ekg_results', $fileName, 'public');

        EkgResult::create([
            'patient_id'       => $request->patient_id,
            'result_file_path' => $filePath,
            'examination_date' => $request->examination_date,
        ]);

        return redirect()->route('ekg.index')->with('success', 'EKG result created successfully');
    }

    public function download($id)
    {
        $ekgResult = EkgResult::findOrFail($id);

        $relativePath = preg_replace('/^public\//', '', $ekgResult->result_file_path);

        $filePath = storage_path('app/public/' . $relativePath);

        if (file_exists($filePath)) {
            return response()->download($filePath);
        }

        abort(404, 'File not found');
    }

    public function destroy(EkgResult $ekgResult)
    {
        // Hapus file dari storage
        if (Storage::disk('public')->exists($ekgResult->result_file_path)) {
            Storage::disk('public')->delete($ekgResult->result_file_path);
        }

        $ekgResult->delete();
        return redirect()->route('ekg.index')->with('success', 'EKG result deleted successfully');
    }

    // recieve ekg result from external system
    public function receive(Request $request)
    {
        try {
            $filename = time() . '_ecg.pdf';
            $rawData  = null;

            // Ambil semua files
            $files = $request->allFiles();

            if (! empty($files)) {
                // Ambil file pertama apapun nama fieldnya
                $firstKey = array_key_first($files);
                $file     = $files[$firstKey];

                Log::info('File key: ' . $firstKey);
                Log::info('File size: ' . $file->getSize());
                Log::info('File error: ' . $file->getError());
                Log::info('File path: ' . $file->getRealPath());

                // Baca dari temp path langsung
                $rawData = file_get_contents($file->getRealPath());
                Log::info('Raw data size: ' . strlen($rawData));

            } else {
                Log::warning('No files found!');
                throw new \Exception('No file received');
            }

            if (empty($rawData)) {
                throw new \Exception('File is empty');
            }

            // Strip DICOM header jika ada
            $pdfStart = strpos($rawData, '%PDF');
            if ($pdfStart !== false) {
                Log::info('PDF marker found at: ' . $pdfStart);
                $rawData = substr($rawData, $pdfStart);
            }

            Storage::disk('public')->put('ecg/' . $filename, $rawData);
            Log::info('ECG saved: ' . $filename . ' (' . strlen($rawData) . ' bytes)');

            $xml = '<?xml version="1.0" encoding="UTF-8"?>
<root>
    <Code>1</Code>
    <Message>Success</Message>
</root>';

            return response($xml, 200)
                ->header('Content-Type', 'application/xml')
                ->header('Connection', 'close');

        } catch (\Exception $e) {
            Log::error('ECG receive error: ' . $e->getMessage());

            $xml = '<?xml version="1.0" encoding="UTF-8"?>
<root>
    <Code>0</Code>
    <Message>' . $e->getMessage() . '</Message>
</root>';

            return response($xml, 500)
                ->header('Content-Type', 'application/xml')
                ->header('Connection', 'close');
        }
    }

    public function query(Request $request)
    {
        Log::info('Query body: ' . $request->getContent());

        // Parse XML dari alat
        $xml       = simplexml_load_string($request->getContent());
        $patientId = (string) ($xml->PatientID ?? '');
        $guid      = (string) ($xml->guid ?? '');

        Log::info('PatientID: ' . $patientId);
        Log::info('GUID: ' . $guid);

        // Response dengan data yang match
        $response = '<?xml version="1.0" encoding="UTF-8"?>
<root>
    <Code>1</Code>
    <Message></Message>
    <records>
        <rows>
            <SerialNo>' . $guid . '</SerialNo>
            <PatientID>' . $patientId . '</PatientID>
            <PatientName></PatientName>
            <PatientSex></PatientSex>
            <PatientAge></PatientAge>
            <PatientAgeUnit>Y</PatientAgeUnit>
            <PatientBirthDate></PatientBirthDate>
            <RequestDepartment></RequestDepartment>
            <RequestID>' . $guid . '</RequestID>
            <SickBedNo></SickBedNo>
            <Pacemaker>2</Pacemaker>
            <ExamDepartment></ExamDepartment>
            <Priority></Priority>
            <fileGuid>' . $guid . '</fileGuid>
            <RequestDate></RequestDate>
        </rows>
    </records>
</root>';

        return response($response, 200)
            ->header('Content-Type', 'application/xml')
            ->header('Connection', 'close');
    }

}
