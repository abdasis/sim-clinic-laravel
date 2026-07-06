<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('invoice.title') }} {{ $invoice_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            color: #1a1a1a;
            margin: 0;
            padding: 24px;
            background: #f4f4f5;
        }
        .invoice {
            max-width: 720px;
            margin: 0 auto;
            background: #fff;
            padding: 32px;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #111;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .clinic-name { font-size: 22px; font-weight: 700; margin: 0; }
        .invoice-meta { text-align: right; font-size: 14px; }
        .invoice-meta strong { display: block; font-size: 16px; }
        .parties { display: flex; justify-content: space-between; margin-bottom: 24px; font-size: 14px; }
        .parties .label { color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 14px; }
        th, td { padding: 10px 8px; text-align: left; }
        thead th { border-bottom: 2px solid #111; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        tbody td { border-bottom: 1px solid #e5e7eb; }
        .num { text-align: right; }
        .totals { display: flex; justify-content: flex-end; margin-bottom: 24px; }
        .totals table { width: auto; min-width: 260px; margin: 0; }
        .totals .grand td { border-top: 2px solid #111; font-weight: 700; font-size: 16px; }
        .payments { font-size: 14px; margin-bottom: 24px; }
        .payments .label { color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .actions { text-align: center; margin-top: 24px; }
        button {
            background: #111; color: #fff; border: 0; padding: 10px 20px;
            border-radius: 6px; font-size: 14px; cursor: pointer;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .invoice { box-shadow: none; border-radius: 0; max-width: none; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="invoice">
        <div class="header">
            <div>
                <p class="clinic-name">{{ $tenant?->name ?? config('app.name') }}</p>
                @if ($tenant?->phone)
                    <div>{{ $tenant->phone }}</div>
                @endif
            </div>
            <div class="invoice-meta">
                <span class="label">{{ __('invoice.invoice_number') }}</span>
                <strong>{{ $invoice_number }}</strong>
                <div>{{ __('invoice.issued_at') }}: {{ optional($issued_at)->format('d/m/Y H:i') }}</div>
            </div>
        </div>

        <div class="parties">
            <div>
                <span class="label">{{ __('invoice.patient') }}</span>
                <div>{{ $patient?->name ?? '-' }}</div>
            </div>
            <div style="text-align:right">
                <span class="label">{{ __('invoice.cashier') }}</span>
                <div>{{ $cashier?->name ?? '-' }}</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>{{ __('invoice.item') }}</th>
                    <th class="num">{{ __('invoice.qty') }}</th>
                    <th class="num">{{ __('invoice.price') }}</th>
                    <th class="num">{{ __('invoice.subtotal') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td class="num">{{ $item->qty }}</td>
                        <td class="num">{{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <table>
                <tr class="grand">
                    <td>{{ __('invoice.total') }}</td>
                    <td class="num">Rp {{ number_format((float) $subtotal, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>

        @if ($payments->isNotEmpty())
            <div class="payments">
                <span class="label">{{ __('invoice.payment_method') }}</span>
                @foreach ($payments as $payment)
                    <div>
                        {{ $payment->method?->label() ?? $payment->method }}
                        &mdash; Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                    </div>
                @endforeach
            </div>
        @endif

        <div class="actions">
            <button type="button" onclick="window.print()">{{ __('invoice.print') }}</button>
        </div>
    </div>
</body>
</html>
