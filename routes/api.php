<?php

use App\Http\Controllers\Api\WorklistController;
use App\Models\EkgResult;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Routes Worklist API
Route::get('/worklist', function (Request $request) {
    $patientId = $request->query('patient_id');

    $query = Patient::where('isInWorklist', 1);

    if ($patientId) {
        $query->where('patient_code', $patientId);
    }

    $patients = $query->get();

    if ($patients->isEmpty()) {
        return response()->json(['code' => 0, 'data' => null]);
    }

    return response()->json([
        'code' => 1,
        'data' => $patients->map(function ($p) {
            return [
                'SerialNo'          => (string) $p->patient_code,
                'PatientID'         => (string) $p->patient_code,
                'PatientName'       => $p->name,
                'PatientSex'        => $p->gender === 'Male' ? 'M' : ($p->gender === 'Female' ? 'F' : 'O'),
                'PatientAge'        => (string) $p->age,
                'PatientAgeUnit'    => 'Y',
                'PatientBirthDate'  => '',
                'RequestDepartment' => '',
                'RequestID'         => 'ECG' . $p->patient_code,
                'SickBedNo'         => '',
                'Pacemaker'         => $p->pacemaker === 'Yes' ? 'Yes' : 'No',
                'Source'            => $p->source, // harus: "Inpatient", "Outpatient", atau "Physical Exam"
                'ExamDepartment'    => '',
                'Priority'          => '',
                'fileGuid'          => '',
                'RequestDate'       => $p->created_at->format('Ymd'),
            ];
        }),
    ]);
});
Route::post('/worklist/create', [WorklistController::class, 'create']);
Route::post('/worklist/destroy', [WorklistController::class, 'destroy']);

// Routes ECG_result API
Route::post('/ecg-result', function (Request $request) {
    if (! $request->hasFile('file')) {
        return response()->json(['status' => 'error', 'message' => 'No file uploaded'], 400);
    }

    $pdf = $request->file('file');
    if (! $pdf->isValid() || $pdf->getClientOriginalExtension() !== 'pdf') {
        return response()->json(['status' => 'error', 'message' => 'Invalid file'], 400);
    }

    $patientCode = $request->input('patient_id'); // ← dapat P0001 dari middleware
    $examName    = $request->input('exam_name');
    $studyDate   = $request->input('study_date');

    $patient = Patient::where('patient_code', $patientCode)->first();

    if (! $patient) {
        return response()->json(['status' => 'error', 'message' => 'Patient not found'], 404);
    }

    $filename = 'Aktivo_' . now()->format('Ymd_His') . '_' . Str::slug($examName ?? 'ecg') . '_' . Str::random(6) . '.pdf';
    $path     = $pdf->storeAs('public/ecg-results', $filename);

    EkgResult::create([
        'patient_id'          => $patient->id, // ← pakai integer id, bukan patient_code
        'result_file_path'    => $path,
        'examination_date'    => now(),
        'orthanc_instance_id' => $request->input('orthanc_instance_id'),
    ]);

    // Update isInWorklist to 2 (selesai) after storing the result
    $updated = $patient->update(['isInWorklist' => 2]);
    Log::info('Update isInWorklist to 2 (selesai) for patient ' . $patient->patient_code . ': ' . ($updated ? 'success' : 'failed'));

    // Hapus file .wl
    $wlPattern = "C:\\Orthanc\\Worklists\\{$patient->patient_code}_*.wl";
    $wlFiles   = glob($wlPattern);
    Log::info('WL files found: ' . count($wlFiles ?? []));
    if ($wlFiles) {
        foreach ($wlFiles as $wlFile) {
            unlink($wlFile);
            Log::info("Deleted worklist file: {$wlFile}");
        }
    }

    return response()->json(['status' => 'ok', 'message' => 'Result stored', 'filename' => $filename]);
});



Route::get('/check-new-ekg', function (Request $request) {
    $lastId = $request->query('last_id', 0);

    $newResults = EkgResult::with('patient')
        ->where('id', '>', $lastId)
        ->latest()
        ->get()
        ->map(function($ekg) {
            return [
                'ekg_id'       => $ekg->id,
                'patient_name' => $ekg->patient->name ?? 'Unknown',
                'patient_code' => $ekg->patient->patient_code ?? '-',
                'exam_date'    => $ekg->examination_date,
            ];
        });

    return response()->json([
        'new_results' => $newResults,
        'last_id'     => $newResults->max('ekg_id') ?? $lastId,
    ]);
});






// Routes for areaList //
// Route::post('areaList', function (Request $request) {
//     return response()->json([
//         "result"    => "",
//         "errorCode" => "",
//         "errorText" => "",
//         "data"      => [
//             [
//                 "INPA_AREA_ID"      => "1009",
//                 "INPA_AREA_NAME"    => "Pediatric  Ward",
//                 "INPA_AREA_NAME_EN" => "Pediatric  Ward",
//                 "INPA_AREA_ADDRESS" => "2nd Floor of Building 2",
//                 "ADD_STATE"         => 2,
//             ],
//             [
//                 "INPA_AREA_ID"      => "1010",
//                 "INPA_AREA_NAME"    => "Pediatric  Ward",
//                 "INPA_AREA_NAME_EN" => "Pediatric  Ward",
//                 "INPA_AREA_ADDRESS" => "3rd Floor of Building 2",
//                 "ADD_STATE"         => 2,
//             ],
//         ],
//     ]);
// });

// Route for IoT data upload //
// Route::post('/iotdataupload', function (Request $request) {
//     $devName = $request->query('dev_name');
//     $devMac  = $request->query('dev_macaddr');
//     $data    = $request->all();

//     Log::info('Received IoT data (route):', $data);

//     broadcast(new HeartRateUpdated($data));

//     Log::info('Broadcast called for HeartRateUpdated (route).');

//     return response("OK", 200);
// });
