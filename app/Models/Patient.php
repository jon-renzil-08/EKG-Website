<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'age',
        'height',
        'gender',
        'pacemaker',
        'source',
        'patient_code',
        'isInWorklist',
    ];

    protected static function booted()
    {
        static::creating(function ($patient) {
            // Otomatis masuk worklist kalau status belum ditentukan
            if ($patient->isInWorklist === null) {
                $patient->isInWorklist = 1;
            }

            // Kode sudah diisi (mis. dari alat EKG atau SIMRS): jangan diubah
            if (!empty($patient->patient_code)) {
                return;
            }

            $prefix = 'P';

            $lastCode = Patient::withTrashed()
                ->whereRaw('patient_code REGEXP ?', ['^' . $prefix . '[0-9]+$'])
                ->orderByRaw(
                    'CAST(SUBSTRING(patient_code, ?) AS UNSIGNED) DESC',
                    [strlen($prefix) + 1]
                )
                ->value('patient_code');

            $lastNumber = $lastCode
                ? (int) substr($lastCode, strlen($prefix))
                : 0;

            $patient->patient_code = $prefix . str_pad(
                $lastNumber + 1,
                4,
                '0',
                STR_PAD_LEFT
            );
        });
    }

    public function ekgResults()
    {
        return $this->hasMany(EkgResult::class);
    }
}
