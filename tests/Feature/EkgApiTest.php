<?php

namespace Tests\Feature;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EkgApiTest extends TestCase
{
    use RefreshDatabase;

    private function queryXml(string $patientId): string
    {
        return '<?xml version="1.0" encoding="UTF-8" ?><root><flag>2</flag>'
            . '<PatientID>' . $patientId . '</PatientID><DeviceID>1</DeviceID></root>';
    }

    private function postXml(string $uri, string $xml)
    {
        return $this->call('POST', $uri, [], [], [], ['CONTENT_TYPE' => 'application/xml'], $xml);
    }

    private function makePatient(string $code, int $worklist): Patient
    {
        return Patient::create([
            'patient_code' => $code,
            'name' => 'Pasien Tes',
            'gender' => 'Male',
            'age' => 40,
            'pacemaker' => 'No',
            'isInWorklist' => $worklist,
            'source' => 'Outpatient',
        ]);
    }

    public function test_network_test_dengan_body_kosong_berhasil(): void
    {
        $response = $this->call('POST', '/api/receive');

        $response->assertOk();
        $this->assertStringContainsString('<Code>1</Code>', $response->getContent());
    }

    public function test_query_pasien_yang_tidak_ada_tidak_masuk_cache(): void
    {
        Cache::forget('ekg_last_queried_patient_id');

        $this->postXml('/api/query', $this->queryXml('KSM-9999'));

        $this->assertNull(Cache::get('ekg_last_queried_patient_id'));
    }

    public function test_query_pasien_di_worklist_disimpan_ke_cache(): void
    {
        Cache::forget('ekg_last_queried_patient_id');
        $this->makePatient('KSM-0001', 1);

        $this->postXml('/api/query', $this->queryXml('KSM-0001'));

        $this->assertSame('KSM-0001', Cache::get('ekg_last_queried_patient_id'));
    }

    public function test_pasien_yang_sudah_selesai_tidak_masuk_cache(): void
    {
        Cache::forget('ekg_last_queried_patient_id');
        $this->makePatient('KSM-0002', 2);

        $this->postXml('/api/query', $this->queryXml('KSM-0002'));

        $this->assertNull(Cache::get('ekg_last_queried_patient_id'));
    }

    public function test_halaman_unduhan_butuh_login(): void
    {
        $this->get('/ekg/download/1/pdf')->assertRedirect();
    }

    public function test_query_dengan_patient_id_kosong_tidak_masuk_cache(): void
    {
        Cache::forget('ekg_last_queried_patient_id');

        $this->postXml('/api/query', $this->queryXml(''));

        $this->assertNull(Cache::get('ekg_last_queried_patient_id'));
    }

    public function test_query_terbaru_menimpa_cache_sebelumnya(): void
    {
        Cache::forget('ekg_last_queried_patient_id');
        $this->makePatient('KSM-0001', 1);
        $this->makePatient('KSM-0002', 1);

        $this->postXml('/api/query', $this->queryXml('KSM-0001'));
        $this->postXml('/api/query', $this->queryXml('KSM-0002'));

        $this->assertSame('KSM-0002', Cache::get('ekg_last_queried_patient_id'));
    }

    public function test_query_dengan_xml_rusak_tidak_membuat_server_error(): void
    {
        $response = $this->postXml('/api/query', '<root><PatientID>');

        $this->assertLessThan(500, $response->getStatusCode());
    }

    public function test_pasien_yang_belum_masuk_worklist_tidak_masuk_cache(): void
    {
        Cache::forget('ekg_last_queried_patient_id');
        $this->makePatient('KSM-0003', 0);

        $this->postXml('/api/query', $this->queryXml('KSM-0003'));

        $this->assertNull(Cache::get('ekg_last_queried_patient_id'));
    }
}