<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadPaymentSlipRequest;

class ParseUploadedPdfController extends Controller
{
    public function __invoke(UploadPaymentSlipRequest $request): void
    {
        dd($request->file('pdf')->getClientOriginalName());
    }
}
