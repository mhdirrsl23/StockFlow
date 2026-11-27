<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Services\LaporanExportService;
use App\Support\Aktivitas;
use App\Support\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

final class LaporanController extends Controller
{
    public function __construct(private readonly LaporanExportService $laporan) {}

    public function stok(ReportRequest $request): JsonResponse
    {
        $laporan = $this->laporan->dataStok($request->kategori());

        return ApiResponse::ok($laporan, 'Laporan stok ditemukan');
    }

    public function transaksi(ReportRequest $request): JsonResponse
    {
        $laporan = $this->laporan->dataTransaksi($request->dari(), $request->sampai(), $request->kategori());

        return ApiResponse::ok($laporan, 'Laporan transaksi ditemukan');
    }

    public function kedaluwarsa(ReportRequest $request): JsonResponse
    {
        $laporan = $this->laporan->dataKedaluwarsa($request->dari(), $request->sampai(), $request->status());

        return ApiResponse::ok($laporan, 'Laporan kedaluwarsa ditemukan');
    }

    public function laris(ReportRequest $request): JsonResponse
    {
        $laporan = $this->laporan->dataLaris($request->dari(), $request->sampai());

        return ApiResponse::ok($laporan, 'Laporan produk terlaris ditemukan');
    }

    public function ekspor(ReportRequest $request): Response
    {
        $format = strtolower((string) $request->input('format', 'xlsx'));
        $jenis = (string) $request->input('jenis', 'stok');

        $laporan = match ($jenis) {
            'transaksi' => $this->laporan->dataTransaksi($request->dari(), $request->sampai(), $request->kategori()),
            'kedaluwarsa' => $this->laporan->dataKedaluwarsa($request->dari(), $request->sampai(), $request->status()),
            'laris' => $this->laporan->dataLaris($request->dari(), $request->sampai()),
            default => $this->laporan->dataStok($request->kategori()),
        };

        Aktivitas::catat($request->user(), 'ubah', 'laporan', null, 'Ekspor laporan '.$jenis.' sebagai '.$format);

        return $format === 'pdf'
            ? $this->unduhPdf($laporan)
            : $this->unduhXlsx($laporan);
    }

    private function unduhXlsx(array $laporan): Response
    {
        $spreadsheet = new Spreadsheet;
        $worksheet = $spreadsheet->getActiveSheet();

        $worksheet->fromArray($laporan['header'], null, 'A1');
        $worksheet->fromArray($laporan['rows'], null, 'A2');

        $dipilih = 'A1:'.chr(64 + count($laporan['header'])).'1';
        $worksheet->getStyle($dipilih)->getFont()->setBold(true);

        foreach (range('A', 'L') as $kolom) {
            $worksheet->getColumnDimension($kolom)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->stream(function () use ($writer): void {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$laporan['nama_berkas']}.xlsx\"",
        ]);
    }

    private function unduhPdf(array $laporan): Response
    {
        return Pdf::loadHTML(view('laporan.generik', [
            'judul' => $laporan['judul'],
            'judul_tabel' => $laporan['judul_tabel'],
            'header' => $laporan['header'],
            'baris' => $laporan['rows'],
            'dibuat_pada' => Str::replaceFirst(' ', ' ', now()->toDateTimeString()),
        ])->render())
            ->stream("{$laporan['nama_berkas']}.pdf", [
                'Content-Disposition' => "attachment; filename=\"{$laporan['nama_berkas']}.pdf\"",
            ]);
    }
}
