<?php

namespace Tests\Feature;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EkgQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ---------- helper ----------

    private function queryXml(string $patientId): string
    {
        return '<?xml version="1.0" encoding="UTF-8" ?><root><flag>2</flag>'
            . '<PatientID>' . $patientId . '</PatientID><DeviceID>1</DeviceID></root>';
    }

    private function postXml(string $body)
    {
        return $this->call('POST', '/api/query', [], [], [], ['CONTENT_TYPE' => 'application/xml'], $body);
    }

    private function makePatient(string $code, array $override = []): Patient
    {
        return Patient::create(array_merge([
            'patient_code' => $code,
            'name'         => 'Pasien Tes',
            'gender'       => 'Male',
            'age'          => 40,
            'pacemaker'    => 'No',
            'isInWorklist' => 1,
            'source'       => 'Outpatient',
        ], $override));
    }

    /** Parse body respons jadi SimpleXMLElement, sekaligus memastikan XML-nya valid. */
    private function parse($response): \SimpleXMLElement
    {
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'Respons bukan XML yang valid');
        return $xml;
    }

    private function assertNotFoundResponse($response): void
    {
        $response->assertOk();
        $this->assertStringContainsString('<Error message="Patient not found"/>', $response->getContent());
        $this->assertNull(Cache::get('ekg_last_queried_patient_id'));
    }

    // ---------- pasien ditemukan ----------

    public function test_pasien_di_worklist_dibalas_xml_lengkap(): void
    {
        $this->makePatient('KSM-0001', ['name' => 'Budi Santoso', 'age' => 52]);

        $response = $this->postXml($this->queryXml('KSM-0001'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml');

        $xml = $this->parse($response);
        $this->assertSame('1', (string) $xml->Code);
        $this->assertCount(1, $xml->records->rows);

        $row = $xml->records->rows[0];
        $this->assertSame('KSM-0001', (string) $row->SerialNo);
        $this->assertSame('KSM-0001', (string) $row->PatientID);
        $this->assertSame('Budi Santoso', (string) $row->PatientName);
        $this->assertSame('M', (string) $row->PatientSex);
        $this->assertSame('52', (string) $row->PatientAge);
        $this->assertSame('Y', (string) $row->PatientAgeUnit);
        $this->assertSame('ECGKSM-0001', (string) $row->RequestID);
        $this->assertSame(now()->format('Ymd'), (string) $row->RequestDate);
    }

    public function test_urutan_tag_row_sesuai_yang_diharapkan_device(): void
    {
        $this->makePatient('KSM-0002');

        $xml = $this->parse($this->postXml($this->queryXml('KSM-0002')));

        $tags = [];
        foreach ($xml->records->rows[0]->children() as $child) {
            $tags[] = $child->getName();
        }

        $this->assertSame([
            'SerialNo', 'PatientID', 'PatientName', 'PatientSex', 'PatientAge',
            'PatientAgeUnit', 'PatientBirthDate', 'RequestDepartment', 'RequestID',
            'SickBedNo', 'Pacemaker', 'PatientSource', 'ExamDepartment', 'Priority',
            'fileGuid', 'RequestDate',
        ], $tags);
    }

    public function test_hanya_pasien_yang_cocok_yang_dikembalikan(): void
    {
        $this->makePatient('KSM-0003');
        $this->makePatient('KSM-0004');

        $xml = $this->parse($this->postXml($this->queryXml('KSM-0003')));

        $this->assertCount(1, $xml->records->rows);
        $this->assertSame('KSM-0003', (string) $xml->records->rows[0]->PatientID);
    }

    // ---------- mapping kode ----------

    public function test_mapping_gender(): void
    {
        $this->makePatient('KSM-G1', ['gender' => 'Male']);
        $this->makePatient('KSM-G2', ['gender' => 'Female']);
        $this->makePatient('KSM-G3', ['gender' => 'Unknown']);

        $expected = ['KSM-G1' => 'M', 'KSM-G2' => 'F', 'KSM-G3' => 'O'];

        foreach ($expected as $code => $sex) {
            $xml = $this->parse($this->postXml($this->queryXml($code)));
            $this->assertSame($sex, (string) $xml->records->rows[0]->PatientSex, "Gender untuk {$code}");
        }
    }

    public function test_mapping_pacemaker(): void
    {
        $this->makePatient('KSM-P1', ['pacemaker' => 'Yes']);
        $this->makePatient('KSM-P2', ['pacemaker' => 'No']);

        $yes = $this->parse($this->postXml($this->queryXml('KSM-P1')));
        $no  = $this->parse($this->postXml($this->queryXml('KSM-P2')));

        $this->assertSame('1', (string) $yes->records->rows[0]->Pacemaker);
        $this->assertSame('2', (string) $no->records->rows[0]->Pacemaker);
    }

    public function test_mapping_sumber_pasien(): void
    {
        $this->makePatient('KSM-S1', ['source' => 'Outpatient']);
        $this->makePatient('KSM-S2', ['source' => 'Inpatient']);
        $this->makePatient('KSM-S3', ['source' => 'Physical Exam']);

        $expected = ['KSM-S1' => '0', 'KSM-S2' => '1', 'KSM-S3' => '2'];

        foreach ($expected as $code => $sourceCode) {
            $xml = $this->parse($this->postXml($this->queryXml($code)));
            $this->assertSame($sourceCode, (string) $xml->records->rows[0]->PatientSource, "Source untuk {$code}");
        }
    }

    // ---------- cache ----------

    public function test_pasien_ditemukan_menyimpan_id_terakhir_dan_data_pasien_ke_cache(): void
    {
        $this->makePatient('KSM-0010', ['name' => 'Siti', 'gender' => 'Female', 'age' => 33]);

        $this->postXml($this->queryXml('KSM-0010'));

        $this->assertSame('KSM-0010', Cache::get('ekg_last_queried_patient_id'));
        $this->assertSame([
            'patient_name' => 'Siti',
            'patient_sex'  => 'F',
            'patient_age'  => '33',
        ], Cache::get('ekg_patient_data:KSM-0010'));
    }

    public function test_cache_id_terakhir_kedaluwarsa_setelah_dua_menit(): void
    {
        $this->makePatient('KSM-0011');

        $this->postXml($this->queryXml('KSM-0011'));
        $this->assertSame('KSM-0011', Cache::get('ekg_last_queried_patient_id'));

        $this->travel(3)->minutes();

        $this->assertNull(Cache::get('ekg_last_queried_patient_id'));
    }

    // ---------- pasien tidak ditemukan ----------

    public function test_pasien_tidak_ada_dibalas_error_dan_tidak_masuk_cache(): void
    {
        $this->assertNotFoundResponse($this->postXml($this->queryXml('KSM-9999')));
    }

    public function test_pasien_belum_masuk_worklist_ditolak(): void
    {
        $this->makePatient('KSM-0020', ['isInWorklist' => 0]);

        $this->assertNotFoundResponse($this->postXml($this->queryXml('KSM-0020')));
    }

    public function test_pasien_sudah_selesai_ditolak(): void
    {
        $this->makePatient('KSM-0021', ['isInWorklist' => 2]);

        $this->assertNotFoundResponse($this->postXml($this->queryXml('KSM-0021')));
    }

    // ---------- input tidak valid ----------

    public function test_body_kosong_dibalas_error(): void
    {
        $this->assertNotFoundResponse($this->postXml(''));
    }

    public function test_xml_rusak_dibalas_error_bukan_500(): void
    {
        $this->assertNotFoundResponse($this->postXml('<root><PatientID>'));
    }

    public function test_xml_tanpa_patient_id_dibalas_error(): void
    {
        $this->assertNotFoundResponse($this->postXml('<root><DeviceID>1</DeviceID></root>'));
    }

    public function test_patient_id_kosong_dibalas_error(): void
    {
        $this->makePatient('KSM-0030');

        $this->assertNotFoundResponse($this->postXml($this->queryXml('')));
    }

    // ---------- escaping ----------

    public function test_karakter_khusus_di_nama_di_escape_dan_xml_tetap_valid(): void
    {
        $this->makePatient('KSM-0040', ['name' => 'Budi & <Sari> "Tes"']);

        $xml = $this->parse($this->postXml($this->queryXml('KSM-0040')));

        $this->assertSame('Budi & <Sari> "Tes"', (string) $xml->records->rows[0]->PatientName);
    }
}