{{-- Laporan bulanan untuk dompdf. DejaVu Sans dipakai karena tertanam di
     dompdf — tanpa itu karakter non-latin jatuh ke kotak kosong. --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #111; padding: 24px 28px; }
    h1 { font-size: 15px; text-align: center; letter-spacing: 1px; }
    .subtitle { text-align: center; font-size: 10px; font-weight: bold; margin-top: 2px; }
    .period { text-align: center; color: #555; margin: 2px 0 14px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #1a1a1a; color: #fff; font-size: 8px; letter-spacing: .5px; padding: 5px 6px; text-align: left; }
    td { padding: 4px 6px; border-bottom: 1px solid #ddd; vertical-align: top; }
    .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .total-row td { border-top: 2px solid #1a1a1a; border-bottom: none; font-weight: bold; }
    .section { margin-top: 14px; page-break-inside: avoid; }
    .section h2 { font-size: 9px; background: #1a1a1a; color: #fff; padding: 4px 6px; letter-spacing: .5px; }
    .section table { width: 55%; }
    .net { margin-top: 16px; border-top: 2px solid #1a1a1a; border-bottom: 3px double #1a1a1a; padding: 8px 6px; page-break-inside: avoid; }
    .net table { width: 100%; }
    .net td { border: none; font-size: 12px; font-weight: bold; padding: 0 6px; }
    .note { color: #666; font-size: 8px; margin-top: 4px; font-style: italic; }
    .sign { margin-top: 36px; width: 100%; page-break-inside: avoid; }
    .sign td { border: none; text-align: center; width: 33%; padding-top: 0; }
    .sign .line { padding-top: 48px; }
</style>
</head>
<body>
    <h1>{{ mb_strtoupper($clinicName) }}</h1>
    <div class="subtitle">LAPORAN PASIEN &amp; PENDAPATAN KLINIK</div>
    <div class="period">Periode {{ \Carbon\Carbon::parse($report['from'])->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($report['to'])->format('d/m/Y') }}</div>

    <table>
        <thead>
            <tr>
                <th>NO</th>
                <th>TERAPIS</th>
                <th>NAMA PASIEN</th>
                <th>TINDAKAN</th>
                <th>PRODUK</th>
                <th>TANGGAL</th>
                <th class="num">TREATMENT (IDR)</th>
                <th class="num">PRODUK (IDR)</th>
                <th class="num">TOTAL (IDR)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $index => $line)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $line['therapist_name'] ?? '-' }}</td>
                    <td>{{ $line['patient_name'] ?? '-' }}</td>
                    <td>{{ $line['services'] ?: '-' }}</td>
                    <td>{{ $line['products'] ?: '-' }}</td>
                    <td>{{ \Carbon\Carbon::parse($line['issued_at'])->format('d/m/Y') }}</td>
                    <td class="num">{{ number_format($line['treatment_amount'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($line['product_amount'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($line['total'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center;color:#777">Tidak ada transaksi lunas pada periode ini.</td></tr>
            @endforelse
            <tr class="total-row">
                <td colspan="6" style="text-align:right">TOTAL</td>
                <td class="num">{{ number_format($report['totals']['treatment'], 0, ',', '.') }}</td>
                <td class="num">{{ number_format($report['totals']['product'], 0, ',', '.') }}</td>
                <td class="num">{{ number_format($report['totals']['revenue'], 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section">
        <h2>RINCIAN DANA (PER METODE BAYAR)</h2>
        <table>
            @foreach ($report['payments'] as $payment)
                <tr>
                    <td>{{ $payment['method_label'] }}</td>
                    <td class="num">{{ number_format($payment['total'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td>TOTAL DANA</td>
                <td class="num">{{ number_format(collect($report['payments'])->sum('total'), 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>RINCIAN PENGELUARAN</h2>
        <table>
            @forelse ($report['expenses']['by_category'] as $expense)
                <tr>
                    <td>{{ $expense['category_label'] }}</td>
                    <td class="num">{{ number_format($expense['total'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="2" style="color:#777">Belum ada pengeluaran tercatat.</td></tr>
            @endforelse
            <tr class="total-row">
                <td>TOTAL PENGELUARAN</td>
                <td class="num">{{ number_format($report['expenses']['total'], 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>FEE TERAPIS (PRATINJAU)</h2>
        <table>
            @forelse ($report['commission']['rows'] as $line)
                <tr>
                    <td>{{ $line['therapist_name'] }}</td>
                    <td class="num">{{ number_format($line['total'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="2" style="color:#777">Tidak ada transaksi bertanda terapis.</td></tr>
            @endforelse
            <tr class="total-row">
                <td>TOTAL FEE</td>
                <td class="num">{{ number_format($report['commission']['total'], 0, ',', '.') }}</td>
            </tr>
        </table>
        <p class="note">Fee di atas pratinjau — ikut mengurangi keuntungan setelah dibukukan sebagai pengeluaran.</p>
    </div>

    <div class="net">
        <table>
            <tr>
                <td>KEUNTUNGAN BERSIH (PENDAPATAN &minus; PENGELUARAN)</td>
                <td class="num">Rp {{ number_format($report['net_profit'], 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <table class="sign">
        <tr>
            <td>Diketahui Oleh</td>
            <td>Disetujui Oleh</td>
            <td>Dibuat Oleh</td>
        </tr>
        <tr>
            <td class="line">(............................)</td>
            <td class="line">(............................)</td>
            <td class="line">(............................)</td>
        </tr>
    </table>
</body>
</html>
