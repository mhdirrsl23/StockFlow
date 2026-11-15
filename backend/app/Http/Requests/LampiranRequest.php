<?php

namespace App\Http\Requests;

use App\Models\Pengaturan;
use Illuminate\Foundation\Http\FormRequest;

class LampiranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * FR-14 / Q-05: foto JPG/PNG; batas ukuran membaca pengaturan
     * `maks_ukuran_lampiran_mb` sehingga tetap dinamis.
     */
    public function rules(): array
    {
        $maksKb = ($this->batasMb() * 1024) - 1;

        return [
            'jenis' => ['required', 'in:bukti_batch,bukti_penanganan'],
            'lampiran' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:'.$maksKb],
        ];
    }

    public function messages(): array
    {
        $batas = $this->batasMb();

        return [
            'lampiran.required' => 'Foto wajib dipilih (FR-14).',
            'lampiran.mimes' => 'Foto harus berformat JPG, JPEG, atau PNG (Q-05).',
            'lampiran.max' => "Ukuran foto melebihi batas {$batas} MB berdasarkan pengaturan (NFR-01).",
        ];
    }

    private function batasMb(): int
    {
        return max(1, (int) (\App\Models\Pengaturan::nilai('maks_ukuran_lampiran_mb', '2')));
    }
}
