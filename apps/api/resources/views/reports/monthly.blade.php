{{-- Laporan bulanan untuk dompdf.

     DejaVu Sans dipakai karena tertanam di dompdf — tanpa itu karakter
     non-latin jatuh ke kotak kosong.

     Catatan gaya: dompdf hanya mengerti CSS lama, jadi tata letaknya disusun
     dengan tabel dan bukan flex/grid. Warna tiap blok sama persis dengan versi
     layar dan Excel supaya orang yang memegang cetakan dan yang membuka
     aplikasi sedang melihat laporan yang sama. Angka tetap hitam di atas latar
     terang; warna hanya di kepala blok dan garis, jadi kalau tercetak
     hitam-putih pun susunannya masih terbaca. --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Laporan Pasien &amp; Pendapatan — {{ $clinicName }}</title>
<style>
    @page { margin: 20px 22px 42px; }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: "DejaVu Sans", sans-serif;
        font-size: 8px;
        color: #0b3b2e;
        line-height: 1.45;
    }

    /* Kepala laporan */
    .masthead { background: #0b3b2e; color: #fff; padding: 12px 16px; }
    .masthead table { width: 100%; }
    .masthead .clinic { font-size: 15px; font-weight: bold; letter-spacing: 1.5px; }
    .masthead .doc { font-size: 9px; letter-spacing: 2px; color: #9fd3bd; margin-top: 2px; }
    .masthead .period { font-size: 9px; color: #d6ece2; margin-top: 6px; }
    .masthead .meta { text-align: right; vertical-align: bottom; font-size: 7px; color: #9fd3bd; letter-spacing: .8px; }
    .masthead .meta strong { display: block; font-size: 9px; color: #fff; letter-spacing: 0; }
    .rule { height: 3px; background: #007a55; }

    /* Angka pembuka */
    .kpi { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 12px 0 4px; }
    .kpi td { width: 25%; padding: 8px 10px; vertical-align: top; }
    .kpi .label { font-size: 6.5px; letter-spacing: 1px; font-weight: bold; }
    .kpi .value { font-size: 13px; font-weight: bold; color: #0b3b2e; padding-top: 2px; }
    .kpi .sub { font-size: 6.5px; color: #6b7f78; }
    .kpi-revenue { background: #e4f5ed; border: 1px solid #007a55; }
    .kpi-revenue .label { color: #007a55; }
    .kpi-expense { background: #fdf0ea; border: 1px solid #b34a22; }
    .kpi-expense .label { color: #b34a22; }
    .kpi-profit { background: #e4f5ed; border: 1px solid #0b3b2e; }
    .kpi-profit .label { color: #0b3b2e; }
    .kpi-visit { background: #eaf1fa; border: 1px solid #1f5fa8; }
    .kpi-visit .label { color: #1f5fa8; }

    /* Tabel kunjungan */
    table.grid { width: 100%; border-collapse: collapse; }
    table.grid thead th {
        background: #007a55; color: #fff; font-size: 6.5px; letter-spacing: .6px;
        padding: 6px 5px; text-align: left; border-right: 1px solid #2e9273;
    }
    table.grid thead th:last-child { border-right: none; }
    table.grid tbody td { padding: 5px; border-bottom: 1px solid #d3e5dd; vertical-align: top; }
    table.grid tbody tr.alt td { background: #f5fbf8; }
    table.grid tfoot td {
        background: #e4f5ed; font-weight: bold; font-size: 8.5px;
        padding: 6px 5px; border-top: 2px solid #007a55;
    }
    .num { text-align: right; white-space: nowrap; }
    .idx { text-align: center; color: #6b7f78; }
    .pill {
        font-size: 6.5px; background: #eaf1fa; color: #1f5fa8;
        border: 1px solid #c3d8f0; padding: 1px 4px; border-radius: 6px; white-space: nowrap;
    }
    .muted { color: #6b7f78; }
    .empty { text-align: center; color: #6b7f78; font-style: italic; padding: 14px 0; }

    /* Blok rincian */
    .blocks { width: 100%; border-collapse: separate; border-spacing: 7px 0; margin-top: 12px; }
    .blocks > tbody > tr > td { width: 33.33%; vertical-align: top; }
    .block { border: 1px solid #d3e5dd; }
    .block h2 { font-size: 7px; letter-spacing: 1px; color: #fff; padding: 5px 8px; }
    .block table { width: 100%; border-collapse: collapse; }
    .block table td { padding: 4px 8px; border-bottom: 1px solid #eef5f1; }
    .block tr.sum td { font-weight: bold; border-bottom: none; border-top: 1px solid #d3e5dd; }
    .block .note { font-size: 6.5px; color: #6b7f78; font-style: italic; padding: 5px 8px 6px; }
    .h-fund { background: #1f5fa8; }
    .b-fund tr.sum td { background: #eaf1fa; color: #1f5fa8; }
    .h-expense { background: #b34a22; }
    .b-expense tr.sum td { background: #fdf0ea; color: #b34a22; }
    .h-fee { background: #8a6100; }
    .b-fee tr.sum td { background: #fbf4e3; color: #8a6100; }

    /* Keuntungan bersih */
    .net { margin-top: 12px; background: #0b3b2e; color: #fff; padding: 10px 14px; }
    .net table { width: 100%; }
    .net .label { font-size: 8px; letter-spacing: 1px; color: #9fd3bd; }
    .net .value { font-size: 16px; font-weight: bold; text-align: right; }
    .net .formula { font-size: 6.5px; color: #7fbfa4; }

    /* Tanda tangan */
    .sign { width: 100%; margin-top: 26px; border-collapse: collapse; page-break-inside: avoid; }
    .sign td { text-align: center; width: 25%; font-size: 7.5px; }
    .sign .role { font-weight: bold; padding-bottom: 44px; }
    /* Garisnya dipasang di dalam sel, bukan di selnya: kalau di sel, keempat
       garis bersambung jadi satu rule selebar halaman. */
    .sign .name { border-top: 1px solid #0b3b2e; margin: 0 18%; padding-top: 4px; color: #6b7f78; }

    .foot {
        position: fixed; bottom: 8px; left: 0; right: 0;
        font-size: 6.5px; color: #6b7f78; border-top: 1px solid #d3e5dd; padding-top: 4px;
    }
    .foot .right { float: right; }
    /* dompdf mengisi nomor halaman lewat counter, bukan lewat skrip. Jumlah
       total halaman sengaja tidak disebut: counter(pages) baru terisi kalau
       dokumennya dirender dua kali, dan hasilnya di sini selalu nol. */
    .foot .right:after { content: counter(page); }
</style>
</head>
<body>
@php
    $rp = fn ($value) => number_format((float) $value, 0, ',', '.');
    $summary = $report['summary'];
    $fundTotal = collect($report['payments'])->sum('total');
@endphp

<div class="masthead">
    <table>
        <tr>
            <td>
                <div class="clinic">{{ mb_strtoupper($clinicName) }}</div>
                <div class="doc">LAPORAN PASIEN &amp; PENDAPATAN KLINIK</div>
                <div class="period">
                    Periode {{ \Carbon\Carbon::parse($report['from'])->translatedFormat('d F Y') }}
                    &nbsp;s.d.&nbsp;
                    {{ \Carbon\Carbon::parse($report['to'])->translatedFormat('d F Y') }}
                </div>
            </td>
            <td class="meta">
                RATA-RATA PER KUNJUNGAN
                <strong>Rp {{ $rp($summary['average_ticket']) }}</strong>
                Dicetak {{ now()->translatedFormat('d F Y, H:i') }}
            </td>
        </tr>
    </table>
</div>
<div class="rule"></div>

<table class="kpi">
    <tr>
        <td class="kpi-revenue">
            <div class="label">PENDAPATAN</div>
            <div class="value">{{ $rp($report['totals']['revenue']) }}</div>
            <div class="sub">Treatment {{ $rp($report['totals']['treatment']) }} &middot; Produk {{ $rp($report['totals']['product']) }}</div>
        </td>
        <td class="kpi-expense">
            <div class="label">PENGELUARAN</div>
            <div class="value">{{ $rp($report['expenses']['total']) }}</div>
            <div class="sub">{{ count($report['expenses']['by_category']) }} kategori tercatat</div>
        </td>
        <td class="kpi-profit">
            <div class="label">KEUNTUNGAN BERSIH</div>
            <div class="value">{{ $rp($report['net_profit']) }}</div>
            <div class="sub">Pendapatan dikurangi pengeluaran</div>
        </td>
        <td class="kpi-visit">
            <div class="label">KUNJUNGAN</div>
            <div class="value">{{ $summary['visits'] }}</div>
            <div class="sub">{{ $summary['patients'] }} pasien &middot; {{ $summary['new_patients'] }} baru</div>
        </td>
    </tr>
</table>

<table class="grid">
    <thead>
        <tr>
            <th style="width:3%">NO</th>
            <th style="width:7%">TANGGAL</th>
            <th style="width:10%">STAF</th>
            <th style="width:12%">NAMA PASIEN</th>
            <th style="width:7%">BAYAR</th>
            <th style="width:17%">TINDAKAN</th>
            <th style="width:15%">PRODUK</th>
            <th class="num" style="width:8%">TREATMENT (Rp)</th>
            <th class="num" style="width:8%">PRODUK (Rp)</th>
            <th class="num" style="width:8%">TOTAL (Rp)</th>
            <th class="num" style="width:7%">FEE &amp; KOMISI</th>
            <th class="num" style="width:8%">TOTAL BERSIH</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($report['rows'] as $index => $line)
            <tr @class(['alt' => $index % 2 === 1])>
                <td class="idx">{{ $index + 1 }}</td>
                <td>{{ \Carbon\Carbon::parse($line['issued_at'])->format('d/m/Y') }}</td>
                <td>{{ $line['therapist_name'] ?? '—' }}</td>
                <td><strong>{{ $line['patient_name'] ?? '—' }}</strong></td>
                <td>
                    @if ($line['payment_label'])
                        <span class="pill">{{ $line['payment_label'] }}</span>
                    @else
                        <span class="muted">—</span>
                    @endif
                </td>
                <td>{{ $line['services'] ?: '—' }}</td>
                <td>{{ $line['products'] ?: '—' }}</td>
                <td class="num">{{ $line['treatment_amount'] ? $rp($line['treatment_amount']) : '—' }}</td>
                <td class="num">{{ $line['product_amount'] ? $rp($line['product_amount']) : '—' }}</td>
                <td class="num"><strong>{{ $rp($line['total']) }}</strong></td>
                <td class="num">{{ $line['fee_amount'] ? $rp($line['fee_amount']) : '—' }}</td>
                <td class="num">{{ $rp($line['net_amount']) }}</td>
            </tr>
        @empty
            <tr><td colspan="12" class="empty">Belum ada transaksi lunas pada periode ini.</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="7" class="num">TOTAL</td>
            <td class="num">{{ $rp($report['totals']['treatment']) }}</td>
            <td class="num">{{ $rp($report['totals']['product']) }}</td>
            <td class="num">{{ $rp($report['totals']['revenue']) }}</td>
            <td class="num">{{ $rp($report['totals']['fee']) }}</td>
            <td class="num">{{ $rp($report['totals']['net']) }}</td>
        </tr>
    </tfoot>
</table>

<table class="blocks">
    <tr>
        <td>
            <div class="block">
                <h2 class="h-fund">RINCIAN DANA MASUK</h2>
                <table class="b-fund">
                    @foreach ($report['payments'] as $payment)
                        <tr>
                            <td>{{ $payment['method_label'] }}</td>
                            <td class="num">{{ $rp($payment['total']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="sum">
                        <td>TOTAL DANA MASUK</td>
                        <td class="num">{{ $rp($fundTotal) }}</td>
                    </tr>
                </table>
            </div>
        </td>
        <td>
            <div class="block">
                <h2 class="h-expense">RINCIAN PENGELUARAN</h2>
                <table class="b-expense">
                    @forelse ($report['expenses']['by_category'] as $expense)
                        <tr>
                            <td>{{ $expense['category_label'] }}</td>
                            <td class="num">{{ $rp($expense['total']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="muted">Belum ada pengeluaran tercatat.</td></tr>
                    @endforelse
                    <tr class="sum">
                        <td>TOTAL PENGELUARAN</td>
                        <td class="num">{{ $rp($report['expenses']['total']) }}</td>
                    </tr>
                </table>
            </div>
        </td>
        <td>
            <div class="block">
                <h2 class="h-fee">FEE &amp; KOMISI STAF (PRATINJAU)</h2>
                <table class="b-fee">
                    @forelse ($report['commission']['rows'] as $line)
                        <tr>
                            <td>{{ $line['therapist_name'] }}</td>
                            <td class="num">{{ $rp($line['total']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="muted">Belum ada kunjungan atau penjualan bertanda staf.</td></tr>
                    @endforelse
                    <tr class="sum">
                        <td>TOTAL FEE</td>
                        <td class="num">{{ $rp($report['commission']['total']) }}</td>
                    </tr>
                </table>
                <div class="note">Belum memotong keuntungan; baru jadi pengeluaran setelah dibukukan.</div>
            </div>
        </td>
    </tr>
</table>

<div class="net">
    <table>
        <tr>
            <td>
                <div class="label">KEUNTUNGAN BERSIH</div>
                <div class="formula">Pendapatan {{ $rp($report['totals']['revenue']) }} &minus; pengeluaran {{ $rp($report['expenses']['total']) }}</div>
            </td>
            <td class="value">Rp {{ $rp($report['net_profit']) }}</td>
        </tr>
    </table>
</div>

<table class="sign">
    <tr>
        <td class="role">Diketahui Oleh</td>
        <td class="role">Disetujui Oleh</td>
        <td class="role">Staf</td>
        <td class="role">Dibukukan dan dibuat oleh</td>
    </tr>
    <tr>
        <td><div class="name">(............................)</div></td>
        <td><div class="name">(............................)</div></td>
        <td><div class="name">(............................)</div></td>
        <td><div class="name">(............................)</div></td>
    </tr>
</table>

<div class="foot">
    {{ $clinicName }} — Laporan Pasien &amp; Pendapatan Klinik,
    periode {{ \Carbon\Carbon::parse($report['from'])->format('d/m/Y') }}–{{ \Carbon\Carbon::parse($report['to'])->format('d/m/Y') }}
    <span class="right">Halaman </span>
</div>
</body>
</html>
