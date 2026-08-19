<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WorklistController extends Controller
{
    public function create(Request $request)
    {
        $patient = Patient::findOrFail($request->patient_id);

        $response = Http::post('http://127.0.0.1:8081/create-worklist', [
            'patient_id'   => $patient->id,
            'patient_name' => $patient->name,
            'age'          => $patient->age,
            'gender'       => $patient->gender,
            'modality'     => 'ECG',
            'study_date'   => now()->format('Ymd'),
        ]);

        if ($response->successful()) {
            $patient->update(['isInWorklist' => 1]);
            return response()->json([
                'status'  => 'ok',
                'message' => 'Pasien berhasil ditambahkan ke worklist'
            ]);
        }

        return response()->json([
            'status'  => 'error',
            'message' => 'Gagal membuat worklist'
        ], 500);
    }

    public function destroy(Request $request)
    {
        $patient = Patient::findOrFail($request->patient_id);
        $patient->update(['isInWorklist' => 0]);

        return response()->json([
            'status'  => 'ok',
            'message' => 'Pasien dihapus dari worklist'
        ]);
    }
}