<?php

namespace Tests\Feature;

use App\Models\EkgResult;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EkgControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    // ---------- helper ----------

    private function login(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        return $user;
    }

    private function makePatient(string $code = 'KSM-0001', string $name = 'Pasien Tes'): Patient
    {
        return Patient::create([
            'patient_code' => $code,
            'name'         => $name,
            'gender'       => 'Male',
            'age'          => 40,
            'pacemaker'    => 'No',
            'isInWorklist' => 2,
            'source'       => 'Outpatient',
        ]);
    }

    private function makeResult(Patient $patient, ?string $path = 'ekg_results/a.pdf'): EkgResult
    {
        return EkgResult::create([
            'patient_id'       => $patient->id,
            'result_file_path' => $path,
            'examination_date' => now(),
        ]);
    }

    private function validPayload(Patient $patient, array $override = []): array
    {
        return array_merge([
            'patient_id'       => $patient->id,
            'result_file'      => UploadedFile::fake()->create('hasil.pdf', 100, 'application/pdf'),
            'examination_date' => '2026-10-09',
        ], $override);
    }

    // ---------- akses ----------

    public function test_tamu_tidak_bisa_membuka_daftar_ekg(): void
    {
        $this->get(route('ekg.index'))->assertRedirect(route('login'));
    }

    public function test_tamu_tidak_bisa_menyimpan_ekg(): void
    {
        $patient = $this->makePatient();

        $this->post(route('ekg.store'), $this->validPayload($patient))
            ->assertRedirect(route('login'));

        $this->assertSame(0, EkgResult::count());
    }

    // ---------- index ----------

    public function test_daftar_ekg_menampilkan_hasil(): void
    {
        $this->login();
        $this->makeResult($this->makePatient());

        $response = $this->get(route('ekg.index'));

        $response->assertOk();
        $this->assertCount(1, $response->viewData('ekgResults'));
    }

    public function test_daftar_ekg_dipaginasi_10_per_halaman(): void
    {
        $this->login();
        $patient = $this->makePatient();
        for ($i = 0; $i < 12; $i++) {
            $this->makeResult($patient, "ekg_results/{$i}.pdf");
        }

        $page1 = $this->get(route('ekg.index'))->viewData('ekgResults');
        $page2 = $this->get(route('ekg.index', ['page' => 2]))->viewData('ekgResults');

        $this->assertCount(10, $page1);
        $this->assertCount(2, $page2);
        $this->assertSame(12, $page1->total());
    }

    public function test_pencarian_berdasarkan_nama_pasien(): void
    {
        $this->login();
        $this->makeResult($this->makePatient('KSM-0001', 'Budi Santoso'));
        $this->makeResult($this->makePatient('KSM-0002', 'Siti Aminah'));

        $results = $this->get(route('ekg.index', ['search' => 'Budi']))->viewData('ekgResults');

        $this->assertCount(1, $results);
        $this->assertSame('Budi Santoso', $results->first()->patient->name);
    }

    public function test_pencarian_berdasarkan_kode_pasien(): void
    {
        $this->login();
        $this->makeResult($this->makePatient('KSM-0001', 'Budi'));
        $this->makeResult($this->makePatient('KSM-0002', 'Siti'));

        $results = $this->get(route('ekg.index', ['search' => 'KSM-0002']))->viewData('ekgResults');

        $this->assertCount(1, $results);
        $this->assertSame('KSM-0002', $results->first()->patient->patient_code);
    }

    public function test_pencarian_tanpa_hasil_mengembalikan_daftar_kosong(): void
    {
        $this->login();
        $this->makeResult($this->makePatient());

        $results = $this->get(route('ekg.index', ['search' => 'tidak-ada']))->viewData('ekgResults');

        $this->assertCount(0, $results);
    }

    // ---------- store ----------

    public function test_simpan_ekg_valid_menyimpan_file_dan_data(): void
    {
        $this->login();
        $patient = $this->makePatient();

        $this->post(route('ekg.store'), $this->validPayload($patient))
            ->assertRedirect(route('ekg.index'))
            ->assertSessionHas('success');

        $row = EkgResult::first();
        $this->assertNotNull($row);
        $this->assertSame($patient->id, $row->patient_id);
        $this->assertStringStartsWith('ekg_results/', $row->result_file_path);
        $this->assertStringEndsWith('hasil.pdf', $row->result_file_path);
        $this->assertTrue(Storage::disk('public')->exists($row->result_file_path));
    }

    public function test_simpan_ekg_tanpa_data_ditolak(): void
    {
        $this->login();

        $this->post(route('ekg.store'), [])
            ->assertSessionHasErrors(['patient_id', 'result_file', 'examination_date']);

        $this->assertSame(0, EkgResult::count());
    }

    public function test_simpan_ekg_dengan_pasien_tidak_ada_ditolak(): void
    {
        $this->login();
        $patient = $this->makePatient();

        $this->post(route('ekg.store'), $this->validPayload($patient, ['patient_id' => 9999]))
            ->assertSessionHasErrors('patient_id');
    }

    public function test_simpan_ekg_selain_pdf_ditolak(): void
    {
        $this->login();
        $patient = $this->makePatient();

        $this->post(route('ekg.store'), $this->validPayload($patient, [
            'result_file' => UploadedFile::fake()->create('foto.jpg', 100, 'image/jpeg'),
        ]))->assertSessionHasErrors('result_file');

        $this->assertSame(0, EkgResult::count());
    }

    public function test_simpan_ekg_lebih_dari_2mb_ditolak(): void
    {
        $this->login();
        $patient = $this->makePatient();

        $this->post(route('ekg.store'), $this->validPayload($patient, [
            'result_file' => UploadedFile::fake()->create('besar.pdf', 3000, 'application/pdf'),
        ]))->assertSessionHasErrors('result_file');

        $this->assertSame(0, EkgResult::count());
    }

    public function test_simpan_ekg_dengan_tanggal_tidak_valid_ditolak(): void
    {
        $this->login();
        $patient = $this->makePatient();

        $this->post(route('ekg.store'), $this->validPayload($patient, ['examination_date' => 'bukan-tanggal']))
            ->assertSessionHasErrors('examination_date');
    }

    // ---------- destroy ----------

    public function test_hapus_ekg_menghapus_baris_dan_file(): void
    {
        $this->login();
        Storage::disk('public')->put('ekg_results/a.pdf', 'isi');
        $row = $this->makeResult($this->makePatient(), 'ekg_results/a.pdf');

        $this->delete(route('ekg.destroy', $row->id))
            ->assertRedirect(route('ekg.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('ekg_results', ['id' => $row->id]);
        $this->assertFalse(Storage::disk('public')->exists('ekg_results/a.pdf'));
    }

    public function test_hapus_ekg_tidak_menghapus_baris_lain(): void
    {
        $this->login();
        $patient = $this->makePatient();
        $a = $this->makeResult($patient, 'ekg_results/a.pdf');
        $b = $this->makeResult($patient, 'ekg_results/b.pdf');

        $this->delete(route('ekg.destroy', $a->id));

        $this->assertDatabaseMissing('ekg_results', ['id' => $a->id]);
        $this->assertDatabaseHas('ekg_results', ['id' => $b->id]);
    }

    public function test_tamu_tidak_bisa_menghapus_ekg(): void
    {
        $row = $this->makeResult($this->makePatient());

        $this->delete(route('ekg.destroy', $row->id))->assertRedirect(route('login'));

        $this->assertDatabaseHas('ekg_results', ['id' => $row->id]);
    }
}