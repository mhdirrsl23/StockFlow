<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Baris Bahasa Validasi
    |--------------------------------------------------------------------------
    |
    | Satu baris per pesan, dipakai validator Laravel dengan bahasa `id`
    | (APP_LOCALE=id). Pesan konsisten berbahasa Indonesia (Bagian 3.4 PRD).
    |
    */

    'accepted' => 'Kolom :attribute harus disetujui.',
    'accepted_if' => 'Kolom :attribute harus disetujui ketika :other bernilai :value.',
    'active_url' => 'Kolom :attribute bukan URL yang valid.',
    'after' => 'Kolom :attribute harus tanggal setelah :date.',
    'after_or_equal' => 'Kolom :attribute harus tanggal sama atau setelah :date.',
    'alpha' => 'Kolom :attribute hanya boleh berisi huruf.',
    'alpha_dash' => 'Kolom :attribute hanya boleh berisi huruf, angka, garis miring, garis bawah, dan titik.',
    'alpha_num' => 'Kolom :attribute hanya boleh berisi huruf dan angka.',
    'array' => 'Kolom :attribute harus berupa array.',
    'ascii' => 'Kolom :attribute hanya boleh berisi karakter ascii tunggal.',
    'before' => 'Kolom :attribute harus tanggal sebelum :date.',
    'before_or_equal' => 'Kolom :attribute harus tanggal sama atau sebelum :date.',
    'between' => [
        'array' => 'Kolom :attribute harus terdiri dari :min sampai :max item.',
        'file' => 'Ukuran berkas :attribute harus antara :min sampai :max kilobita.',
        'numeric' => 'Nilai :attribute harus antara :min dan :max.',
        'string' => 'Panjang :attribute harus di antara :min sampai :max karakter.',
    ],
    'boolean' => 'Kolom :attribute harus benar atau salah.',
    'can' => 'Kolom :attribute berisi nilai yang tidak diizinkan.',
    'confirmed' => 'Kolom :attribute tidak cocok dengan konfirmasi.',
    'contains' => 'Kolom :attribute harus berisi nilai wajib.',
    'current_password' => 'Password saat ini salah.',
    'date' => 'Kolom :attribute bukan tanggal yang valid.',
    'date_equals' => 'Kolom :attribute harus tanggal sama dengan :date.',
    'date_format' => 'Kolom :attribute tidak cocok dengan format :format.',
    'decimal' => 'Kolom :attribute harus memiliki :decimal angka desimal.',
    'declined' => 'Kolom :attribute harus ditolak.',
    'declined_if' => 'Kolom :attribute harus ditolak ketika :other bernilai :value.',
    'different' => 'Kolom :attribute dan :other harus berbeda.',
    'digits' => 'Kolom :attribute harus terdiri dari :digits angka.',
    'digits_between' => 'Kolom :attribute harus terdiri dari :min sampai :max angka.',
    'dimensions' => 'Kolom :attribute tidak memiliki dimensi gambar yang valid.',
    'distinct' => 'Kolom :attribute memiliki nilai duplikat.',
    'doesnt_end_with' => 'Kolom :attribute tidak boleh diakhiri dengan :values.',
    'doesnt_start_with' => 'Kolom :attribute tidak boleh diawali dengan :values.',
    'email' => 'Kolom :attribute harus alamat surel yang valid.',
    'ends_with' => 'Kolom :attribute harus diakhiri dengan salah satu dari: :values.',
    'entangled' => 'Kolom :attribute tidak valid.',
    'enum' => 'Kolom :attribute yang dipilih tidak valid.',
    'exists' => 'Kolom :attribute yang dipilih tidak valid.',
    'extensions' => 'Kolom :attribute harus memiliki salah satu ekstensi: :values.',
    'file' => 'Kolom :attribute harus berupa berkas.',
    'filled' => 'Kolom :attribute wajib diisi.',
    'gt' => [
        'array' => 'Kolom :attribute harus memiliki lebih dari :value item.',
        'file' => 'Ukuran berkas :attribute harus lebih besar dari :value kilobita.',
        'numeric' => 'Nilai :attribute harus lebih besar dari :value.',
        'string' => 'Panjang :attribute harus lebih besar dari :value karakter.',
    ],
    'gte' => [
        'array' => 'Kolom :attribute harus memiliki :value item atau lebih.',
        'file' => 'Ukuran berkas :attribute harus lebih besar dari atau sama dengan :value kilobita.',
        'numeric' => 'Nilai :attribute harus lebih besar dari atau sama dengan :value.',
        'string' => 'Panjang :attribute harus lebih besar dari atau sama dengan :value karakter.',
    ],
    'hex_color' => 'Kolom :attribute harus kode warna hex yang valid.',
    'image' => 'Kolom :attribute harus gambar.',
    'in' => 'Kolom :attribute yang dipilih tidak valid.',
    'in_array' => 'Kolom :attribute harus ada di dalam :other.',
    'in_array_keys' => 'Kolom :attribute harus berupa array yang berisi pasangan kunci dari :values.',
    'integer' => 'Kolom :attribute harus bilangan bulat.',
    'ip' => 'Kolom :attribute harus alamat IP yang valid.',
    'ipv4' => 'Kolom :attribute harus alamat IPv4 yang valid.',
    'ipv6' => 'Kolom :attribute harus alamat IPv6 yang valid.',
    'json' => 'Kolom :attribute harus teks JSON yang valid.',
    'list' => 'Kolom :attribute harus berupa list.',
    'lowercase' => 'Kolom :attribute harus huruf kecil.',
    'lt' => [
        'array' => 'Kolom :attribute harus memiliki kurang dari :value item.',
        'file' => 'Ukuran berkas :attribute harus kurang dari :value kilobita.',
        'numeric' => 'Nilai :attribute harus kurang dari :value.',
        'string' => 'Panjang :attribute harus kurang dari :value karakter.',
    ],
    'lte' => [
        'array' => 'Kolom :attribute tidak boleh melebihi :value item.',
        'file' => 'Ukuran berkas :attribute harus kurang dari atau sama dengan :value kilobita.',
        'numeric' => 'Nilai :attribute harus kurang dari atau sama dengan :value.',
        'string' => 'Panjang :attribute harus kurang dari atau sama dengan :value karakter.',
    ],
    'mac_address' => 'Kolom :attribute harus alamat MAC yang valid.',
    'max' => [
        'array' => 'Kolom :attribute tidak boleh memiliki lebih dari :max item.',
        'file' => 'Ukuran berkas :attribute maksimal :max kilobita.',
        'numeric' => 'Nilai :attribute maksimal :max.',
        'string' => 'Panjang :attribute maksimal :max karakter.',
    ],
    'max_digits' => 'Kolom :attribute tidak boleh lebih dari :max angka.',
    'mimes' => 'Kolom :attribute harus berupa berkas tipe: :values.',
    'mimetypes' => 'Kolom :attribute harus berupa berkas tipe: :values.',
    'min' => [
        'array' => 'Kolom :attribute minimal :min item.',
        'file' => 'Ukuran berkas :attribute minimal :min kilobita.',
        'numeric' => 'Nilai :attribute minimal :min.',
        'string' => 'Panjang :attribute minimal :min karakter.',
    ],
    'min_digits' => 'Kolom :attribute minimal :min angka.',
    'missing' => 'Kolom :attribute tidak boleh ada.',
    'missing_if' => 'Kolom :attribute tidak boleh ada ketika :other bernilai :value.',
    'missing_unless' => 'Kolom :attribute tidak boleh ada kecuali :other bernilai :value.',
    'missing_with' => 'Kolom :attribute tidak boleh ada jika :values ada.',
    'missing_with_all' => 'Kolom :attribute tidak boleh ada jika :values semua ada.',
    'multiple_of' => 'Kolom :attribute harus kelipatan :value.',
    'not_in' => 'Kolom :attribute yang dipilih tidak valid.',
    'not_regex' => 'Format :attribute tidak valid.',
    'numeric' => 'Kolom :attribute harus berupa angka.',
    'password' => [
        'letters' => 'Kolom :attribute harus memiliki huruf.',
        'mixed' => 'Kolom :attribute harus memiliki huruf kapital dan huruf kecil.',
        'numbers' => 'Kolom :attribute harus memiliki minimal satu angka.',
        'symbols' => 'Kolom :attribute harus memiliki simbol.',
        'uncompromised' => 'Password :attribute muncul di daftar password bocor; pilih password lain.',
    ],
    'present' => 'Kolom :attribute wajib ada.',
    'present_if' => 'Kolom :attribute wajib ada ketika :other bernilai :value.',
    'present_unless' => 'Kolom :attribute wajib ada kecuali :other bernilai :value.',
    'present_with' => 'Kolom :attribute wajib ada jika :values ada.',
    'present_with_all' => 'Kolom :attribute wajib ada jika :values semua ada.',
    'prohibited' => 'Kolom :attribute tidak boleh diisi.',
    'prohibited_if' => 'Kolom :attribute tidak boleh diisi ketika :other bernilai :value.',
    'prohibited_if_accepted' => 'Kolom :attribute tidak boleh diisi jika :other disetujui.',
    'prohibited_if_declined' => 'Kolom :attribute tidak boleh diisi jika :other ditolak.',
    'prohibited_if_has' => 'Kolom :attribute tidak boleh diisi jika atribut :other berisi nilai yang dilarang.',
    'prohibited_if_not_has' => 'Kolom :attribute tidak boleh diisi jika atribut :other tidak berisi nilai yang dilarang.',
    'prohibited_unless' => 'Kolom :attribute tidak boleh diisi kecuali :other bernilai :value.',
    'prohibits' => 'Kolom :attribute melarang terisinya :other.',
    'regex' => 'Format :attribute tidak valid.',
    'required' => 'Kolom :attribute wajib diisi.',
    'required_array_keys' => 'Kolom :attribute wajib berupa array dengan kunci :values.',
    'required_if' => 'Kolom :attribute wajib diisi ketika :other bernilai :value.',
    'required_if_accepted' => 'Kolom :attribute wajib diisi jika :other disetujui.',
    'required_if_declined' => 'Kolom :attribute wajib diisi jika :other ditolak.',
    'required_if_has' => 'Kolom :attribute wajib diisi jika atribut :other berisi nilai yang diwajibkan.',
    'required_if_not_has' => 'Kolom :attribute wajib diisi jika atribut :other tidak berisi nilai yang diwajibkan.',
    'required_unless' => 'Kolom :attribute wajib diisi kecuali :other bernilai :value.',
    'required_with' => 'Kolom :attribute wajib diisi jika :values ada.',
    'required_with_all' => 'Kolom :attribute wajib diisi jika :values semua ada.',
    'required_without' => 'Kolom :attribute wajib diisi jika :values tidak ada.',
    'required_without_all' => 'Kolom :attribute wajib diisi jika seluruh :values tidak ada.',
    'same' => 'Kolom :attribute dan :other harus sama.',
    'size' => [
        'array' => 'Kolom :attribute harus memiliki tepat :size item.',
        'file' => 'Ukuran berkas :attribute harus tepat :size kilobita.',
        'numeric' => 'Nilai :attribute harus tepat :size.',
        'string' => 'Panjang :attribute harus tepat :size karakter.',
    ],
    'starts_with' => 'Kolom :attribute harus diawali dengan salah satu dari: :values.',
    'string' => 'Kolom :attribute harus string.',
    'timezone' => 'Kolom :attribute harus zona waktu yang valid.',
    'ulid' => 'Kolom :attribute harus ULID yang valid.',
    'unique' => 'Kolom :attribute sudah dipakai.',
    'uploaded' => 'Kolom :attribute gagal diunggah; periksa kembali berkas.',
    'uppercase' => 'Kolom :attribute harus huruf besar.',
    'url' => 'Format :attribute tidak valid.',
    'uuid' => 'Kolom :attribute harus UUID yang valid.',

    /*
    |--------------------------------------------------------------------------
    | Alias Atribut
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'username' => [
            'exists' => 'Username atau password salah (FR-02).',
        ],
        'aktif' => [
            'accepted_if' => 'Akun staf dinonaktifkan oleh pemilik; hubungi pemilik toko (FR-05).',
        ],
        'kode_sku' => [
            'required' => 'Kode SKU wajib diisi (FR-04).',
            'unique' => 'Kode SKU sudah dipakai barang aktif lain (AB-07).',
            'max' => 'Kode SKU maksimal :max karakter.',
        ],
        'nama' => [
            'required' => 'Nama wajib diisi (FR-04).',
            'max' => 'Nama maksimal :max karakter.',
        ],
        'kategori' => [
            'required' => 'Kategori wajib dipilih (daftar tetap Q-09).',
            'in' => 'Kategori harus salah satu dari: Makanan, Minuman, Obat, Kebutuhan Rumah, Lainnya.',
        ],
        'satuan' => [
            'required' => 'Satuan wajib diisi (FR-04).',
        ],
        'stok_minimum' => [
            'required' => 'Stok minimum wajib diisi (AB-04).',
            'integer' => 'Stok minimum harus bilangan bulat.',
            'min' => 'Stok minimum tidak boleh negatif.',
        ],
        'nomor_batch' => [
            'required' => 'Nomor batch wajib diisi (FR-05).',
            'unique' => 'Nomor batch sudah dipakai pada barang ini (AB-07).',
        ],
        'jumlah' => [
            'required' => 'Jumlah wajib diisi (FR-06).',
            'integer' => 'Jumlah harus bilangan bulat.',
            'min' => 'Jumlah tidak boleh kurang dari :min (FR-06).',
        ],
        'tanggal_kedaluwarsa' => [
            'required' => 'Tanggal kedaluwarsa wajib diisi (FR-05).',
            'date' => 'Tanggal kedaluwarsa tidak valid (YYYY-MM-DD).',
        ],
        'tanggal' => [
            'required' => 'Kolom :attribute wajib diisi.',
            'date' => 'Kolom :attribute tidak valid.',
        ],
        'catatan' => [
            'required' => 'Alasan penyesuaian wajib diisi (BR-05).',
        ],
        'status_penanganan' => [
            'required' => 'Jenis tindakan penanganan wajib dipilih (FR-12).',
            'in' => 'Tindakan harus Dimusnahkan, Dikembalikan ke Pemasok, atau Lainnya.',
        ],
        'catatan_penanganan' => [
            'required' => 'Catatan penanganan wajib diisi bila tindakan Lainnya (FR-12).',
        ],
        'barang_id' => [
            'required' => 'Kode barang wajib dipilih.',
            'exists' => 'Barang tidak ditemukan atau sudah terhapus.',
        ],
        'batch_id' => [
            'required' => 'Kode batch wajib dipilih.',
            'exists' => 'Batch tidak ditemukan atau sudah terhapus.',
        ],
        'jenis' => [
            'required' => 'Jenis transaksi wajib dipilih (masuk/keluar/penyesuaian).',
            'in' => 'Jenis transaksi harus salah satu dari: masuk, keluar, penyesuaian.',
        ],
        'berkas' => [
            'required' => 'Berkas Excel wajib dipilih (FR-15).',
            'mimes' => 'Format berkas harus .xlsx atau .xls (BR-06).',
            'max' => 'Ukuran berkas melebihi batas pengaturan (BR-06).',
        ],
        'lampiran' => [
            'required' => 'Foto wajib dipilih (FR-14).',
            'mimes' => 'Foto harus berformat JPG, PNG, atau JPEG (Q-05).',
            'max' => 'Ukuran foto melebihi batas pengaturan (Q-05).',
        ],
        'peran' => [
            'in' => 'Peran harus pemilik atau staf.',
        ],
        'password' => [
            'required' => 'Password wajib diisi (FR-02).',
        ],
        'format' => [
            'in' => 'Format ekspor harus xlsx atau pdf (FR-16).',
        ],
        'id' => [
            'required' => 'Pengidentifikasi massal wajib ada.',
            'array' => 'Pengidentifikasi massal harus berupa daftar ID.',
        ],
        'dari' => [
            'date' => 'Tanggal mulai tidak valid (FR-17).',
        ],
        'sampai' => [
            'date' => 'Tanggal akhir tidak valid (FR-17).',
        ],
        'token' => [
            'required' => 'Token konfirmasi impor wajib diisi (FR-15).',
        ],
        'ambang_peringatan_hari' => [
            'integer' => 'Ambang peringatan hari harus bilangan bulat.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Alias Atribut
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'nama' => 'nama',
        'username' => 'username',
        'password' => 'password',
        'kode_sku' => 'Kode SKU',
        'nama_barang' => 'nama barang',
        'kategori' => 'kategori',
        'satuan' => 'satuan',
        'stok_minimum' => 'stok minimum',
        'nomor_batch' => 'nomor batch',
        'jumlah' => 'jumlah',
        'jumlah_awal' => 'jumlah awal',
        'tanggal_kedaluwarsa' => 'tanggal kedaluwarsa',
        'barang_id' => 'barang',
        'batch_id' => 'batch',
        'jenis' => 'jenis transaksi',
        'catatan' => 'catatan',
        'status' => 'status',
        'status_penanganan' => 'tindakan penanganan',
        'catatan_penanganan' => 'catatan penanganan',
        'berkas' => 'berkas Excel',
        'lampiran' => 'foto',
        'peran' => 'peran',
        'aktif' => 'status aktif',
        'format' => 'format ekspor',
        'dari' => 'tanggal mulai',
        'sampai' => 'tanggal akhir',
        'token' => 'token konfirmasi',
        'ambang_peringatan_hari' => 'ambang peringatan hari',
        'ids' => 'daftar pengidentifikasi',
    ],
];
