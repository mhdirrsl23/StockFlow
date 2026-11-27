<?php

namespace App\Http\Requests;

use App\Models\Barang;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $barangId = $this->route('barang') instanceof Barang ? $this->route('barang')->id : null;

        return [
            'kode_sku' => [
                'bail',
                'required',
                'string',
                'max:50',
                Rule::unique('barang', 'kode_sku')->ignore($barangId)->whereNull('deleted_at'),
            ],
            'nama' => ['required', 'string', 'max:150'],
            'kategori' => ['required', 'in:Makanan,Minuman,Obat,Kebutuhan Rumah,Lainnya'],
            'satuan' => ['required', 'string', 'max:30'],
            'stok_minimum' => ['required', 'integer', 'min:0', 'max:999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'kode_sku.unique' => 'Kode SKU sudah dipakai barang aktif lain (AB-07).',
        ];
    }
}
