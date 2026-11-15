<?php

namespace Database\Factories;

use App\Models\Barang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Barang>
 */
class BarangFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode_sku' => 'SKU-'.strtoupper(fake()->unique()->lexify('??????')),
            'nama' => fake()->words(2, true),
            'kategori' => fake()->randomElement(['Makanan', 'Minuman', 'Obat', 'Kebutuhan Rumah', 'Lainnya']),
            'satuan' => fake()->randomElement(['pcs', 'box', 'pack']),
            'stok_minimum' => fake()->numberBetween(5, 50),
        ];
    }
}
