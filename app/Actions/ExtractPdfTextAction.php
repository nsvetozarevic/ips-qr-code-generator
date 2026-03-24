<?php

namespace App\Actions;

use App\Contracts\ExtractsPdfText;
use Illuminate\Http\UploadedFile;
use Spatie\PdfToText\Pdf;

class ExtractPdfTextAction implements ExtractsPdfText
{
    public function __invoke(UploadedFile $pdf): string
    {
        $text = Pdf::getText($pdf->getRealPath(), options: ['-layout']);

        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        return preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    }
}
