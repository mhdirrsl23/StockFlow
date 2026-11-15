<?php

namespace App\Http\Controllers;

use App\Models\Notifikasi;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    // Mengambil Notifikasi Hari Ini / Belum Ditampilkan
    public function index()
    {
        $notifikasi = Notifikasi::with(['barang', 'batchStok'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['status' => true, 'data' => $notifikasi], 200);
    }

    // Tandai Notifikasi Sudah Ditampilkan di Ponsel
    public function tandaiTampil($id)
    {
        $notif = Notifikasi::find($id);

        if ($notif) {
            $notif->update(['ditampilkan_pada' => now()]);
        }

        return response()->json(['status' => true], 200);
    }
}
