<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Kesalahan aturan bisnis stok; diterjemahkan menjadi 422 per-field agar
 * aplikasi Android menampilkan pesan di dekat kolom terkait (FR-19).
 */
class StokException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        public readonly array $errors,
        public readonly string $pesan = 'Transaksi stok tidak dapat diproses',
    ) {
        parent::__construct($pesan);
    }

    public static function field(string $kolom, string $pesan, string $judul = 'Transaksi stok tidak dapat diproses'): self
    {
        return new self(errors: [$kolom => [$pesan]], pesan: $judul);
    }
}
