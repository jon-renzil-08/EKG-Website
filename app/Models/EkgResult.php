<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EkgResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'pdf_file_path',
        'result_file_type',
        'xml_file_path',
        'dat_file_path',
        'examination_date',
        'orthanc_instance_id',
        'device_no'
    ];

    protected $casts = [
        'examination_date' => 'datetime'
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    protected static function booted()
    {
        static::deleting(function ($ekg) {
            if (Storage::exists($ekg->pdf_file_path)) {
                Storage::delete($ekg->pdf_file_path);
                Log::info('Deleted EKG file: ' . $ekg->pdf_file_path);
            }
        });
    }
}
