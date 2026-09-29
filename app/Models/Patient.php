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
            $month  = now()->format('m');
            $year   = now()->format('Y');
            $suffix = $month . '-' . $year;

            $lastCode = Patient::withTrashed()
                ->where('patient_code', 'like', "%-{$suffix}")
                ->orderByRaw("CAST(SUBSTRING_INDEX(patient_code, '-', 1) AS UNSIGNED) DESC")
                ->value('patient_code');

            $lastNumber = $lastCode ? (int) explode('-', $lastCode)[0] : 0;
            $number = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);

            $patient->patient_code = $number . '-' . $suffix;
        });
    }

    public function ekgResults()
    {
        return $this->hasMany(EkgResult::class);
    }
}
