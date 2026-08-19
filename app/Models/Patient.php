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
        'gender',
        'pacemaker',
        'source'
    ];




    protected static function booted()
    {
        // Auto generate patient code

        static::creating(function ($patient) {
            $last = Patient::max('id') ?? 0;
            $patient->patient_code = 'P' . str_pad($last + 1, 4, '0', STR_PAD_LEFT);
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