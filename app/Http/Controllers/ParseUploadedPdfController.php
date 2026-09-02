<?php

namespace App\Http\Controllers;

use App\Contracts\ExtractsPdfText;
use App\Contracts\ParsesPaymentSlipPdf;
use App\Http\Requests\UploadPaymentSlipRequest;
use App\Http\Resources\SlipResultResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ParseUploadedPdfController
{
    public function __construct(
        private readonly ExtractsPdfText $extractPdfText,
        private readonly ParsesPaymentSlipPdf $parsePaymentSlipPdf,
    ) {}

    public function __invoke(UploadPaymentSlipRequest $request): AnonymousResourceCollection
    {
        $text = ($this->extractPdfText)($request->file('pdf'));
        $slips = ($this->parsePaymentSlipPdf)($text);

        return SlipResultResource::collection($slips);
    }
}
