<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImporPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * FR-15 / BR-06: berkas .xlsx/.xls, ukuran dibatasi di layanan impor
     * sesuai pengaturan (maks_ukuran_impor_mb).
     */
    public function rules(): array
    {
        return [
            'berkas' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
        ];
    }

    public function messages(): array
    {
        return [
            'berkas.mimes' => 'Format berkas harus .xlsx atau .xls (BR-06).',
        ];
    }
}
