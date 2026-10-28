<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ekg_results', function (Blueprint $table) {
            $table->string('device_no')->nullable()->after('orthanc_instance_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ekg_results', function (Blueprint $table) {
            $table->dropColumn('device_no');
        });
    }
};
