<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PengaturanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ambang_peringatan_hari' => ['nullable', 'integer', 'min:1', 'max:365'],
            'maks_ukuran_lampiran_mb' => ['nullable', 'integer', 'min:1', 'max:20'],
            'maks_ukuran_impor_mb' => ['nullable', 'integer', 'min:1', 'max:20'],
            'maks_baris_impor' => ['nullable', 'integer', 'min:10', 'max:10000'],
        ];
    }
}
