<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadPaymentSlipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'pdf.required' => 'Please upload a PDF payment slip.',
            'pdf.file' => 'The uploaded document must be a file.',
            'pdf.mimes' => 'Only PDF files are supported.',
            'pdf.max' => 'The PDF file may not be greater than 10 MB.',
        ];
    }
}
