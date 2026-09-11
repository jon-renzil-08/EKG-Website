<?php

namespace App\Http\Controllers;

use App\Models\EkgResult;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EkgController extends Controller
{
    public function index(Request $request)
    {
        $query = EkgResult::with('patient');

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('patient_code', 'LIKE', "%{$search}%");
            });
        }

        $ekgResults = $query->latest()->paginate(10)->appends(request()->query());

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

    // Di EkgController
public function download($instanceId)
{
    $response = Http::get(
        "http://127.0.0.1:8042/instances/{$instanceId}/pdf"
    );

    if (!$response->successful()) {
        abort(404, 'PDF tidak ditemukan di Orthanc.');
    }

    return response($response->body(), 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="hasil_ekg.pdf"',
    ]);
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
}
