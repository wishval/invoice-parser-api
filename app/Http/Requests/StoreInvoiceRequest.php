<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pdf.required' => 'Please provide a PDF file to upload',
            'pdf.file' => 'The uploaded file is invalid',
            'pdf.mimes' => 'Only PDF files are accepted',
            'pdf.max' => 'PDF must not exceed 10MB in size',
        ];
    }
}
