<?php

use App\Http\Controllers\Api\WorklistController;
use App\Http\Controllers\EkgQueryController;
use App\Http\Controllers\PatientController;
use App\Models\EkgResult;
use App\Models\Patient;
use App\Models\SimrsResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
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

Route::middleware('auth:sanctum')->group(function () {

});

Route::middleware('no.browser')->group(function () {

    // GET Patients API
    Route::get('/patients', function () {
        $patients = Patient::latest()->get();

        return response()->json([
            'success' => true,
            'message' => 'Data pasien berhasil diambil.',
            'data' => $patients,
        ]);
    });

    // GET Worklist API
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
                    'PatientID' => (string) $p->patient_code,
                    'PatientName' => $p->name,
                    'PatientSex' => $p->gender === 'Male' ? 'M' : ($p->gender === 'Female' ? 'F' : 'O'),
                    'PatientAge' => (string) $p->age,
                    'Pacemaker' => $p->pacemaker === 'Yes' ? 'Yes' : 'No',
                    'Source' => $p->source, // harus: "Inpatient", "Outpatient", atau "Physical Exam"
                    'RequestDate' => $p->created_at ? $p->created_at->format('Y-m-d H:i:s') : null,
                ];
            }),
        ]);
    });

    // GET ECG Result API
    Route::get('/result', function (Request $request) {
        $results = EkgResult::with('patient')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'ok',
            'data' => $results->map(function ($ekg) {
                return [
                    'id' => $ekg->id,
                    'device_no' => $ekg->device_no,
                    'patient_code' => $ekg->patient->patient_code ?? 'unknown',
                    'patient_name' => $ekg->patient->name ?? 'unknown',
                    'gender' => $ekg->patient->gender ?? null,
                    'age' => $ekg->patient->age ?? null,
                    'pacemaker' => $ekg->patient->pacemaker ?? null,
                    'source' => $ekg->patient->source ?? null,
                    'result_file_type' => $ekg->result_file_type,
                    'examination_date' => $ekg->examination_date
                        ? $ekg->examination_date->format('Y-m-d H:i:s')
                        : null,
                    'pdf_url' => $ekg->result_file_path
                        ? url("/ekg/download/{$ekg->id}/pdf")
                        : null,
                    'xml_url' => $ekg->xml_file_path
                        ? url("/ekg/download/{$ekg->id}/xml")
                        : null,
                    'dat_url' => $ekg->dat_file_path
                        ? url("/ekg/download/{$ekg->id}/dat")
                        : null,
                ];
            }),
        ]);
    });

    // GET Check New EKG
    Route::get('/check-new-ekg', function (Request $request) {
        $lastId = $request->query('last_id', 0);

        $newResults = EkgResult::with('patient')
            ->where('id', '>', $lastId)
            ->latest()
            ->get()
            ->map(function ($ekg) {
                return [
                    'ekg_id' => $ekg->id,
                    'patient_name' => $ekg->patient->name ?? 'Unknown',
                    'patient_code' => $ekg->patient->patient_code ?? '-',
                    'exam_date' => $ekg->examination_date,
                ];
            });

        return response()->json([
            'new_results' => $newResults,
            'last_id' => $newResults->max('ekg_id') ?? $lastId,
        ]);
    });

});


// Routes for create Patient API
Route::post('/patients', function (Request $request) {
    $validated = $request->validate([
        'patient_code' => 'nullable|string|max:255|unique:patients,patient_code',
        'name' => 'required|string|max:255',
        'age' => 'required|integer|min:1|max:120',
        'gender' => 'required|in:Male,Female,Unknown',
        'pacemaker' => 'required|in:Yes,No',
        'source' => 'required|in:Inpatient,Outpatient,Physical Exam',
    ]);

    $patient = Patient::create([
        'patient_code' => $validated['patient_code'] ?? null,   // kosong → dibuat otomatis P0001...
        'name' => $validated['name'],
        'age' => $validated['age'],
        'gender' => $validated['gender'],
        'pacemaker' => $validated['pacemaker'],
        'source' => $validated['source'],
        'isInWorklist' => 1,   // otomatis masuk worklist
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Pasien berhasil ditambahkan.',
        'data' => $patient,
    ], 201);
});


// Route to handle file uploads for ECG results
Route::post('/ecg-result', function (Request $request) {
    if (!$request->hasFile('file')) {
        return response()->json(['status' => 'error', 'message' => 'No file uploaded'], 400);
    }

    $uploadedFile = $request->file('file');
    $extension = strtolower($uploadedFile->getClientOriginalExtension());
    $allowedExtensions = ['pdf', 'xml', 'dat'];

    if (!$uploadedFile->isValid() || !in_array($extension, $allowedExtensions)) {
        return response()->json(['status' => 'error', 'message' => 'Invalid file type.'], 400);
    }

    $patientCode = $request->input('patient_id');
    if (!$patientCode) {
        return response()->json(['status' => 'error', 'message' => 'patient_id is required'], 400);
    }

    $examName = $request->input('exam_name');

    $deviceNo = $request->input('device_no');

    $patientCode = $request->input('patient_code', $patientCode);

    $patient = Patient::where('patient_code', $patientCode)->first();

    if (!$patient) {
        $genderMap = ['M' => 'Male', 'F' => 'Female'];
        $rawSex = strtoupper($request->input('patient_sex', ''));

        $patient = Patient::create([
            'patient_code' => $patientCode,
            'name' => $request->input('patient_name', 'Unknown'),
            'gender' => $genderMap[$rawSex] ?? 'Unknown',
            'age' => $request->input('patient_age') ?: 0,
            'pacemaker' => 'No',
            'isInWorklist' => 1,
            'source' => 'Outpatient',
        ]);

        Log::info("Auto-registered new patient from manual EKG entry: {$patientCode}");
    }

    $filename = 'Hasil_EKG_' . now()->format('Ymd_His') . '_' . Str::slug($examName ?? 'ecg') . '_' . Str::random(6) . '.' . $extension;
    $path = $uploadedFile->storeAs('public/ecg-results-middleware', $filename);

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
        // isi device_no kalau masih kosong dan request ini membawan device_no
        if ($deviceNo && !$existing->device_no) {
            $updateData['device_no'] = $deviceNo;
        }
        $existing->update($updateData);
        $ekgResult = $existing;
    } else {
        $ekgResult = EkgResult::create([
            'patient_id' => $patient->id,
            'examination_date' => now(),
            'orthanc_instance_id' => $request->input('orthanc_instance_id'),
            'device_no' => $deviceNo,
            $targetColumn => $path,
        ]);
    }

    // result_file_type = daftar format yang ada di record ini, mis. "PDF", "PDF,XML", "PDF,XML,DAT".
    // Dihitung ulang tiap ada file masuk, karena PDF/XML/DAT dari 1 pemeriksaan digabung di 1 row.
    $ekgResult->refresh();
    $ekgResult->update([
        'result_file_type' => collect([
            $ekgResult->result_file_path ? 'PDF' : null,
            $ekgResult->xml_file_path ? 'XML' : null,
            $ekgResult->dat_file_path ? 'DAT' : null,
        ])->filter()->implode(','),
    ]);

    $updated = $patient->update(['isInWorklist' => 2]);
    Log::info('Update isInWorklist to 2 (selesai) for patient ' . $patient->patient_code . ': ' . ($updated ? 'success' : 'failed'));

    return response()->json(['status' => 'ok', 'message' => 'Result stored', 'filename' => $filename, 'ekg_id' => $ekgResult->id]);
});


// Route to handle file upload for SIMRS
Route::post('/send-to-simrs', function (Request $request) {
    $validated = $request->validate([
        'patient_code' => 'required|string|max:255',
        'file' => 'required|file',
        'device_no' => 'nullable|string|max:255',
    ]);

    $uploadedFile = $request->file('file');
    $extension = strtolower($uploadedFile->getClientOriginalExtension());

    if (!in_array($extension, ['pdf', 'xml', 'dat'])) {
        return response()->json(['status' => 'error', 'message' => 'Invalid file type.'], 400);
    }

    $patientCode = $validated['patient_code'];
    $deviceNo = $validated['device_no'] ?? null;

    $filename = 'Hasil_EKG_' . now()->format('Ymd_His') . '_' . Str::slug('ecg') . '_' . Str::random(6) . '.' . $extension;
    $path = $uploadedFile->storeAs('public/ecg-results-simrs', $filename);

    $columnMap = [
        'pdf' => 'result_file_path',
        'xml' => 'xml_file_path',
        'dat' => 'dat_file_path',
    ];
    $targetColumn = $columnMap[$extension];

    // PDF/XML/DAT dari pasien yang sama dalam 60 detik digabung di satu record
    $existing = SimrsResult::where('patient_code', $patientCode)
        ->where('created_at', '>=', now()->subSeconds(60))
        ->whereNull($targetColumn)
        ->latest()
        ->first();

    if ($existing) {
        $updateData = [$targetColumn => $path];
        if ($deviceNo && !$existing->device_no) {
            $updateData['device_no'] = $deviceNo;
        }
        $existing->update($updateData);
        $record = $existing;
    } else {
        $record = SimrsResult::create([
            'patient_code' => $patientCode,
            'device_no' => $deviceNo,
            'examination_date' => now(),
            $targetColumn => $path,
        ]);
    }

    $record->refresh();
    $record->update([
        'result_file_type' => collect([
            $record->result_file_path ? 'PDF' : null,
            $record->xml_file_path ? 'XML' : null,
            $record->dat_file_path ? 'DAT' : null,
        ])->filter()->implode(','),
    ]);

    return response()->json([
        'status' => 'ok',
        'message' => 'Result stored',
        'filename' => $filename,
        'ekg_id' => $record->id,
    ]);
});


// Route Dawei EKG Direct Query API
Route::post('/query', [EkgQueryController::class, 'query']);





