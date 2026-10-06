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
            if (! empty($patient->patient_code)) {
                return;
            }

            $prefix = 'KSM-';

            $lastCode = Patient::withTrashed()
                ->where('patient_code', 'like', $prefix . '%')
                ->orderByRaw(
                    'CAST(SUBSTRING(patient_code, ' . (strlen($prefix) + 1) . ') AS UNSIGNED) DESC'
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
