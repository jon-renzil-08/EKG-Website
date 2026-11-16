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
        'birth_date',
        'height',
        'gender',
        'pacemaker',
        'source',
        'patient_code',
        'isInWorklist',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    // Nomor Rekam Medis Untuk Rumah Sakit Keluarga Sehat Pati
    // protected static function booted()
    // {
    //     static::creating(function ($patient) {
    //         // Otomatis masuk worklist kalau status belum ditentukan
    //         if ($patient->isInWorklist === null) {
    //             $patient->isInWorklist = 1;
    //         }

    //         // Kode sudah diisi (mis. dari alat EKG atau SIMRS): jangan diubah
    //         if (!empty($patient->patient_code)) {
    //             return;
    //         }

    //         // Format: "YY NNNNNN" -> 2 digit tahun, spasi, 6 digit nomor urut
    //         $yearPrefix = now()->format('y');   // contoh: "26"

    //         $lastCode = Patient::withTrashed()
    //             ->whereRaw('patient_code REGEXP ?', ['^' . $yearPrefix . ' [0-9]{6}$'])
    //             ->orderByDesc('patient_code')
    //             ->value('patient_code');

    //         // "26 000123" -> ambil angka setelah "26 " (mulai karakter ke-4)
    //         $lastNumber = $lastCode
    //             ? (int) substr($lastCode, 3)
    //             : 0;

    //         $patient->patient_code = $yearPrefix . ' ' . str_pad(
    //             $lastNumber + 1,
    //             6,
    //             '0',
    //             STR_PAD_LEFT
    //         );
    //     });
    // }



    // Nomor Rekam Medis untuk Rumah Sakit Keluarga Sehat Rembang
    // protected static function booted()
    // {
    //     static::creating(function ($patient) {
    //         // Otomatis masuk worklist kalau status belum ditentukan
    //         if ($patient->isInWorklist === null) {
    //             $patient->isInWorklist = 1;
    //         }

    //         // Kode sudah diisi (mis. dari alat EKG atau SIMRS): jangan diubah
    //         if (!empty($patient->patient_code)) {
    //             return;
    //         }

    //         // Format: "YYNNNNNNNN" -> 2 digit tahun + 8 digit nomor urut (10 digit, tanpa spasi)
    //         $yearPrefix = now()->format('y');   // contoh: "26"

    //         $lastCode = Patient::withTrashed()
    //             ->whereRaw('patient_code REGEXP ?', ['^' . $yearPrefix . '[0-9]{8}$'])
    //             ->orderByDesc('patient_code')
    //             ->value('patient_code');

    //         // "2600000123" -> ambil angka setelah "26" (mulai karakter ke-3)
    //         $lastNumber = $lastCode
    //             ? (int) substr($lastCode, 2)
    //             : 0;

    //         $patient->patient_code = $yearPrefix . str_pad(
    //             $lastNumber + 1,
    //             8,
    //             '0',
    //             STR_PAD_LEFT
    //         );
    //     });
    // }

    // Nomor Rekam Medis untuk Rumah Sakit Karitas Weetabula
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

            // Format: 6 digit angka (contoh: 000001), mengikuti format SIMRS
            $lastCode = Patient::withTrashed()
                ->whereRaw('patient_code REGEXP ?', ['^[0-9]{6}$'])
                ->orderByDesc('patient_code')
                ->value('patient_code');

            $lastNumber = $lastCode ? (int) $lastCode : 0;

            $patient->patient_code = str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
        });
    }

    public function ekgResults()
    {
        return $this->hasMany(EkgResult::class);
    }
}
