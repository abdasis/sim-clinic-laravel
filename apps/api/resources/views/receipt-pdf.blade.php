<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice_number }}</title>
    <style>
        {{--
            Lebar halaman PDF disamakan dengan area cetak kepala termal
            (48mm), diatur di InvoiceController. Margin kiri-kanan tipis saja;
            gulungan kertas tidak punya tepi atas-bawah.
        --}}
        @page {
            margin: 0 2mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: DejaVu Sans, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            line-height: 1.25;
            color: #000;
            margin: 0;
            padding: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
        }
        td, th {
            padding: 0;
            vertical-align: top;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
        .rule-solid { border-top: 1px solid #000; margin: 1.5mm 0; }
        .rule-dashed { border-top: 1px dashed #555; margin: 1.5mm 0; }
        .band {
            background: #000;
            color: #fff;
            text-align: center;
            font-size: 7.5pt;
            font-weight: bold;
            letter-spacing: 1px;
            padding: 1.5px 0;
        }
    </style>
</head>
<body>
@php
    $services = $items->filter(fn ($item) => ($item->kind ?? ($item->service_id !== null ? 'service' : 'product')) !== 'product');
    $products = $items->filter(fn ($item) => ($item->kind ?? ($item->service_id !== null ? 'service' : 'product')) === 'product');
    $groups = array_filter([
        ['key' => 'service', 'label' => __('invoice.group_service'), 'items' => $services],
        ['key' => 'product', 'label' => __('invoice.group_product'), 'items' => $products],
    ], fn ($g) => $g['items']->isNotEmpty());

    $total = (float) $subtotal;
    $gross = (float) $items->reduce(function ($sum, $item) {
        $listPrice = (float) ($item->list_price ?? 0);
        $unitPrice = (float) $item->unit_price;
        return $sum + (max($listPrice, $unitPrice) * (int) $item->qty);
    }, 0);
    // Poin yang ditukar berdiri sendiri, di luar potongan promo: pasien
    // menyerahkan sesuatu yang dikumpulkannya untuk baris ini, jadi ia harus
    // bisa dihitung ulang dari nota — bukan lebur jadi selisih harga.
    $pointsRedeemed = max(0, (int) ($transaction->points_redeemed ?? 0));
    $pointsRedeemedAmount = max(0, (float) ($transaction->points_redeemed_amount ?? 0));
    $discount = max(0, $gross - $total - $pointsRedeemedAmount);
    $pointsEarned = max(0, (int) ($transaction->points_earned ?? 0));

    $paid = (float) ($transaction->paid_amount ?? $payments->sum('amount'));
    $outstanding = (float) (isset($transaction) ? $transaction->outstandingAmount() : max(0, $total - $paid));
    $change = max(0, $paid - $total);

    $clinicName = \App\Support\ClinicIdentity::displayName($tenant) ?: config('app.name');
    $clinicTagline = $tenant?->companyProfile?->tagline;
    $clinicAddress = \App\Support\ReceiptAddress::format($tenant?->companyProfile?->address);
    $clinicPhone = $tenant?->phone;
    $receiptNote = $tenant?->companyProfile?->receipt_note;
    $printCount = max(1, (int) ($transaction->print_count ?? 1));
    $performers = isset($transaction) && $transaction->relationLoaded('performers') ? $transaction->performers : collect();
    $totalQty = (int) $items->sum('qty');
    $printedAt = now()->format('d/m/Y H:i');
@endphp

    <div class="text-center font-bold uppercase" style="font-size: 9.5pt; letter-spacing: 0.5px;">
        {{ $clinicName }}
    </div>
    @if ($clinicTagline)
        <div class="text-center" style="font-size: 7.5pt; color: #444; margin-top: 0.5mm;">
            {{ $clinicTagline }}
        </div>
    @endif
    @if ($clinicAddress)
        <div class="text-center" style="font-size: 7.5pt; color: #444; margin-top: 0.5mm;">
            {{ $clinicAddress }}
        </div>
    @endif
    @if ($clinicPhone)
        <div class="text-center" style="font-size: 7.5pt; margin-top: 0.5mm;">
            {{ __('invoice.phone_short') }} {{ $clinicPhone }}
        </div>
    @endif

    <div class="rule-dashed"></div>

    <div class="band uppercase">{{ __('invoice.receipt') }}</div>

    @if (isset($transaction) && $transaction->cancelled_at)
        <div class="band uppercase" style="margin-top: 1mm;">{{ __('invoice.cancelled') }}</div>
        <div class="text-center" style="font-size: 7.5pt; margin-top: 0.5mm;">{{ __('invoice.cancelled_note') }}</div>
    @endif

    <table style="margin-top: 1.5mm; font-size: 8pt;">
        <tr>
            <td style="width: 38px; color: #555;">{{ __('invoice.number_short') }}</td>
            <td style="width: 6px; text-align: center; color: #555;">:</td>
            <td class="font-bold">{{ $invoice_number }}</td>
        </tr>
        <tr>
            <td style="color: #555;">{{ __('invoice.date') }}</td>
            <td style="text-align: center; color: #555;">:</td>
            <td>{{ optional($issued_at)->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td style="color: #555;">{{ __('invoice.customer') }}</td>
            <td style="text-align: center; color: #555;">:</td>
            <td>{{ $patient?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td style="color: #555;">{{ __('invoice.served_by') }}</td>
            <td style="text-align: center; color: #555;">:</td>
            <td>{{ $cashier?->name ?? '-' }}</td>
        </tr>
        @if ($performers->isNotEmpty())
            <tr>
                <td style="color: #555;">{{ __('invoice.performers') }}</td>
                <td style="text-align: center; color: #555;">:</td>
                <td>{{ $performers->pluck('name')->join(', ') }}</td>
            </tr>
        @endif
    </table>

    <div class="rule-dashed"></div>

    @foreach ($groups as $group)
        <div style="font-size: 7.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #444; margin-top: 1.5mm; margin-bottom: 0.5mm;">
            {{ $group['label'] }}
        </div>
        @foreach ($group['items'] as $item)
            <table style="margin-bottom: 1.2mm;">
                <tr>
                    <td colspan="2" class="font-bold" style="padding-top: 0.5mm;">
                        {{ $item->name }}
                    </td>
                </tr>
                <tr>
                    <td style="color: #555;">
                        {{ $item->qty }} x {{ number_format((float) $item->unit_price, 0, ',', '.') }}
                    </td>
                    <td class="text-right font-bold">
                        {{ number_format((float) $item->subtotal, 0, ',', '.') }}
                    </td>
                </tr>
            </table>
        @endforeach
    @endforeach

    <div class="rule-solid"></div>

    <table style="font-size: 8.5pt;">
        <tr>
            <td style="color: #444;">{{ __('invoice.item_total') }} ({{ str_replace(':count', (string) $totalQty, __('invoice.item_count')) }})</td>
            <td class="text-right">{{ number_format($discount + $pointsRedeemedAmount > 0 ? $gross : $total, 0, ',', '.') }}</td>
        </tr>
        @if ($discount > 0)
            <tr>
                <td style="color: #444;">{{ __('invoice.discount') }}</td>
                <td class="text-right">-{{ number_format($discount, 0, ',', '.') }}</td>
            </tr>
        @endif
        @if ($pointsRedeemed > 0)
            <tr>
                <td style="color: #444;">{{ __('invoice.points_redeemed', ['count' => $pointsRedeemed]) }}</td>
                <td class="text-right" style="white-space: nowrap;">-{{ number_format($pointsRedeemedAmount, 0, ',', '.') }}</td>
            </tr>
        @endif
    </table>

    <div style="border-top: 1.5px solid #000; margin-top: 1mm; padding-top: 1mm;">
        <table style="font-size: 9.5pt;" class="font-bold">
            <tr>
                <td class="uppercase">{{ __('invoice.grand_total') }} (IDR)</td>
                <td class="text-right">{{ number_format($total, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    @if ($payments->isNotEmpty())
        <div class="rule-dashed"></div>
        <table style="font-size: 8pt;">
            @foreach ($payments as $payment)
                <tr>
                    <td style="color: #444;">{{ $payment->method?->label() ?? $payment->method }}</td>
                    <td class="text-right">{{ number_format((float) $payment->amount, 0, ',', '.') }}</td>
                </tr>
            @endforeach
            @if ($payments->count() > 1)
                <tr>
                    <td style="color: #444;">{{ __('invoice.paid_amount') }}</td>
                    <td class="text-right">{{ number_format($paid, 0, ',', '.') }}</td>
                </tr>
            @endif
            @if ($change > 0)
                <tr class="font-bold">
                    <td>{{ __('invoice.change') }}</td>
                    <td class="text-right">{{ number_format($change, 0, ',', '.') }}</td>
                </tr>
            @endif
        </table>
    @endif

    @if ($outstanding > 0)
        <div style="border: 1px solid #000; margin-top: 1.5mm; padding: 1mm;">
            <table style="font-size: 8.5pt;" class="font-bold">
                <tr>
                    <td>{{ __('invoice.outstanding') }}</td>
                    <td class="text-right">{{ number_format($outstanding, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>
    @endif

    {{-- Nol tidak pernah dicetak: baris yang selalu ada tapi kadang "+0 poin"
         cuma menambah keraguan tanpa memberi apa-apa. --}}
    @if ($pointsEarned > 0)
        <table style="font-size: 7.5pt; color: #444; margin-top: 1mm;">
            <tr>
                <td>{{ __('invoice.points_earned') }}</td>
                <td class="text-right" style="color: #000; font-weight: bold;">+{{ $pointsEarned }} {{ __('invoice.points_unit') }}</td>
            </tr>
        </table>
    @endif

    @if ($receiptNote)
        <div class="text-center" style="font-size: 7.5pt; color: #444; font-style: italic; margin-top: 2mm;">
            *{{ $receiptNote }}
        </div>
    @endif

    <div style="margin-top: 2mm;">
        <div class="text-center" style="font-size: 7.5pt; color: #444; letter-spacing: 2px;">
            --- &#10022; &#9829; &#10022; ---
        </div>
        <div class="text-center font-bold" style="font-size: 10.5pt; margin-top: 1mm;">
            {{ __('invoice.thank_you') }}
        </div>
        <div class="text-center uppercase" style="font-size: 7.5pt; color: #444; letter-spacing: 0.5px; margin-top: 0.5mm;">
            {{ __('invoice.thank_you_sub') }} {{ $clinicName }}
        </div>

        {{--
            Keterangan cetak hanya muncul pada cetak ulang. Pada cetakan pertama
            ia cuma mengulang tanggal yang sudah ada di kepala nota — tiga baris
            di tiap struk yang tidak pernah dibaca siapa pun.

            Untuk cetakan kedua dan seterusnya penandanya justru wajib: tanpa
            itu satu transaksi bisa beredar sebagai dua bukti bayar yang sama
            sahnya. Nomor dan waktunya dirapatkan jadi satu baris.
        --}}
        @if ($printCount > 1)
            <div style="border: 1px solid #000; text-align: center; font-size: 7.5pt; padding: 1px 2px; margin-top: 1.5mm;">
                <span class="font-bold uppercase" style="letter-spacing: 0.5px;">{{ __('invoice.reprint') }} #{{ $printCount }}</span>
                <span style="color: #555;">{{ $printedAt }}</span>
            </div>
        @endif
    </div>
</body>
</html>
