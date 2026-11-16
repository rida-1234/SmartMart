<x-filament-panels::page>
    <style>
        .pos-grid { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 1rem; align-items: start; }
        @media (max-width: 1024px) { .pos-grid { grid-template-columns: 1fr; } }
        .pos-card { border: 1px solid rgba(127, 127, 127, .25); border-radius: .75rem; padding: 1rem; background: var(--color-white, #fff); }
        .dark .pos-card { background: rgba(255, 255, 255, .04); }
        .pos-scan { width: 100%; font-size: 1.25rem; padding: .75rem 1rem; border-radius: .5rem; border: 2px solid rgba(127, 127, 127, .35); background: transparent; }
        .pos-scan:focus { outline: none; border-color: rgb(245, 158, 11); }
        .pos-results { margin-top: .25rem; border: 1px solid rgba(127, 127, 127, .25); border-radius: .5rem; overflow: hidden; }
        .pos-results button { display: flex; width: 100%; justify-content: space-between; gap: 1rem; padding: .5rem .75rem; text-align: left; }
        .pos-results button:hover { background: rgba(245, 158, 11, .12); }
        .pos-muted { opacity: .65; font-size: .85rem; }
        .pos-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .pos-table th { text-align: left; font-size: .8rem; opacity: .7; padding: .4rem; border-bottom: 1px solid rgba(127, 127, 127, .25); }
        .pos-table td { padding: .4rem; border-bottom: 1px solid rgba(127, 127, 127, .15); vertical-align: middle; }
        .pos-table .num { text-align: right; white-space: nowrap; }
        .pos-qty { width: 5.5rem; padding: .3rem .4rem; border: 1px solid rgba(127, 127, 127, .35); border-radius: .375rem; background: transparent; text-align: right; }
        .pos-x { color: rgb(220, 38, 38); font-weight: 700; padding: 0 .4rem; }
        .pos-row { display: flex; justify-content: space-between; align-items: center; padding: .3rem 0; }
        .pos-total { font-size: 2rem; font-weight: 800; }
        .pos-input { width: 100%; padding: .5rem .6rem; border: 1px solid rgba(127, 127, 127, .35); border-radius: .5rem; background: transparent; }
        .pos-label { display: block; font-size: .8rem; opacity: .75; margin: .6rem 0 .2rem; }
        .pos-methods { display: grid; grid-template-columns: repeat(4, 1fr); gap: .35rem; }
        .pos-methods button { padding: .45rem 0; border-radius: .5rem; border: 1px solid rgba(127, 127, 127, .35); font-size: .85rem; }
        .pos-methods button.on { background: rgb(245, 158, 11); border-color: rgb(245, 158, 11); color: #000; font-weight: 600; }
        .pos-pay { width: 100%; margin-top: 1rem; padding: .9rem; font-size: 1.2rem; font-weight: 700; border-radius: .6rem; background: rgb(22, 163, 74); color: #fff; }
        .pos-pay:disabled { opacity: .45; }
        .pos-secondary { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; margin-top: .5rem; }
        .pos-secondary button { padding: .5rem; border-radius: .5rem; border: 1px solid rgba(127, 127, 127, .35); font-size: .9rem; }
        .pos-error { color: rgb(220, 38, 38); font-size: .9rem; margin-top: .5rem; }
        .pos-keys { font-size: .75rem; opacity: .6; margin-top: .75rem; }
        .pos-held button { display: block; width: 100%; text-align: left; padding: .35rem .5rem; border-radius: .4rem; }
        .pos-held button:hover { background: rgba(245, 158, 11, .12); }
        .pos-low { color: rgb(220, 38, 38); }
    </style>

    <div
        x-data="{
            focusScan() { this.$refs.scan?.focus() },
            print(url) {
                let f = document.getElementById('pos-print-frame');
                if (f) f.remove();
                f = document.createElement('iframe');
                f.id = 'pos-print-frame';
                f.style.cssText = 'position:fixed;width:0;height:0;border:0;right:0;bottom:0';
                f.src = url;
                document.body.appendChild(f);
            },
        }"
        x-init="focusScan()"
        x-on:pos-print.window="print($event.detail.url); $nextTick(() => focusScan())"
        x-on:keydown.window.f2.prevent="$wire.pay()"
        x-on:keydown.window.f8.prevent="$wire.hold()"
        x-on:keydown.window.f9.prevent="$refs.paid?.focus(); $refs.paid?.select()"
        x-on:keydown.window.escape.prevent="if (confirm('Clear this bill?')) { $wire.clear(); focusScan() }"
        class="pos-grid"
    >
        {{-- Left: scan + bill lines --}}
        <div class="pos-card">
            <input
                x-ref="scan"
                type="text"
                class="pos-scan"
                placeholder="Scan barcode or type product name, then Enter"
                autocomplete="off"
                wire:model.live.debounce.250ms="scan"
                wire:keydown.enter="addByScan"
            >

            @if ($this->results->isNotEmpty())
                <div class="pos-results">
                    @foreach ($this->results as $p)
                        <button type="button" wire:key="r-{{ $p->id }}" wire:click="addProduct({{ $p->id }})" x-on:click="$nextTick(() => focusScan())">
                            <span>{{ $p->name }} <span class="pos-muted">{{ $p->barcode }}</span></span>
                            <span>{{ number_format($p->sale_price, 2) }} <span class="pos-muted">· stock {{ (float) $p->stock }}</span></span>
                        </button>
                    @endforeach
                </div>
            @endif

            @error('cart') <div class="pos-error">{{ $message }}</div> @enderror

            <table class="pos-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="num">Price</th>
                        <th class="num">Qty</th>
                        <th class="num">Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cart as $id => $line)
                        <tr wire:key="line-{{ $id }}">
                            <td>
                                {{ $line['name'] }}
                                @if ($line['stock'] < (float) $line['qty'])
                                    <div class="pos-muted pos-low">Only {{ $line['stock'] }} in stock</div>
                                @endif
                            </td>
                            <td class="num">{{ number_format($line['price'], 2) }}</td>
                            <td class="num">
                                <input type="number" step="any" min="0" class="pos-qty" wire:model.live.debounce.400ms="cart.{{ $id }}.qty">
                                <span class="pos-muted">{{ $line['unit'] }}</span>
                            </td>
                            <td class="num">{{ number_format($line['price'] * (float) $line['qty'], 2) }}</td>
                            <td class="num"><button type="button" class="pos-x" title="Remove" wire:click="remove('{{ $id }}')">&times;</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="pos-muted" style="padding: 2rem; text-align: center;">No items yet. Scan a product to start.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Right: totals + payment --}}
        <div>
            <div class="pos-card">
                <div class="pos-row"><span>Items</span><span>{{ count($cart) }}</span></div>
                <div class="pos-row"><span>Subtotal</span><span>{{ number_format($this->subtotal, 2) }}</span></div>
                <label class="pos-label" for="pos-discount">Discount on bill</label>
                <input id="pos-discount" type="number" step="any" min="0" class="pos-input" wire:model.live.debounce.400ms="billDiscount" placeholder="0">

                <div class="pos-row" style="margin-top: .75rem;"><span>TOTAL</span><span class="pos-total">{{ number_format($this->total, 2) }}</span></div>

                <span class="pos-label">Payment</span>
                <div class="pos-methods">
                    @foreach (['cash' => 'Cash', 'card' => 'Card', 'wallet' => 'Wallet', 'credit' => 'Credit'] as $value => $label)
                        <button type="button" wire:click="$set('method', '{{ $value }}')" @class(['on' => $method === $value])>{{ $label }}</button>
                    @endforeach
                </div>

                <label class="pos-label" for="pos-customer">Customer {{ $method === 'credit' ? '(required for credit)' : '(optional)' }}</label>
                <select id="pos-customer" class="pos-input" wire:model.live="customerId">
                    <option value="">Walk-in customer</option>
                    @foreach ($this->customers as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}{{ $c->phone ? ' · '.$c->phone : '' }}{{ $c->balance > 0 ? ' · owes '.number_format($c->balance, 2) : '' }}</option>
                    @endforeach
                </select>

                <label class="pos-label" for="pos-paid">Amount received (F9) {{ $method === 'credit' ? '' : '· empty = exact' }}</label>
                <input id="pos-paid" x-ref="paid" type="number" step="any" min="0" class="pos-input" style="font-size: 1.2rem;"
                       wire:model.live.debounce.300ms="paid" wire:keydown.enter="pay" placeholder="{{ number_format($this->total, 2, '.', '') }}">

                @if ($this->change > 0)
                    <div class="pos-row" style="margin-top: .5rem;"><span>Change</span><strong style="font-size: 1.4rem;">{{ number_format($this->change, 2) }}</strong></div>
                @endif

                @error('paid') <div class="pos-error">{{ $message }}</div> @enderror
                @error('method') <div class="pos-error">{{ $message }}</div> @enderror

                <button type="button" class="pos-pay" wire:click="pay" wire:loading.attr="disabled" @disabled(empty($cart))>
                    Pay &amp; print (F2)
                </button>
                <div class="pos-secondary">
                    <button type="button" wire:click="hold" @disabled(empty($cart))>Hold bill (F8)</button>
                    <button type="button" x-on:click="if (confirm('Clear this bill?')) { $wire.clear(); focusScan() }">Clear (Esc)</button>
                </div>
                <div class="pos-keys">Enter = add item · F2 = pay · F8 = hold · F9 = amount received · Esc = clear</div>
            </div>

            @if (count($this->held))
                <div class="pos-card pos-held" style="margin-top: 1rem;">
                    <strong>Bills on hold</strong>
                    @foreach ($this->held as $i => $bill)
                        <button type="button" wire:key="held-{{ $i }}" wire:click="resume({{ $i }})">
                            {{ $bill['at'] }} · {{ count($bill['cart']) }} items · {{ number_format($bill['total'], 2) }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
