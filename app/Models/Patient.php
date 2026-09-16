<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Patient extends Model
{
    use HasFactory;

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
        // Auto generate patient code
        static::creating(function ($patient) {
            // Ambil nomor urut terakhir
            $last   = Patient::max('id') ?? 0;
            $number = str_pad($last + 1, 5, '0', STR_PAD_LEFT);

            // Format: 00001-MM-YYYY
            $month = now()->format('m');
            $year  = now()->format('Y');

            $patient->patient_code = $number . '-' . $month . '-' . $year;
        });

        // Auto delete file PDF

        static::deleting(function ($patient) {

            foreach ($patient->ekgResults as $ekg) {
                if (Storage::exists($ekg->result_file_path)) {
                    Storage::delete($ekg->result_file_path);
                    Log::info('Deleted EKG file: ' . $ekg->result_file_path);
                }
            }
        });
    }

    public function ekgResults()
    {
        return $this->hasMany(EkgResult::class);
    }
}
