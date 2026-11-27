<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nomor_batch' => ['required', 'string', 'max:100'],
            'jumlah_awal' => ['required', 'integer', 'min:1', 'max:999999'],
            'tanggal_kedaluwarsa' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal_kedaluwarsa.after_or_equal' => 'Tanggal kedaluwarsa tidak boleh sebelum hari ini (FR-05).',
        ];
    }
}
