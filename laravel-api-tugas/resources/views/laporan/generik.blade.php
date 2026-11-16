<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }}</title>
    <style>
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11px;
            color: #1f2937;
            margin: 28px 32px;
        }
        .kop { border-bottom: 3px solid #15803d; margin-bottom: 14px; padding-bottom: 10px; }
        .kop h1 { margin: 0; font-size: 20px; color: #15803d; letter-spacing: 2px; }
        .kop .subjudul { margin: 2px 0 0; font-size: 10px; color: #6b7280; }
        h2 { font-size: 13px; margin: 16px 0 8px; color: #111827; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #dcfce7; font-weight: 700; font-size: 10px; color: #14532d; }
        tr:nth-child(even) td { background: #f9fafb; }
        .ket { margin-top: 14px; font-size: 9px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>StockFlow</h1>
        <div class="subjudul">Sistem Monitoring Stok Kedaluwarsa — Stratejik Inventory for Store Efficiency</div>
    </div>

    <h2>{{ $judul_tabel }}</h2>

    <table>
        <thead>
            <tr>
                @foreach ($header as $kolom)
                    <th>{{ $kolom }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($baris as $barisData)
                <tr>
                    @foreach ($barisData as $sel)
                        <td>{{ is_array($sel) ? json_encode($sel) : $sel }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($header) }}">Tidak ada data untuk rentang laporan ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="ket">
        Dibuat pada {{ $dibuat_pada }} (WIB) oleh aplikasi StockFlow — dok internal toko (NFR-08).
    </div>
</body>
</html>
