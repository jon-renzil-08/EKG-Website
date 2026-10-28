<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('simrs_results', function (Blueprint $table) {
            $table->id();
            $table->string('patient_code')->index();
            $table->string('device_no')->nullable();
            $table->string('result_file_path')->nullable();
            $table->string('xml_file_path')->nullable();
            $table->string('dat_file_path')->nullable();
            $table->string('result_file_type')->nullable();
            $table->timestamp('examination_date')->nullable();
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simrs_results');
    }
};
