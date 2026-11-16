<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PatientController extends Controller
{

    public function index(Request $request)
    {
        $query = Patient::query();

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('patient_code', 'LIKE', "%{$search}%");
            });
        }

        $patients = $query->latest()->paginate(10)->appends(request()->query());

        return view('patients.index', compact('patients'));
    }

    public function create()
    {
        return view('patients.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'age' => 'required_without:birth_date|nullable|integer|min:0|max:120',
            'birth_date' => 'nullable|date|before:today',
            'gender' => 'required|in:Male,Female,Unknown',
            'pacemaker' => 'required|in:Yes,No',
            'source' => 'required|in:Inpatient,Outpatient,Physical Exam'
        ]);

        Patient::create([
            'name' => $request['name'],
            'age' => $request->filled('birth_date')
                ? Carbon::parse($request['birth_date'])->age
                : $request['age'],
            'birth_date' => $request['birth_date'],
            'gender' => $request['gender'],
            'pacemaker' => $request['pacemaker'],
            'source' => $request['source'],
            'isInWorklist' => 1,
        ]);
        return redirect()->route('patients.index')->with('success', 'Patient created successfully');
    }

    public function show(Patient $patient)
    {
        return view('patients.show', compact('patient'));
    }

    public function edit(Patient $patient)
    {
        $patient = Patient::findOrFail($patient->id);
        return view('patients.edit', compact('patient'));
    }

    public function update(Request $request, Patient $patient)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'age' => 'required|integer|min:1|max:120',
            'gender' => 'required|in:Male,Female,Unknown',
            'pacemaker' => 'required|in:Yes,No',
            'source' => 'required|in:Inpatient,Outpatient,Physical Exam'
        ]);

        $patient->update($request->all());
        return redirect()->route('patients.index')->with('success', 'Patient updated successfully');
    }

    public function destroy(Patient $patient)
    {
        $patient->delete();
        return redirect()->route('patients.index')->with('success', 'Patient deleted successfully');
    }
}
