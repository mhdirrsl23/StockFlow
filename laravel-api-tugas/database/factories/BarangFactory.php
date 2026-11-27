<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BarangFactory extends Factory
{
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
