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

Route::get('result', function (Request $request) {
    $perPage = $request->query('per_page', 20);

    $results = EkgResult::with('patient')
        ->latest()
        ->paginate($perPage);

    return response()->json([
        'status' => 'ok',
        'data'   => collect($results->items())->map(function ($ekg) {
            return [
                'id'               => $ekg->id,
                'patient_code'     => $ekg->patient->patient_code ?? 'unknown',
                'patient_name'     => $ekg->patient->name ?? 'unknown',
                'gender'           => $ekg->patient->gender ?? null,
                'age'              => $ekg->patient->age ?? null,
                'result_file_type' => $ekg->result_file_type,
                'input_source'     => $ekg->input_source,
                'examination_date' => $ekg->examination_date->format('Y-m-d H:i:s'),
                'pdf_url'          => $ekg->result_file_path
                    ? url("/ekg/download/{$ekg->id}/pdf")
                    : null,
                'xml_url'          => $ekg->xml_file_path
                    ? url("/ekg/download/{$ekg->id}/xml")
                    : null,
                'dat_url'          => $ekg->dat_file_path
                    ? url("/ekg/download/{$ekg->id}/dat")
                    : null,
            ];
        })->values()->all(),
        'pagination' => [
            'current_page' => $results->currentPage(),
            'last_page'    => $results->lastPage(),
            'total'        => $results->total(),
        ],
    ]);
});

Route::post('/ecg-result', function (Request $request) {
    if (! $request->hasFile('file')) {
        return response()->json(['status' => 'error', 'message' => 'No file uploaded'], 400);
    }

    $uploadedFile      = $request->file('file');
    $extension         = strtolower($uploadedFile->getClientOriginalExtension());
    $allowedExtensions = ['pdf', 'xml', 'dat'];

    if (! $uploadedFile->isValid() || ! in_array($extension, $allowedExtensions)) {
        return response()->json(['status' => 'error', 'message' => 'Invalid file type.'], 400);
    }

    $patientCode = $request->input('patient_id');
    if (! $patientCode) {
        return response()->json(['status' => 'error', 'message' => 'patient_id is required'], 400);
    }

    $examName = $request->input('exam_name');
    $fileType = $request->input('file_type', strtoupper($extension));

    $patientCode = $request->input('patient_code', $patientCode);

    $patient = Patient::where('patient_code', $patientCode)->first();

    if (! $patient) {
        $genderMap = ['M' => 'Male', 'F' => 'Female'];
        $rawSex    = strtoupper($request->input('patient_sex', ''));

        $patient = Patient::create([
            'patient_code' => $patientCode,
            'name'         => $request->input('patient_name', 'Unknown'),
            'gender'       => $genderMap[$rawSex] ?? 'Unknown',
            'age'          => $request->input('patient_age') ?: 0,
            'pacemaker'    => 'No',
            'isInWorklist' => 2,
            'source'       => 'Outpatient',
        ]);

        Log::info("Auto-registered new patient from manual EKG entry: {$patientCode}");
    }

    $filename = 'Aktivo_' . now()->format('Ymd_His') . '_' . Str::slug($examName ?? 'ecg') . '_' . Str::random(6) . '.' . $extension;
    $path     = $uploadedFile->storeAs('public/ecg-results', $filename);

    $columnMap = [
        'pdf' => 'result_file_path',
        'xml' => 'xml_file_path',
        'dat' => 'dat_file_path',
    ];
    $targetColumn = $columnMap[$extension];

    $recentWindow = now()->subSeconds(60);

    $existing = EkgResult::where('patient_id', $patient->id)
        ->where('created_at', '>=', $recentWindow)
        ->whereNull($targetColumn)
        ->latest()
        ->first();

    if ($existing) {
        $updateData = [$targetColumn => $path];
        if ($extension === 'pdf') {
            $updateData['result_file_type'] = $fileType;
        }
        $existing->update($updateData);
        $ekgResult = $existing;
    } else {
        $data = [
            'patient_id'          => $patient->id,
            'examination_date'    => now(),
            'orthanc_instance_id' => $request->input('orthanc_instance_id'),
            $targetColumn         => $path,
        ];
        if ($extension === 'pdf') {
            $data['result_file_type'] = $fileType;
        }
        $ekgResult = EkgResult::create($data);
    }

    $updated = $patient->update(['isInWorklist' => 2]);
    Log::info('Update isInWorklist to 2 (selesai) for patient ' . $patient->patient_code . ': ' . ($updated ? 'success' : 'failed'));

    return response()->json(['status' => 'ok', 'message' => 'Result stored', 'filename' => $filename, 'ekg_id' => $ekgResult->id]);
});

Route::get('/check-new-ekg', function (Request $request) {
    $lastId = $request->query('last_id', 0);

    $newResults = EkgResult::with('patient')
        ->where('id', '>', $lastId)
        ->latest()
        ->get()
        ->map(function ($ekg) {
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
