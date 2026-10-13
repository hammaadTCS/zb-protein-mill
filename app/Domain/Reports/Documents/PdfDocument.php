<?php

declare(strict_types=1);

namespace App\Domain\Reports\Documents;

use Barryvdh\DomPDF\PDF;
use Illuminate\Http\Response;

/**
 * Card T6.10: renders a `pdf.*` Blade view with dompdf. Remote files and PHP inside
 * templates stay off, so a PDF can only use what the view embeds; DejaVu Sans is
 * dompdf's built-in font (no IBM Plex Sans TTF in the repository).
 */
final class PdfDocument
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function download(string $view, array $data, string $filename): Response
    {
        /** @var PDF $pdf */
        $pdf = app('dompdf.wrapper');

        return $pdf
            ->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'isFontSubsettingEnabled' => true, 'defaultFont' => 'DejaVu Sans'])
            ->setPaper('a4')
            ->loadView($view, $data)
            ->download($filename);
    }
}
