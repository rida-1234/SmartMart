<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $sale->invoice_no }}</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { width: 72mm; margin: 0 auto; padding: 4mm 0; font: 12px/1.35 "Courier New", monospace; color: #000; }
        .c { text-align: center; }
        .r { text-align: right; }
        .b { font-weight: bold; }
        .big { font-size: 15px; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 1px 0; }
        @media screen { body { background: #fff; box-shadow: 0 0 6px #ccc; padding: 4mm; margin-top: 10px; } }
    </style>
</head>
<body>
    <div class="c b big">{{ $shop['name'] }}</div>
    @if ($shop['address'])<div class="c">{{ $shop['address'] }}</div>@endif
    @if ($shop['phone'])<div class="c">Ph: {{ $shop['phone'] }}</div>@endif
    <hr>
    <table>
        <tr><td>Bill: {{ $sale->invoice_no }}</td><td class="r">{{ $sale->created_at->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Cashier: {{ $sale->user?->name }}</td><td class="r">{{ $sale->customer?->name }}</td></tr>
    </table>
    <hr>
    <table>
        @foreach ($sale->items as $item)
            <tr><td colspan="3">{{ $item->name }}</td></tr>
            <tr>
                <td>{{ rtrim(rtrim(number_format($item->qty, 3), '0'), '.') }} x {{ number_format($item->sale_price, 2) }}</td>
                <td class="r">{{ (float) $item->discount > 0 ? '-'.number_format($item->discount, 2) : '' }}</td>
                <td class="r">{{ number_format($item->line_total, 2) }}</td>
            </tr>
        @endforeach
    </table>
    <hr>
    <table>
        <tr><td>Items</td><td class="r">{{ $sale->items->count() }}</td></tr>
        <tr><td>Subtotal</td><td class="r">{{ number_format($sale->subtotal, 2) }}</td></tr>
        @if ((float) $sale->discount > 0)
            <tr><td>Discount</td><td class="r">-{{ number_format($sale->discount, 2) }}</td></tr>
        @endif
        <tr class="b big"><td>TOTAL {{ $shop['currency'] }}</td><td class="r">{{ number_format($sale->total, 2) }}</td></tr>
        <tr><td>Paid ({{ ucfirst($sale->payment_method) }})</td><td class="r">{{ number_format($sale->paid + $sale->change_due, 2) }}</td></tr>
        @if ((float) $sale->change_due > 0)
            <tr><td>Change</td><td class="r">{{ number_format($sale->change_due, 2) }}</td></tr>
        @endif
        @if ((float) $sale->due > 0)
            <tr class="b"><td>Credit (due)</td><td class="r">{{ number_format($sale->due, 2) }}</td></tr>
        @endif
    </table>
    <hr>
    <div class="c">{{ $shop['footer'] }}</div>
    @if ($sale->status === 'returned')<div class="c b">*** RETURNED ***</div>@endif

    @if ($autoPrint)
        <script>window.addEventListener('load', () => { window.print(); });</script>
    @endif
</body>
</html>
