<?php

use App\Events\HeartRateUpdated;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EcgReportController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\EkgController;
use App\Http\Controllers\JumbotronController;
use App\Http\Controllers\OximonitorController;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;


// Routes for the web application
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Routes for Patients
Route::resource('patients', PatientController::class);

// Routes for Ekg
Route::resource('ekg', EkgController::class);

// Routes for OxiMonitor
Route::resource('oximonitor', OximonitorController::class)->only(['index']);

// Routes for download EKG result
Route::get('/ekg/download/{id}', [EkgController::class, 'download'])->name('ekg.download');

// Routes for Jumbotron
Route::get('/jumbotron/', [JumbotronController::class, 'index'])->name('jumbotron.index');
Route::post('/jumbotron/', [JumbotronController::class, 'store'])->name('jumbotron.save');

// Routes for sending patient data to Worklist Server
Route::post('/send-to-worklist/{id}', function (Request $request, $id) {
    $patient = Patient::find($id);
    if (!$patient) {
        return response()->json(['success' => false, 'message' => 'Pasien tidak ditemukan.'], 404);
    }

    $response = Http::post('http://localhost:8081/create-worklist', [  // ← fix port + endpoint
        'patient_id'   => $patient->patient_code,
        'patient_name' => $patient->name,
        'gender'       => $patient->gender,
        'age'          => $patient->age,
        'modality'     => 'ECG',
        'study_date'   => now()->format('Ymd'),
    ]);

    if ($response->successful()) {
        $patient->isInWorklist = 1;
        $patient->save();
        return response()->json(['success' => true]);
    } else {
        return response()->json([
            'success' => false,
            'message' => 'Gagal mengirim ke Worklist Server.',
            'error'   => $response->body()
        ], $response->status());
    }
});

// Routes test-heart
Route::get('/test-heart', function () {
    broadcast(new HeartRateUpdated(rand(60, 100)));
    return "broadcast sent";
});