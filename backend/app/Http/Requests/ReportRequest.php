<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * FR-16 / FR-17: rentang filter dipakai laporan maupun hasil ekspor.
     */
    public function rules(): array
    {
        return [
            'jenis' => ['required_with:format', 'in:stok,transaksi,kedaluwarsa,laris'],
            'format' => ['nullable', 'in:xlsx,pdf'],
            'dari' => ['nullable', 'date', 'before_or_equal:today'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
            'kategori' => ['nullable', 'in:Makanan,Minuman,Obat,Kebutuhan Rumah,Lainnya'],
            'status' => ['nullable', 'in:Aman,Mendekati Kedaluwarsa,Kedaluwarsa,Ditangani'],
            'barang_id' => ['nullable', Rule::exists('barang', 'id')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'jenis.in' => 'Jenis laporan harus salah satu dari: stok, transaksi, kedaluwarsa, laris.',
            'format.in' => 'Format ekspor harus xlsx atau pdf.',
            'dari.before_or_equal' => 'Tanggal mulai tidak boleh setelah hari ini.',
            'sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.',
        ];
    }
}
