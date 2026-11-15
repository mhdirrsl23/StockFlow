<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PenangananRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * FR-12: tindakan retur/pemusnahan/pengeluaran; catatan wajib bila Lainnya.
     */
    public function rules(): array
    {
        return [
            'batch_id' => [
                'required',
                Rule::exists('batch_stok', 'id')->whereNull('deleted_at'),
            ],
            'status_penanganan' => ['required', 'in:Dimusnahkan,Dikembalikan ke Pemasok,Lainnya'],
            'jumlah' => ['nullable', 'integer', 'gte:1', 'max:999999'],
            'catatan_penanganan' => ['nullable', 'string', 'max:1000'],
            'waktu' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'batch_id.exists' => 'Batch tidak ditemukan atau sudah terhapus.',
            'waktu.before_or_equal' => 'Waktu penanganan tidak boleh berada di masa depan.',
        ];
    }
}
