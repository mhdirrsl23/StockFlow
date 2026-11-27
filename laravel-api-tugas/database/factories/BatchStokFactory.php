<?php

namespace Database\Factories;

use App\Models\Barang;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class BatchStokFactory extends Factory
{
    public function definition(): array
    {
        $jumlah = fake()->numberBetween(20, 200);

        return [
            'barang_id' => Barang::factory(),
            'nomor_batch' => 'B-'.strtoupper(fake()->unique()->lexify('?????')),
            'jumlah_awal' => $jumlah,
            'jumlah_sisa' => $jumlah,
            'tanggal_kedaluwarsa' => Carbon::today()->addDays(fake()->numberBetween(40, 200))->toDateString(),
            'status' => 'Aman',
        ];
    }
}
