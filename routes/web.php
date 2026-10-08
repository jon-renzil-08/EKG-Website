<?php

use App\Events\HeartRateUpdated;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EcgReportController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\EkgController;
use App\Http\Controllers\EkgQueryController;
use App\Http\Controllers\EkgReceiveController;
use App\Http\Controllers\JumbotronController;
use App\Http\Controllers\OximonitorController;
use App\Http\Controllers\LoginController;
use App\Models\EkgResult;
use App\Models\Patient;
use App\Services\EcgPdfEnhancer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;


Route::get('/', [AuthController::class, 'showLogin'])
    ->name('login');

Route::post('/login', [AuthController::class, 'login'])
    ->name('login.process');


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



    Route::get('/ekg/download/{id}/{type}', function($id, $type) {
        $ekg = EkgResult::findOrFail($id);

        $type = strtolower($type);

        $pathMap = [
            'pdf' => $ekg->result_file_path,
            'xml' => $ekg->xml_file_path,
            'dat' => $ekg->dat_file_path,
        ];

        if (!isset($pathMap[$type])) {
            abort(404, 'Tipe file tidak dikenali.');
        }

        $relativePath = $pathMap[$type];
        $filePath = $relativePath ? storage_path('app/' . $relativePath) : null;

        if (!$filePath || !file_exists($filePath)) {
            abort(404, 'File tidak ditemukan.');
        }

        $patient = Patient::withTrashed()->find($ekg->patient_id);
        $patientId   = $patient ? $patient->patient_code : 'unknown';
        $patientName = $patient ? str_replace(' ', '_', $patient->name) : 'unknown';

        if ($type === 'xml') {
            return response()->download($filePath, "Klinik_Shanata_{$patientName}_{$patientId}.xml", [
                'Content-Type' => 'application/xml'
            ]);
        }

        if ($type === 'dat') {
            return response()->download($filePath, "Klinik_Shanata_{$patientName}_{$patientId}.dat", [
                'Content-Type' => 'application/octet-stream'
            ]);
        }

        // PDF (default)
        $enhancer     = new EcgPdfEnhancer();
        $enhancedPath = $enhancer->enhance($filePath);

        return response()->download(
            $enhancedPath,
            "Klinik_Shanata_{$patientName}_{$patientId}.pdf",
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
          return response()->json([
		'success' => false,
		'message' => 'Pasien tidak ditemukan.'
	  ], 404);
        }

        $patient->isInWorklist = 1;
        $patient->save();


        return response()->json([
              'success' => true,
              'message' => 'Pasien berhasil ditambahkan ke worklist.',

        ]);
    });




   // Routes test-heart
   Route::get('/test-heart', function () {
      broadcast(new HeartRateUpdated(rand(60, 100)));
      return "broadcast sent";
   });

});


Route::post('/query', [EkgQueryController::class, 'query']);
Route::post('/receive', [EkgReceiveController::class, 'receive']);


