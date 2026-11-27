<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'username' => Str::lower(fake()->unique()->userName()),
            'password' => static::$password ??= Hash::make('password'),
            'peran' => 'staf',
            'aktif' => true,
        ];
    }

    public function pemilik(): static
    {
        return $this->state(fn () => ['peran' => 'pemilik']);
    }
}
