<?php

namespace Database\Seeders;

use App\Models\BatchStok;
use App\Models\Barang;
use App\Models\MutasiStok;
use App\Models\Notifikasi;
use App\Models\Pengaturan;
use App\Models\RiwayatAktivitas;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Data demo sehari-hari toko, diporting dari skema SQL dengan tanggal
 * relatif terhadap hari WIB (scenario FR-20 / BR-02 / FR-25).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $pemilik = User::query()->create([
            'nama' => 'Budi Santoso',
            'username' => 'pemilik',
            'password' => 'password',
            'peran' => 'pemilik',
            'aktif' => true,
        ]);

        $staf = User::query()->create([
            'nama' => 'Rina Wulandari',
            'username' => 'staf',
            'password' => 'password',
            'peran' => 'staf',
            'aktif' => true,
        ]);

        foreach ([
            'ambang_peringatan_hari' => '30',
            'maks_ukuran_lampiran_mb' => '2',
            'maks_ukuran_impor_mb' => '5',
            'maks_baris_impor' => '1000',
        ] as $kunci => $nilai) {
            Pengaturan::atur($kunci, $nilai);
        }

        $hariIni = Carbon::today();

        $barang = [];

        foreach ([
            ['SKU-MKN-001', 'Indomie Goreng', 'Makanan', 'pcs', 50],
            ['SKU-MNM-001', 'Ultra Milk Full Cream 250ml', 'Minuman', 'pcs', 20],
            ['SKU-UBT-001', 'Paracetamol 500mg', 'Obat', 'box', 10],
            ['SKU-RMH-001', 'Sunlight 755ml', 'Kebutuhan Rumah', 'pcs', 15],
            ['SKU-LNY-001', 'Tisu Basah', 'Lainnya', 'pack', 10],
        ] as [$sku, $nama, $kategori, $satuan, $minimum]) {
            $barang[$sku] = Barang::query()->create([
                'kode_sku' => $sku,
                'nama' => $nama,
                'kategori' => $kategori,
                'satuan' => $satuan,
                'stok_minimum' => $minimum,
                'created_by' => $pemilik->id,
            ]);
        }

        $mgn = $barang['SKU-MKN-001'];
        $mlk = $barang['SKU-MNM-001'];
        $prc = $barang['SKU-UBT-001'];
        $sun = $barang['SKU-RMH-001'];

        $batch = [
            '1' => BatchStok::query()->create([
                'barang_id' => $mgn->id,
                'nomor_batch' => 'MGN-2411A',
                'jumlah_awal' => 100,
                'jumlah_sisa' => 100,
                'tanggal_kedaluwarsa' => $hariIni->copy()->addDays(31)->toDateString(),
                'status' => 'Aman',
                'created_by' => $pemilik->id,
            ]),
            '2' => BatchStok::query()->create([
                'barang_id' => $mgn->id,
                'nomor_batch' => 'MGN-2411B',
                'jumlah_awal' => 50,
                'jumlah_sisa' => 50,
                'tanggal_kedaluwarsa' => $hariIni->copy()->addDays(30)->toDateString(),
                'status' => 'Mendekati Kedaluwarsa',
                'created_by' => $pemilik->id,
            ]),
            '3' => BatchStok::query()->create([
                'barang_id' => $mlk->id,
                'nomor_batch' => 'MLK-2411A',
                'jumlah_awal' => 40,
                'jumlah_sisa' => 40,
                'tanggal_kedaluwarsa' => $hariIni->copy()->addDays(25)->toDateString(),
                'status' => 'Mendekati Kedaluwarsa',
                'created_by' => $staf->id,
            ]),
            '4' => BatchStok::query()->create([
                'barang_id' => $mlk->id,
                'nomor_batch' => 'MLK-2411B',
                'jumlah_awal' => 30,
                'jumlah_sisa' => 30,
                'tanggal_kedaluwarsa' => $hariIni->copy()->subDay()->toDateString(),
                'status' => 'Kedaluwarsa',
                'created_by' => $staf->id,
            ]),
            '5' => BatchStok::query()->create([
                'barang_id' => $prc->id,
                'nomor_batch' => 'PRC-2410A',
                'jumlah_awal' => 20,
                'jumlah_sisa' => 20,
                'tanggal_kedaluwarsa' => $hariIni->copy()->addDays(5)->toDateString(),
                'status' => 'Mendekati Kedaluwarsa',
                'created_by' => $pemilik->id,
            ]),
            '6' => BatchStok::query()->create([
                'barang_id' => $prc->id,
                'nomor_batch' => 'PRC-2410B',
                'jumlah_awal' => 15,
                'jumlah_sisa' => 0,
                'tanggal_kedaluwarsa' => $hariIni->copy()->subDays(5)->toDateString(),
                'status' => 'Ditangani',
                'status_penanganan' => 'Dimusnahkan',
                'catatan_penanganan' => 'Dimusnahkan bersama sampah farmasi, disaksikan staf gudang.',
                'created_by' => $pemilik->id,
            ]),
            '7' => BatchStok::query()->create([
                'barang_id' => $sun->id,
                'nomor_batch' => 'SUN-2412A',
                'jumlah_awal' => 24,
                'jumlah_sisa' => 5,
                'tanggal_kedaluwarsa' => $hariIni->copy()->addDays(60)->toDateString(),
                'status' => 'Aman',
                'created_by' => $staf->id,
            ]),
        ];

        $jam = Carbon::now()->subHours(3);

        foreach ([
            ['1', 'masuk', 100, 'Stok awal', $pemilik->id],
            ['2', 'masuk', 50, 'Stok awal', $pemilik->id],
            ['3', 'masuk', 40, 'Stok awal', $staf->id],
            ['4', 'masuk', 30, 'Stok awal', $staf->id],
            ['5', 'masuk', 20, 'Stok awal', $pemilik->id],
            ['6', 'masuk', 15, 'Stok awal', $pemilik->id],
            ['6', 'penanganan', 15, 'Dimusnahkan karena kedaluwarsa', $pemilik->id],
            ['7', 'masuk', 24, 'Stok awal', $staf->id],
            ['7', 'keluar', 19, 'Penjualan harian', $staf->id],
        ] as [$kunci, $jenis, $jumlah, $catatan, $userId]) {
            MutasiStok::query()->create([
                'batch_id' => $batch[$kunci]->id,
                'jenis' => $jenis,
                'jumlah' => $jumlah,
                'catatan' => $catatan,
                'user_id' => $userId,
                'waktu' => $jam->copy()->addMinutes(count($batch)),
            ]);
        }

        foreach ([
            [$pemilik->id, 'login', null, null, 'Login pemilik'],
            [$staf->id, 'login', null, null, 'Login staf gudang'],
            [$pemilik->id, 'tambah', 'barang', $mgn->id, 'Tambah barang Indomie Goreng'],
            [$staf->id, 'tambah', 'batch_stok', $batch['7']->id, 'Tambah batch SUN-2412A'],
            [$staf->id, 'pengeluaran_stok', 'batch_stok', $batch['7']->id, 'Keluar 19 pcs Sunlight secara FEFO'],
            [$pemilik->id, 'penanganan_kedaluwarsa', 'batch_stok', $batch['6']->id, 'Batch PRC-2410B dimusnahkan'],
            [$pemilik->id, 'ubah_pengaturan', 'pengaturan', null, 'Atur ambang peringatan = 30 hari'],
        ] as [$userId, $aksi, $entitas, $entitasId, $keterangan]) {
            RiwayatAktivitas::query()->create([
                'user_id' => $userId,
                'aksi' => $aksi,
                'entitas' => $entitas,
                'entitas_id' => $entitasId,
                'keterangan' => $keterangan,
                'waktu' => $jam,
            ]);
        }

        foreach ([
            ['Mendekati Kedaluwarsa', $mgn->id, $batch['2']->id, 'Batch MGN-2411B (Indomie Goreng) mendekati kedaluwarsa: 30 hari lagi.'],
            ['Mendekati Kedaluwarsa', $mlk->id, $batch['3']->id, 'Batch MLK-2411A (Ultra Milk 250ml) mendekati kedaluwarsa: 25 hari lagi.'],
            ['Kedaluwarsa', $mlk->id, $batch['4']->id, 'Batch MLK-2411B (Ultra Milk 250ml) sudah kedaluwarsa. Perlu ditangani.'],
            ['Stok Menipis', $sun->id, null, 'Stok Sunlight 755ml menipis: sisa 5 pcs, minimum 15 pcs.'],
        ] as [$jenis, $barangId, $batchId, $pesan]) {
            Notifikasi::query()->create([
                'jenis' => $jenis,
                'barang_id' => $barangId,
                'batch_id' => $batchId,
                'pesan' => $pesan,
                'tanggal' => $hariIni->toDateString(),
                'created_at' => $jam,
            ]);
        }
    }
}
