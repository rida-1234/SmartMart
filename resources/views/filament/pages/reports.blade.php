<x-filament-panels::page>
    <style>
        .rp-bar { display: flex; flex-wrap: wrap; gap: .5rem; align-items: end; }
        .rp-bar input { padding: .4rem .6rem; border: 1px solid rgba(127,127,127,.35); border-radius: .5rem; background: transparent; }
        .rp-bar button { padding: .4rem .8rem; border: 1px solid rgba(127,127,127,.35); border-radius: .5rem; font-size: .9rem; }
        .rp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: .75rem; }
        .rp-tile { border: 1px solid rgba(127,127,127,.25); border-radius: .75rem; padding: .8rem 1rem; }
        .rp-tile .k { font-size: .8rem; opacity: .7; }
        .rp-tile .v { font-size: 1.4rem; font-weight: 700; }
        .rp-two { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 900px) { .rp-two { grid-template-columns: 1fr; } }
        .rp-table { width: 100%; border-collapse: collapse; }
        .rp-table td, .rp-table th { padding: .4rem; border-bottom: 1px solid rgba(127,127,127,.18); text-align: left; }
        .rp-table .n { text-align: right; }
        .rp-input { width: 100%; padding: .4rem .6rem; border: 1px solid rgba(127,127,127,.35); border-radius: .5rem; background: transparent; }
        .rp-bad { color: rgb(220,38,38); } .rp-good { color: rgb(22,163,74); }
    </style>

    @php($s = $this->summary)

    <div class="rp-bar">
        <label>From<br><input type="date" wire:model.live="from"></label>
        <label>To<br><input type="date" wire:model.live="to"></label>
        <button type="button" wire:click="setRange('today')">Today</button>
        <button type="button" wire:click="setRange('week')">This week</button>
        <button type="button" wire:click="setRange('month')">This month</button>
        <button type="button" wire:click="setRange('last-month')">Last month</button>
    </div>

    <div class="rp-grid">
        <div class="rp-tile"><div class="k">Bills</div><div class="v">{{ $s['bills'] }}</div></div>
        <div class="rp-tile"><div class="k">Net sales</div><div class="v">{{ number_format($s['net_sales'], 2) }}</div></div>
        <div class="rp-tile"><div class="k">Returns</div><div class="v">{{ number_format($s['returns'], 2) }}</div></div>
        <div class="rp-tile"><div class="k">Gross profit</div><div class="v">{{ number_format($s['gross_profit'], 2) }}</div></div>
        <div class="rp-tile"><div class="k">Expenses</div><div class="v">{{ number_format($s['expenses'], 2) }}</div></div>
        <div class="rp-tile"><div class="k">Net profit</div><div class="v {{ $s['net_profit'] < 0 ? 'rp-bad' : 'rp-good' }}">{{ number_format($s['net_profit'], 2) }}</div></div>
        <div class="rp-tile"><div class="k">Cash sales</div><div class="v">{{ number_format($s['cash_sales'], 2) }}</div></div>
        <div class="rp-tile"><div class="k">Card / wallet</div><div class="v">{{ number_format($s['card_wallet_sales'], 2) }}</div></div>
        <div class="rp-tile"><div class="k">Credit given</div><div class="v">{{ number_format($s['credit_given'], 2) }}</div></div>
        <div class="rp-tile"><div class="k">Credit received</div><div class="v">{{ number_format($s['credit_received'], 2) }}</div></div>
    </div>

    <div class="rp-two">
        <x-filament::section heading="Top selling products">
            <table class="rp-table">
                <thead><tr><th>Product</th><th class="n">Qty</th><th class="n">Sales</th></tr></thead>
                <tbody>
                    @forelse ($this->topProducts as $p)
                        <tr><td>{{ $p->name }}</td><td class="n">{{ (float) $p->qty }}</td><td class="n">{{ number_format($p->revenue, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="3" style="opacity:.6">No sales in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>

        <div style="display:grid; gap:1rem;">
            <x-filament::section heading="Day closing (today)">
                <label>Opening cash in drawer<input class="rp-input" type="number" step="any" wire:model.live.debounce.400ms="openingCash" placeholder="0"></label>
                <p style="margin:.6rem 0">Expected cash now: <strong>{{ number_format($this->expectedCash, 2) }}</strong></p>
                <label>Counted cash<input class="rp-input" type="number" step="any" wire:model.live.debounce.400ms="countedCash" placeholder="Count the drawer"></label>
                @if ($countedCash !== null && $countedCash !== '')
                    @php($diff = round((float) $countedCash - $this->expectedCash, 2))
                    <p style="margin-top:.6rem" class="{{ $diff == 0 ? 'rp-good' : 'rp-bad' }}">
                        {{ $diff == 0 ? 'Cash matches.' : ($diff > 0 ? 'Extra: ' : 'Short: ').number_format(abs($diff), 2) }}
                    </p>
                @endif
                <p style="font-size:.8rem; opacity:.65; margin-top:.5rem">Assumes expenses and supplier payments marked "cash" were paid from the drawer.</p>
            </x-filament::section>

            <x-filament::section heading="Stock and balances (now)">
                <table class="rp-table">
                    <tr><td>Stock value at cost</td><td class="n">{{ number_format($this->stockValue['at_cost'], 2) }}</td></tr>
                    <tr><td>Stock value at sale price</td><td class="n">{{ number_format($this->stockValue['at_sale'], 2) }}</td></tr>
                    <tr><td>Customers owe the shop</td><td class="n">{{ number_format($this->dues['customers_owe'], 2) }}</td></tr>
                    <tr><td>Shop owes suppliers</td><td class="n">{{ number_format($this->dues['we_owe_suppliers'], 2) }}</td></tr>
                </table>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
