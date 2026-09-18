<?php

use App\Events\HeartRateUpdated;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EcgReportController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\EkgController;
use App\Http\Controllers\JumbotronController;
use App\Http\Controllers\OximonitorController;
use App\Http\Controllers\LoginController;
use App\Models\EkgResult;
use App\Models\Patient;
use App\Services\EcgPdfEnhancer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;


// Authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');


// Routes for the web application
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// Routes for Patients
Route::resource('patients', PatientController::class);

// Routes for Ekg
Route::resource('ekg', EkgController::class);

// Routes for OxiMonitor
Route::resource('oximonitor', OximonitorController::class)->only(['index']);

// Routes for download EKG result
// Route::get('/ekg/download/{id}', [EkgController::class, 'download'])->name('ekg.download');



Route::get('/ekg/download/{id}', function($id) {
    $ekg = EkgResult::findOrFail($id);

    $pdfPath = storage_path('app/' . $ekg->result_file_path);

    if (!file_exists($pdfPath)) {
        abort(404, 'File tidak ditemukan.');
    }

    // Ambil data pasien
    $patient = Patient::find($ekg->patient_id);

    // Format nama file: AKTIVO_EKG_P0001_Johni_Revormasi_Ziliwu.pdf
    $patientId   = $patient ? $patient->patient_code : 'unknown';
    $patientName = $patient ? str_replace(' ', '_', $patient->name) : 'unknown';
    $fileName    = "AKTIVO_EKG_{$patientId}_{$patientName}.pdf";

    // Enhance PDF
    $enhancer    = new EcgPdfEnhancer();
    $enhancedPath = $enhancer->enhance($pdfPath);

    return response()->download(
        $enhancedPath,
        $fileName,
        ['Content-Type' => 'application/pdf']
    )->deleteFileAfterSend(true);

})->name('ekg.download');

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
});