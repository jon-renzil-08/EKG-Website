<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Controller SEMENTARA untuk testing -- tujuannya cuma merekam
 * apa pun yang dikirim alat EKG ke Laravel, supaya kita punya bukti
 * nyata format protokolnya (header, body, dll) SEBELUM menulis
 * controller asli untuk /query dan /receive.
 *
 * JANGAN dipakai untuk production, ini cuma alat bantu diagnosa.
 * Setelah selesai testing, controller ini bisa dihapus / route-nya
 * dicabut dari routes/api.php.
 */
class EkgTestController extends Controller
{
    public function catchAll(Request $request)
    {
        $logData = [
            'timestamp'      => now()->toDateTimeString(),
            'method'         => $request->method(),
            'full_url'       => $request->fullUrl(),
            'path'           => $request->path(),
            'query_params'   => $request->query(),
            'all_headers'    => $request->headers->all(),
            'content_type'   => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length'),
            // raw body mentah -- penting banget, ini yang akan menunjukkan
            // apakah device kirim multipart, raw binary, atau format lain
            'raw_body_size'  => strlen($request->getContent()),
            'raw_body_preview' => substr($request->getContent(), 0, 500), // 500 byte pertama saja biar log tidak kebanyakan
            'has_files'      => $request->hasFile('file') ? 'YES' : 'NO',
            'all_post_input' => $request->except(['file']), // semua field form/POST biasa (kalau ada)
        ];

        // Simpan ke file log KHUSUS supaya mudah dicari, terpisah dari laravel.log biasa
        Log::channel('single')->info('=== EKG TEST REQUEST MASUK ===', $logData);

        // Kalau device benar-benar kirim file (multipart), simpan juga filenya
        // supaya bisa dibuka manual dan dicek isinya
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $savedPath = $file->store('ekg-test-captures');
            Log::channel('single')->info('File tersimpan di: ' . $savedPath);
        }

        // Kalau body-nya RAW (bukan multipart), simpan juga ke file terpisah
        // supaya tidak ke-cut di log (log di atas cuma preview 500 byte)
        if (strlen($request->getContent()) > 0 && !$request->hasFile('file')) {
            $filename = 'ekg-test-captures/raw_' . now()->format('Ymd_His') . '.bin';
            Storage::disk('local')->put($filename, $request->getContent());
            Log::channel('single')->info('Raw body tersimpan di: storage/app/' . $filename);
        }

        // PENTING: response ini masih SEMBARANGAN / placeholder.
        // Kalau device menunggu format tertentu dan tidak dapat, device
        // mungkin akan menunjukkan error atau terus retry -- itu TIDAK masalah
        // untuk tujuan testing ini, karena yang kita mau cuma lihat REQUEST-nya
        // berhasil terekam dulu. Response yang benar baru ditentukan di step berikutnya.
        return response()->json([
            'status' => 'received_for_testing',
            'message' => 'Request tercatat, cek storage/logs untuk detail',
        ], 200);
    }
}
