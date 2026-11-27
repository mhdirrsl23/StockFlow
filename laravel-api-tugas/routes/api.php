<?php

use App\Http\Controllers\BarangController;
use App\Http\Controllers\BatchStockController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpiryMonitoringController;
use App\Http\Controllers\ImporController;
use App\Http\Controllers\LampiranController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\MutasiStokController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\PenangananKedaluwarsaController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\PersonalAccessTokenController;
use App\Http\Controllers\RiwayatAktivitasController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

Route::post('login', [UsersController::class, 'login'])
                                                                                                                                                                                              ->middleware('throttle:login')
                                                                                                                                                                                              ->name('login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('logout', [UsersController::class, 'logout'])->name('logout');
    Route::get('me', [UsersController::class, 'me'])->name('me');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    Route::post('products/bulk-delete', [BarangController::class, 'bulkDelete'])->name('products.bulk-delete');
    Route::apiResource('products', BarangController::class)
        ->parameters(['products' => 'barang']);
    Route::get('products/{barang}/batches', [BatchStockController::class, 'untukBarang'])
        ->name('products.batches.index');
    Route::post('products/{barang}/batches', [BatchStockController::class, 'store'])
        ->name('products.batches.store');
    Route::get('products/{barang}/fefo', [ExpiryMonitoringController::class, 'fefo'])
        ->name('products.fefo');

    Route::post('batches/bulk-delete', [BatchStockController::class, 'bulkDelete'])->name('batches.bulk-delete');
    Route::apiResource('batches', BatchStockController::class)
        ->parameters(['batches' => 'batch'])
        ->only(['index', 'show', 'update', 'destroy']);

    Route::get('stock-transactions', [MutasiStokController::class, 'index'])->name('stock-transactions.index');
    Route::post('stock-transactions', [MutasiStokController::class, 'store'])->name('stock-transactions.store');

    Route::post('expiry-actions', [PenangananKedaluwarsaController::class, 'store'])->name('expiry-actions.store');

    Route::get('expiry-monitoring', [ExpiryMonitoringController::class, 'index'])->name('expiry-monitoring.index');
    Route::get('expiry-monitoring/perlu-ditangani', [ExpiryMonitoringController::class, 'perluDitangani'])
        ->name('expiry-monitoring.perlu-ditangani');

    Route::post('notifications/read-all', [NotifikasiController::class, 'tandaiSemuaDibaca'])
        ->name('notifications.read-all');
    Route::patch('notifications/{notifikasi}/read', [NotifikasiController::class, 'tandaiDibaca'])
        ->name('notifications.read');
    Route::get('notifications', [NotifikasiController::class, 'index'])->name('notifications.index');

    Route::prefix('imports/products')->name('imports.')->group(function (): void {
        Route::post('preview', [ImporController::class, 'preview'])->name('preview');
        Route::post('commit', [ImporController::class, 'commit'])->name('commit');
    });
    Route::get('imports', [ImporController::class, 'index'])->name('imports.index');
    Route::get('imports/{impor}', [ImporController::class, 'show'])->name('imports.show');

    Route::prefix('reports')->name('reports.')->middleware('role:pemilik')->group(function (): void {
        Route::get('stock', [LaporanController::class, 'stok'])->name('stock');
        Route::get('transactions', [LaporanController::class, 'transaksi'])->name('transactions');
        Route::get('expiry', [LaporanController::class, 'kedaluwarsa'])->name('expiry');
        Route::get('fast-moving', [LaporanController::class, 'laris'])->name('fast-moving');
        Route::get('export', [LaporanController::class, 'ekspor'])->name('export');
    });

    Route::get('riwayat-aktivitas', [RiwayatAktivitasController::class, 'index'])->name('riwayat-aktivitas.index');

    Route::get('pengaturan', [PengaturanController::class, 'index'])
        ->middleware('role:pemilik')->name('pengaturan.index');
    Route::put('pengaturan', [PengaturanController::class, 'update'])
        ->middleware('role:pemilik')->name('pengaturan.update');

    Route::post('batches/{batch}/attachments', [LampiranController::class, 'store'])
        ->name('lampiran.store');
    Route::get('attachments/{lampiran}/file', [LampiranController::class, 'file'])
        ->name('lampiran.file');
    Route::delete('attachments/{lampiran}', [LampiranController::class, 'destroy'])
        ->name('lampiran.destroy');

    Route::prefix('users')->middleware('role:pemilik')->name('users.')->group(function (): void {
        Route::get('', [UsersController::class, 'index'])->name('index');
        Route::post('', [UsersController::class, 'store'])->name('store');
        Route::put('{user}', [UsersController::class, 'update'])->name('update');
        Route::patch('{user}/aktif', [UsersController::class, 'ubahAktif'])->name('aktif');
        Route::delete('{user}', [UsersController::class, 'destroy'])->name('destroy');
    });

    Route::get('tokens', [PersonalAccessTokenController::class, 'index'])->name('tokens.index');
    Route::delete('tokens/others', [PersonalAccessTokenController::class, 'hapusLainnya'])
        ->name('tokens.hapus-lainnya');
    Route::delete('tokens/{token}', [PersonalAccessTokenController::class, 'destroy'])->name('tokens.destroy');
});
