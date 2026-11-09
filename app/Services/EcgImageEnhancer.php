<?php

namespace App\Services;

class EcgImageEnhancer
{
    // Area grafik EKG, dalam pecahan dari ukuran gambar (0 - 1).
    // Sesuaikan kalau latar merah muda terlalu atas/bawah/kiri/kanan.
    private const GRID_X1 = 0.04;
    private const GRID_X2 = 1.00;
    private const GRID_Y1 = 0.20;
    private const GRID_Y2 = 0.93;

    // Pengurangan warna hijau dan biru di area grafik (putih -> merah muda).
    // Hitam (gelombang) tetap hitam. Naikkan untuk warna lebih pekat.
    private const TINT = 25;

    /**
     * @return string path file sementara hasil olahan (jpg/png), atau path asli kalau gagal
     */
    public function enhance(string $imagePath): string
    {
        $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));

        $img = match ($ext) {
            'png'         => @imagecreatefrompng($imagePath),
            'jpg', 'jpeg' => @imagecreatefromjpeg($imagePath),
            default       => false,
        };

        if (!$img) {
            return $imagePath;
        }

        $w = imagesx($img);
        $h = imagesy($img);

        $x  = (int) round($w * self::GRID_X1);
        $y  = (int) round($h * self::GRID_Y1);
        $cw = (int) round($w * self::GRID_X2) - $x;
        $ch = (int) round($h * self::GRID_Y2) - $y;

        $region = imagecreatetruecolor($cw, $ch);
        imagecopy($region, $img, 0, 0, $x, $y, $cw, $ch);

        imagefilter($region, IMG_FILTER_COLORIZE, 0, -self::TINT, -self::TINT);

        imagecopy($img, $region, $x, $y, 0, 0, $cw, $ch);
        imagedestroy($region);

        $output = tempnam(sys_get_temp_dir(), 'ecgimg_') . '.' . ($ext === 'png' ? 'png' : 'jpg');

        if ($ext === 'png') {
            imagepng($img, $output);
        } else {
            imagejpeg($img, $output, 92);
        }
        imagedestroy($img);

        return $output;
    }
}
