<?php

use App\Events\HeartRateUpdated;
use App\Http\Controllers\Api\WorklistController;
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

Route::get('/worklist', [WorklistController::class, 'query']);

Route::post('/ecg-result', function (Request $request) {
    if (!$request->hasFile('file')) {
        return response()->json(['status' => 'error', 'message' => 'No file uploaded'], 400);
    }

    $pdf = $request->file('file');

    if (!$pdf->isValid() || $pdf->getClientOriginalExtension() !== 'pdf') {
        return response()->json(['status' => 'error', 'message' => 'Invalid file'], 400);
    }

    $patientId = $request->input('patient_id');
    $examName = $request->input('exam_name');
    $studyDate = $request->input('study_date');

    $filename = now()->format('Ymd_His') . '' . Str::slug($examName ?? 'ecg') . '' . Str::random(6) . '.pdf';
    $path = $pdf->storeAs('public/ecg-results', $filename);

    EkgResult::create([
        'patient_id' => $patientId,
        'result_file_path' => $path,
        'examination_date' => $studyDate ? Carbon::parse($studyDate) : now(),
    ]);

    return response()->json(['status' => 'ok', 'message' => 'Result stored', 'filename' => $filename]);
});

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

Route::post('/iotdataupload', function (Request $request) {
    $devName = $request->query('dev_name');
    $devMac  = $request->query('dev_macaddr');
    $data    = $request->all();

    Log::info('Received IoT data (route):', $data);

    broadcast(new HeartRateUpdated($data));

    Log::info('Broadcast called for HeartRateUpdated (route).');

    return response("OK", 200);
});
