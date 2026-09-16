<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;

class EcgPdfEnhancer
{
    public function enhance(string $pdfPath): string
    {
        $pdf = new Fpdi();

        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        // ==========================================
        // BACA PDF ECG ASLI
        // ==========================================

        $pdf->setSourceFile($pdfPath);

        // Hanya ambil halaman pertama
        $template = $pdf->importPage(1);

        // Ikuti ukuran PDF ECG asli
        $size = $pdf->getTemplateSize($template);

        $pdf->AddPage(
            $size['orientation'],
            [
                $size['width'],
                $size['height']
            ]
        );

        $pageW = $pdf->GetPageWidth();
        $pageH = $pdf->GetPageHeight();

        // ==========================================
        // BACKGROUND ECG
        // ==========================================
        //
        // Hanya area grafik ECG yang diberi
        // background pink.
        //

        $this->drawGridBackground(
            $pdf,
            $pageW,
            $pageH
        );

        // ==========================================
        // PDF ECG ASLI
        // ==========================================
        //
        // Diletakkan di atas background.
        // Grid dan waveform asli tetap digunakan.
        //

        $pdf->useTemplate(
            $template,
            0,
            0,
            $pageW,
            $pageH
        );

        // ==========================================
        // SIMPAN HASIL
        // ==========================================

        $output = tempnam(
            sys_get_temp_dir(),
            'ecg_'
        ) . '.pdf';

        $pdf->Output($output, 'F');

        return $output;
    }

    private function drawGridBackground(
        Fpdi $pdf,
        float $pageW,
        float $pageH
    ): void {

        // ==========================================
        // AREA GRID ECG
        // ==========================================

        // Posisi horizontal area ECG
        $gridX = 16;

        // Posisi mulai area ECG dari atas
        $gridY = 44;

        // Posisi akhir area ECG
        $gridBottom = 189.8;

        // Tinggi area ECG
        $gridH = $gridBottom - $gridY;

        // Lebar area ECG
        $gridW = $pageW;

        // ==========================================
        // BACKGROUND MERAH MUDA
        // ==========================================

        $pdf->SetFillColor(
            255,
            230,
            230
        );

        $pdf->Rect(
            $gridX,
            $gridY,
            $gridW,
            $gridH,
            'F'
        );

        // ==========================================
        // RESET WARNA
        // ==========================================

        $pdf->SetFillColor(
            255,
            255,
            255
        );

        $pdf->SetDrawColor(
            0,
            0,
            0
        );
    }
}

