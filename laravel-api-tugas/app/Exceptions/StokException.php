<?php

namespace App\Exceptions;

use RuntimeException;

class StokException extends RuntimeException
{
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
