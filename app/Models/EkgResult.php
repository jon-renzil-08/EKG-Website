<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EkgResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'result_file_path',
        'examination_date'
    ];

    protected $casts = [
        'examination_date' => 'datetime'
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    protected static function booted()
    {
        static::deleting(function ($ekg) {
            if (Storage::exists($ekg->result_file_path)) {
                Storage::delete($ekg->result_file_path);
                \Log::info('Deleted EKG file: ' . $ekg->result_file_path);
            }
        });
    }
}