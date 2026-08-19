<?php

use App\Events\HeartRateUpdated;
use App\Http\Controllers\Api\WorklistController;
use App\Http\Controllers\EkgController;
use App\Models\EkgResult;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
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
        'data' => $patients->map(function($p) {
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
                'Source'            => $p->source,  // harus: "Inpatient", "Outpatient", atau "Physical Exam"
                'ExamDepartment'    => '',
                'Priority'          => '',
                'fileGuid'          => '',
                'RequestDate'       => $p->created_at->format('Ymd'),
            ];
        })
    ]);
});
Route::post('/worklist/create', [WorklistController::class, 'create']);
Route::post('/worklist/destroy', [WorklistController::class, 'destroy']);


// Routes ECG_result API
Route::post('/ecg-result', function (Request $request) {
    if (!$request->hasFile('file')) {
        return response()->json(['status' => 'error', 'message' => 'No file uploaded'], 400);
    }

    $pdf = $request->file('file');
    if (!$pdf->isValid() || $pdf->getClientOriginalExtension() !== 'pdf') {
        return response()->json(['status' => 'error', 'message' => 'Invalid file'], 400);
    }

    $patientCode = $request->input('patient_id'); // ← dapat P0001 dari middleware
    $examName    = $request->input('exam_name');
    $studyDate   = $request->input('study_date');

   
    $patient = Patient::where('patient_code', $patientCode)->first();

    if (!$patient) {
        return response()->json(['status' => 'error', 'message' => 'Patient not found'], 404);
    }

    $filename = now()->format('Ymd_His') . Str::slug($examName ?? 'ecg') . Str::random(6) . '.pdf';
    $path = $pdf->storeAs('public/ecg-results', $filename);

    EkgResult::create([
        'patient_id'       => $patient->id,  // ← pakai integer id, bukan patient_code
        'result_file_path' => $path,
        'examination_date' => now(),
    ]);

    return response()->json(['status' => 'ok', 'message' => 'Result stored', 'filename' => $filename]);
});

// Routes for areaList 
Route::post('areaList', function (Request $request) {
    return response()->json([
        "result" => "",
        "errorCode" => "",
        "errorText" => "",
        "data" => [
            [
                "INPA_AREA_ID" =>  "1009",
                "INPA_AREA_NAME" =>  "Pediatric  Ward",
                "INPA_AREA_NAME_EN" =>  "Pediatric  Ward",
                "INPA_AREA_ADDRESS" =>  "2nd Floor of Building 2",
                "ADD_STATE" =>  2
            ],
            [
                "INPA_AREA_ID" =>  "1010",
                "INPA_AREA_NAME" =>  "Pediatric  Ward",
                "INPA_AREA_NAME_EN" =>  "Pediatric  Ward",
                "INPA_AREA_ADDRESS" =>  "3rd Floor of Building 2",
                "ADD_STATE" =>  2
            ]
        ]
    ]);
});

// Route for IoT data upload
Route::post('/iotdataupload', function (Request $request) {
    $devName = $request->query('dev_name');
    $devMac  = $request->query('dev_macaddr');
    $data    = $request->all();

    Log::info('Received IoT data (route):', $data);

    broadcast(new HeartRateUpdated($data));

    Log::info('Broadcast called for HeartRateUpdated (route).');

    return response("OK", 200);
});