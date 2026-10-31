<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Patient;
use App\Models\EkgResult;

class PatientSeeder extends Seeder
{
    public function run()
    {
        $patients = [
            ['name' => 'John Doe', 'age' => 45, 'gender' => 'Male', 'pacemaker' => 'No', 'source' => 'Inpatient'],
            ['name' => 'Jane Smith', 'age' => 38, 'gender' => 'Female', 'pacemaker' => 'Yes', 'source' => 'Outpatient'],
            ['name' => 'Bob Johnson', 'age' => 62, 'gender' => 'Male', 'pacemaker' => 'No', 'source' => 'Physical Exam'],
            ['name' => 'Alice Brown', 'age' => 29, 'gender' => 'Female', 'pacemaker' => 'No', 'source' => 'Inpatient'],
            ['name' => 'Charlie Wilson', 'age' => 55, 'gender' => 'Unknown', 'pacemaker' => 'Yes', 'source' => 'Outpatient'],
        ];

        foreach ($patients as $patientData) {
            $patient = Patient::create($patientData);

            // Buat sample EKG result
            EkgResult::create([
                'patient_id' => $patient->id,
                'pdf_file_path' => 'ekg_results/sample_ekg_' . $patient->id . '.pdf',
                'examination_date' => now()->subDays(rand(1, 30)),
            ]);
        }
    }
}
