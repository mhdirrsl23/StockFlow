<?php

namespace App\Http\Controllers;

use App\Http\Requests\BarangRequest;
use App\Models\Barang;
use App\Models\Notifikasi;
use App\Support\Aktivitas;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class BarangController extends Controller
{
    /**
     * FR-17: daftar barang + pencarian kode/nama + filter kategori + pagination.
     */
    public function index(Request $request): mixed
    {
        $query = Barang::query()
            ->cari($request->query('q'))
            ->kategori($request->query('kategori'))
            ->orderBy('nama');

        $paginasi = $query->paginate(min(100, max(1, (int) ($request->query('per_page', 15)))))
            ->appends($request->query());

        $stok = DB::table('v_stok_tersedia')->get()->keyBy('barang_id');

        $items = $paginasi->getCollection()
            ->map(fn (Barang $barang) => [
                'id' => $barang->id,
                'kode_sku' => $barang->kode_sku,
                'nama' => $barang->nama,
                'kategori' => $barang->kategori,
                'satuan' => $barang->satuan,
                'stok_minimum' => $barang->stok_minimum,
                'created_by' => $barang->created_by,
                'total_stok_tersedia' => (int) ($stok[$barang->id]->total_stok_tersedia ?? 0),
                'status_stok' => $stok[$barang->id]->status_stok ?? 'Stok Menipis',
                'created_at' => $barang->created_at,
                'updated_at' => $barang->updated_at,
            ]);

        return ApiResponse::ok([
            'items' => $items,
            'pagination' => [
                'halaman' => $paginasi->currentPage(),
                'per_halaman' => $paginasi->perPage(),
                'total_baris' => $paginasi->total(),
                'halaman_terakhir' => $paginasi->lastPage(),
            ],
        ], 'Daftar barang ditemukan');
    }

    /**
     * Store via mutasi masuk terpisah.
     */
    public function store(BarangRequest $request): mixed
    {
        $validasi = $request->validated();
        $user = $request->user();

        $barang = DB::transaction(function () use ($validasi, $user) {
            $baru = Barang::create([...$validasi, 'created_by' => $user->id]);
            Aktivitas::catat($user, 'tambah', 'barang', $baru->id, 'Tambah barang '.$baru->nama);

            return $baru;
        });

        return ApiResponse::created($this->bentuk($barang), 'Barang berhasil dibuat');
    }

    /**
     * FR-04: detail barang dengan list batch FEFO dan ringkasan stok.
     */
    public function show(Barang $barang): mixed
    {
        $batches = $barang->batches()->fefo()->with('lampiran')->limit(100)->get();

        $tercatat = DB::table('v_stok_tersedia')->where('barang_id', $barang->id)->first();

        $salinan = $this->bentuk($barang) + [
            'batches' => $batches->toArray(),
            'total_stok_tersedia' => (int) ($tercatat->total_stok_tersedia ?? 0),
            'status_stok' => $tercatat->status_stok ?? 'Stok Menipis',
        ];

        return ApiResponse::ok($salinan, 'Barang ditemukan');
    }

    public function update(BarangRequest $request, Barang $barang): mixed
    {
        $user = $request->user();

        $diperbarui = DB::transaction(function () use ($request, $barang, $user) {
            $barang->fill($request->validated());
            $barang->save();

            Aktivitas::catat($user, 'ubah', 'barang', $barang->id, 'Ubah barang '.$barang->nama);

            return $barang;
        });

        return ApiResponse::ok($this->bentuk($diperbarui), 'Barang berhasil diperbarui');
    }

    /**
     * AB-08: pemilik menghapus kapan pun; staf hanya barang pembuatannya sendiri.
     * BR-07: barang dengan batch aktif ditolak; nonaktifkan barang sesuai alur Android.
     */
    public function destroy(Request $request, Barang $barang): mixed
    {
        $user = $request->user();

        if (! $user->isPemilik() && $barang->created_by !== $user->id) {
            return ApiResponse::fail('Akses ditolak: staf hanya dapat menghapus barang yang ia buat sendiri (AB-08).', 403);
        }

        if ($barang->batches()->exists()) {
            return ApiResponse::fail('Barang masih memiliki batch aktif. Hapus batch terlebih dahulu atau nonaktifkan barang (BR-07).', 422);
        }

        return DB::transaction(function () use ($user, $barang) {
            $barang->deleted_by = $user->id;
            $barang->delete();

            // Notifikasi milik barang ini tidak berlaku lagi
            Notifikasi::query()->where('barang_id', $barang->id)->delete();

            Aktivitas::catat($user, 'hapus', 'barang', $barang->id, 'Hapus barang '.$barang->nama);

            return ApiResponse::ok(null, 'Barang dihapus — data tetap tersimpan untuk keterlacakan (BR-07)');
        });
    }

    /**
     * FR-18: hapus massal barang; hasil per item.
     */
    public function bulkDelete(Request $request): mixed
    {
        $ids = $request->input('ids', []);

        if (! is_array($ids) || $ids === []) {
            return ApiResponse::fail('Hapus massal membutuhkan daftar id barang (FR-18).', 422, [
                'ids' => ['Daftar id barang wajib diisi.'],
            ]);
        }

        $user = $request->user();
        $ids = array_map('intval', $ids);

        return DB::transaction(function () use ($user, $ids) {
            $hasil = [];

            foreach ($ids as $id) {
                $barang = Barang::query()->withTrashed()->find($id);

                if ($barang === null) {
                    $hasil[] = ['id' => $id, 'berhasil' => false, 'pesan' => 'Barang tidak ditemukan'];
                    continue;
                }

                if ($barang->deleted_at !== null) {
                    $hasil[] = ['id' => $id, 'berhasil' => false, 'pesan' => 'Barang sudah terhapus'];
                    continue;
                }

                if ($barang->batches()->exists()) {
                    $hasil[] = [
                        'id' => $id,
                        'berhasil' => false,
                        'pesan' => 'Barang masih memiliki batch aktif (BR-07)',
                    ];
                    continue;
                }

                if (! $user->isPemilik() && $barang->created_by !== $user->id) {
                    $hasil[] = [
                        'id' => $id,
                        'berhasil' => false,
                        'pesan' => 'Akses ditolak: staf hanya menghapus barang miliknya sendiri (AB-08)',
                    ];
                    continue;
                }

                $barang->deleted_by = $user->id;
                $barang->delete();

                Aktivitas::catat($user, 'hapus_massal', 'barang', $id, 'Hapus massal barang '.$barang->nama);

                $hasil[] = ['id' => $id, 'berhasil' => true, 'pesan' => 'Hapus berhasil'];
            }

            $berhasil = count(array_filter($hasil, fn ($r) => $r['berhasil']));

            return ApiResponse::ok([
                'total' => count($hasil),
                'berhasil' => $berhasil,
                'gagal' => count($hasil) - $berhasil,
                'hasil' => $hasil,
            ], 'Hapus massal barang diproses');
        });
    }

    /**
     * Format ringkas data barang; total stok tetap membaca view WIB (AB-01).
     */
    private function bentuk(Barang $barang): array
    {
        $tercatat = DB::table('v_stok_tersedia')->where('barang_id', $barang->id)->first();

        return [
            'id' => $barang->id,
            'kode_sku' => $barang->kode_sku,
            'nama' => $barang->nama,
            'kategori' => $barang->kategori,
            'satuan' => $barang->satuan,
            'stok_minimum' => $barang->stok_minimum,
            'created_by' => $barang->created_by,
            'total_stok_tersedia' => (int) ($tercatat->total_stok_tersedia ?? 0),
            'status_stok' => $tercatat->status_stok ?? 'Stok Menipis',
            'jumlah_batch_aktif' => $barang->batches()->count(),
            'created_at' => $barang->created_at?->toDateTimeString(),
            'updated_at' => $barang->updated_at?->toDateTimeString(),
        ];
    }
}
