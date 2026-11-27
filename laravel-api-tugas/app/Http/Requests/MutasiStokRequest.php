<?php

namespace App\Http\Requests;

use App\Models\Barang;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MutasiStokRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $jenis = (string) $this->input('jenis');

        $rules = [
            'barang_id' => [
                'required',
                Rule::exists('barang', 'id')->whereNull('deleted_at'),
            ],
            'jenis' => ['required', 'in:masuk,keluar,penyesuaian'],
            'catatan' => ['nullable', 'string', 'max:255'],
            'waktu' => ['nullable', 'date', 'before_or_equal:now'],
        ];

        if ($jenis === 'penyesuaian') {
            $rules['jumlah'] = ['required', 'integer', 'not_in:0'];
            $rules['catatan'] = ['required', 'string', 'max:255'];
            $rules['batch_id'] = [
                'required',
                Rule::exists('batch_stok', 'id')->whereNull('deleted_at'),
            ];

            return $rules;
        }

        if ($jenis === 'masuk') {
            $rules['jumlah'] = ['required', 'integer', 'gte:1', 'max:999999'];
            $rules['batch_id'] = [
                'nullable',
                Rule::exists('batch_stok', 'id')
                    ->where('barang_id', (int) $this->input('barang_id'))
                    ->whereNull('deleted_at'),
            ];
            $rules['nomor_batch'] = ['required_without:batch_id', 'nullable', 'string', 'max:100'];
            $rules['tanggal_kedaluwarsa'] = ['required_without:batch_id', 'nullable', 'date', 'after_or_equal:today'];

            return $rules;
        }

        $rules['jumlah'] = ['required', 'integer', 'gte:1', 'max:999999'];
        $rules['batch_id'] = [
            'nullable',
            'integer',
            Rule::exists('batch_stok', 'id')->whereNull('deleted_at'),
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'barang_id.exists' => 'Barang tidak ditemukan atau sudah terhapus.',
            'batch_id.exists' => 'Batch tidak ditemukan, sudah terhapus, atau bukan milik barang tersebut.',
            'waktu.before_or_equal' => 'Tanggal transaksi tidak boleh berada di masa depan.',
            'tanggal_kedaluwarsa.after_or_equal' => 'Tanggal kedaluwarsa tidak boleh berada di masa lalu (FR-05).',
            'jumlah.not_in' => 'Penyesuaian menganggap selisih tidak boleh 0 (FR-13).',
        ];
    }

    public function prepareForValidation(): void
    {
        if ($this->filled('batch_id')) {
            $this->merge(['batch_id' => (int) $this->input('batch_id')]);
        }

        if ($this->filled('barang_id')) {
            $this->merge(['barang_id' => (int) $this->input('barang_id')]);
        }
    }

    public function barangId(): ?int
    {
        return $this->input('barang_id') !== null ? (int) $this->input('barang_id') : null;
    }

    public function barangModel(): ?Barang
    {
        return Barang::find($this->barangId());
    }
}
