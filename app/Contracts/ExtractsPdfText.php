<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface ExtractsPdfText
{
    public function __invoke(UploadedFile $pdf): string;
}
