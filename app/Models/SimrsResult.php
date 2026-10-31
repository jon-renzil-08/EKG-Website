<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimrsResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_code',
        'device_no',
        'pdf_file_path',
        'xml_file_path',
        'dat_file_path',
        'result_file_type',
        'examination_date',
    ];

    protected $casts = [
        'examination_date' => 'datetime',
    ];
}
