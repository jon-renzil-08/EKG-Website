<?php

namespace Tests\Feature;

use App\Models\EkgResult;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EkgReceiveTest extends TestCase
{
    use RefreshDatabase;

    private const GUID = '0000001C-0078-00EB-B443-1E4058929176';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Cache::flush();
    }

    // ---------- helper ----------

    private function upload(string $filename, string $body, string $contentType = 'application/octet-stream')
    {
        return $this->call('POST', '/api/receive', [], [], [], [
            'CONTENT_TYPE'  => $contentType,
            'HTTP_FILENAME' => $filename,
        ], $body);
    }

    private function xmlBody(string $patientId, string $name = 'Siti', string $sex = 'F'): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><root><PatientInfo>'
            . "<PatientID>{$patientId}</PatientID><Name>{$name}</Name><Sex>{$sex}</Sex>"
            . '</PatientInfo></root>';
    }

    /** Isi DAT minimal: token dipisah byte \x01 (NUL dibuang oleh parser, jadi jangan dipakai). */
    private function datBody(string $guid, string $patientId, string $name, string $age, string $sex): string
    {
        return "\x01{$guid}\x01{$patientId}\x01{$name}\x01{$age}\x01{$sex}\x01";
    }

    private function makePatient(string $code, int $worklist): Patient
    {
        return Patient::create([
            'patient_code' => $code,
            'name'         => 'Pasien Tes',
            'gender'       => 'Male',
            'age'          => 40,
            'pacemaker'    => 'No',
            'isInWorklist' => $worklist,
            'source'       => 'Outpatient',
        ]);
    }

    private function assertCode(int $code, $response): void
    {
        $response->assertOk();
        $this->assertStringContainsString("<Code>{$code}</Code>", $response->getContent());
    }

    // ---------- XML ----------

    public function test_upload_xml_valid_mendaftarkan_pasien_dan_menyimpan_hasil(): void
    {
        $response = $this->upload(self::GUID . '.xml', $this->xmlBody('KSM-0200'), 'application/xml');

        $this->assertCode(1, $response);

        $patient = Patient::where('patient_code', 'KSM-0200')->first();
        $this->assertNotNull($patient);
        $this->assertSame('Siti', $patient->name);
        $this->assertSame('Female', $patient->gender);
        $this->assertSame(2, (int) $patient->isInWorklist);

        $row = EkgResult::where('patient_id', $patient->id)->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->xml_file_path);
        Storage::disk('local')->assertExists($row->xml_file_path);
    }

    public function test_upload_xml_memindahkan_pasien_worklist_menjadi_selesai(): void
    {
        $patient = $this->makePatient('KSM-0201', 1);

        $this->upload(self::GUID . '.xml', $this->xmlBody('KSM-0201'), 'application/xml');

        $this->assertSame(2, (int) $patient->fresh()->isInWorklist);
        $this->assertSame(1, EkgResult::where('patient_id', $patient->id)->count());
    }

    public function test_upload_xml_rusak_tanpa_cache_query_ditolak(): void
    {
        $response = $this->upload(self::GUID . '.xml', '<root><PatientInfo>', 'application/xml');

        $this->assertCode(-1, $response);
        $this->assertSame(0, EkgResult::count());
    }

    // ---------- origin QUERY vs MANUAL ----------

    public function test_origin_query_jika_id_file_sama_dengan_query_terakhir(): void
    {
        $this->makePatient('KSM-0300', 1);
        Cache::put('ekg_last_queried_patient_id', 'KSM-0300', now()->addMinutes(10));

        $this->upload(self::GUID . '.xml', $this->xmlBody('KSM-0300'), 'application/xml');

        $this->assertSame('QUERY', EkgResult::first()->origin);
    }

    public function test_origin_manual_dan_cache_query_dibuang_jika_id_file_berbeda(): void
    {
        $this->makePatient('KSM-0300', 1);
        Cache::put('ekg_last_queried_patient_id', 'KSM-0300', now()->addMinutes(10));

        $this->upload(self::GUID . '.xml', $this->xmlBody('KSM-0999'), 'application/xml');

        $this->assertSame('MANUAL', EkgResult::first()->origin);
        $this->assertNull(Cache::get('ekg_last_queried_patient_id'));
        // pasien worklist yang di-query tidak ikut berubah
        $this->assertSame(1, (int) Patient::where('patient_code', 'KSM-0300')->first()->isInWorklist);
    }

    // ---------- PDF ----------

    public function test_pdf_tidak_terbaca_tanpa_cache_query_ditolak(): void
    {
        $response = $this->upload(self::GUID . '.pdf', '%PDF-1.4 bukan pdf asli', 'application/pdf');

        $this->assertCode(-1, $response);
        $this->assertStringContainsString('patient_id', $response->getContent());
        $this->assertSame(0, EkgResult::count());
    }

    public function test_pdf_tidak_terbaca_memakai_pasien_dari_cache_query(): void
    {
        $patient = $this->makePatient('KSM-0400', 1);
        Cache::put('ekg_last_queried_patient_id', 'KSM-0400', now()->addMinutes(10));

        $response = $this->upload(self::GUID . '.pdf', '%PDF-1.4 bukan pdf asli', 'application/pdf');

        $this->assertCode(1, $response);

        $row = EkgResult::where('patient_id', $patient->id)->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->result_file_path);
        $this->assertSame('QUERY', $row->origin);
        Storage::disk('local')->assertExists($row->result_file_path);
    }

    // ---------- DAT ----------

    public function test_dat_terbaca_mendaftarkan_pasien_dari_isi_file(): void
    {
        $body = $this->datBody(self::GUID, 'KSM-0500', 'Budi Santoso', '40Y', 'Male');

        $response = $this->upload(self::GUID . '.dat', $body);

        $this->assertCode(1, $response);

        $patient = Patient::where('patient_code', 'KSM-0500')->first();
        $this->assertNotNull($patient);
        $this->assertSame('Budi Santoso', $patient->name);
        $this->assertSame('Male', $patient->gender);
        $this->assertSame(40, (int) $patient->age);

        $row = EkgResult::where('patient_id', $patient->id)->first();
        $this->assertNotNull($row->dat_file_path);
        Storage::disk('local')->assertExists($row->dat_file_path);
    }

    public function test_dat_tidak_terbaca_ditahan_di_cache(): void
    {
        $response = $this->upload(self::GUID . '.dat', "\x01sampah\x01");

        $this->assertCode(1, $response);
        $this->assertCount(1, Cache::get('ekg_pending_dats', []));
        $this->assertSame(0, EkgResult::count());
    }

    public function test_dat_yang_ditahan_menempel_ke_xml_berikutnya_dalam_satu_baris(): void
    {
        $this->upload(self::GUID . '.dat', "\x01sampah\x01");

        $this->upload('AAAA0000-0000-0000-0000-000000000001.xml', $this->xmlBody('KSM-0600'), 'application/xml');

        $this->assertSame(1, EkgResult::count());

        $row = EkgResult::first();
        $this->assertNotNull($row->xml_file_path);
        $this->assertNotNull($row->dat_file_path);
        $this->assertNull(Cache::get('ekg_pending_dats'));
    }

    public function test_xml_lalu_dat_terbaca_pasien_sama_digabung_dalam_satu_baris(): void
    {
        $this->upload(self::GUID . '.xml', $this->xmlBody('KSM-0700'), 'application/xml');
        $this->upload(self::GUID . '.dat', $this->datBody(self::GUID, 'KSM-0700', 'Siti', '30Y', 'Female'));

        $this->assertSame(1, EkgResult::count());

        $row = EkgResult::first();
        $this->assertNotNull($row->xml_file_path);
        $this->assertNotNull($row->dat_file_path);
    }

    // ---------- lain-lain ----------

    public function test_ekstensi_tidak_dikenal_tetap_dibalas_sukses_tanpa_membuat_hasil(): void
    {
        $response = $this->upload('catatan.txt', 'halo');

        $this->assertCode(1, $response);
        $this->assertSame(0, EkgResult::count());
        $this->assertNotEmpty(Storage::disk('local')->allFiles('ekg-incoming'));
    }

    public function test_tanpa_header_filename_nama_file_ditebak_dari_content_type(): void
    {
        $this->call('POST', '/api/receive', [], [], [], ['CONTENT_TYPE' => 'application/xml'], $this->xmlBody('KSM-0800'));

        $row = EkgResult::first();
        $this->assertNotNull($row);
        $this->assertStringEndsWith('ecg_result.xml', $row->xml_file_path);
    }
}