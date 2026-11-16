<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Sales, returns, profit and cash for a date range (inclusive).
     *
     * @return array<string, float|int>
     */
    public function summary(CarbonInterface $from, CarbonInterface $to): array
    {
        [$start, $end] = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];

        $sales = Sale::whereBetween('created_at', [$start, $end]);

        $items = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereBetween('sales.created_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(sale_items.line_total), 0) AS revenue')
            ->selectRaw('COALESCE(SUM(sale_items.cost_price * sale_items.qty), 0) AS cost')
            ->selectRaw('COALESCE(SUM(sale_items.line_total * sale_items.returned_qty / sale_items.qty), 0) AS returned_revenue')
            ->selectRaw('COALESCE(SUM(sale_items.cost_price * sale_items.returned_qty), 0) AS returned_cost')
            ->first();

        $billDiscounts = (float) (clone $sales)->sum('discount');
        $netRevenue = (float) $items->revenue - (float) $items->returned_revenue - $billDiscounts;
        $netCost = (float) $items->cost - (float) $items->returned_cost;
        $expenses = (float) Expense::whereBetween('date', [$start->toDateString(), $end->toDateString()])->sum('amount');

        $creditReceived = (float) Payment::where('payable_type', Customer::class)
            ->whereBetween('created_at', [$start, $end])->sum('amount');
        $suppliersPaid = (float) Payment::where('payable_type', Supplier::class)
            ->whereBetween('created_at', [$start, $end])->sum('amount');

        return [
            'bills' => (clone $sales)->count(),
            'gross_sales' => round((float) (clone $sales)->sum('total'), 2),
            'returns' => round((float) $items->returned_revenue, 2),
            'net_sales' => round($netRevenue, 2),
            'cost_of_goods' => round($netCost, 2),
            'gross_profit' => round($netRevenue - $netCost, 2),
            'expenses' => round($expenses, 2),
            'net_profit' => round($netRevenue - $netCost - $expenses, 2),
            'cash_sales' => round((float) (clone $sales)->where('payment_method', 'cash')->sum('paid'), 2),
            'card_wallet_sales' => round((float) (clone $sales)->whereIn('payment_method', ['card', 'wallet'])->sum('paid'), 2),
            'credit_given' => round((float) (clone $sales)->sum('due'), 2),
            'credit_received' => round($creditReceived, 2),
            'suppliers_paid' => round($suppliersPaid, 2),
        ];
    }

    /**
     * Cash that should be in the drawer at day end (before counting).
     */
    public function expectedCash(CarbonInterface $day, float $openingCash = 0): float
    {
        $range = [$day->copy()->startOfDay(), $day->copy()->endOfDay()];
        $s = $this->summary($day, $day);

        // Cash part of credit sales also lands in the drawer.
        $partialCredit = (float) Sale::whereBetween('created_at', $range)
            ->where('payment_method', 'credit')->sum('paid');

        $cashRefunds = (float) StockMovement::where('type', 'return')
            ->whereBetween('created_at', $range)->sum('cash_refund');

        // Assumes supplier payments and expenses are paid from the drawer in cash.
        $cashOut = (float) Payment::where('payable_type', Supplier::class)->where('method', 'cash')
            ->whereBetween('created_at', $range)->sum('amount')
            + (float) Expense::whereDate('date', $day->toDateString())->sum('amount');

        $creditInCash = (float) Payment::where('payable_type', Customer::class)->where('method', 'cash')
            ->whereBetween('created_at', $range)->sum('amount');

        return round($openingCash + $s['cash_sales'] + $partialCredit + $creditInCash - $cashOut - $cashRefunds, 2);
    }

    public function topProducts(CarbonInterface $from, CarbonInterface $to, int $limit = 10): Collection
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereBetween('sales.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->groupBy('sale_items.product_id', 'sale_items.name')
            ->select('sale_items.product_id', 'sale_items.name')
            ->selectRaw('SUM(sale_items.qty - sale_items.returned_qty) AS qty')
            ->selectRaw('SUM(sale_items.line_total) AS revenue')
            ->orderByDesc('qty')
            ->limit($limit)
            ->get();
    }

    public function stockValue(): array
    {
        $row = Product::where('stock', '>', 0)
            ->selectRaw('COALESCE(SUM(stock * cost_price), 0) AS at_cost, COALESCE(SUM(stock * sale_price), 0) AS at_sale')
            ->first();

        return ['at_cost' => round((float) $row->at_cost, 2), 'at_sale' => round((float) $row->at_sale, 2)];
    }

    public function dues(): array
    {
        return [
            'customers_owe' => round((float) DB::table('customers')->where('balance', '>', 0)->sum('balance'), 2),
            'we_owe_suppliers' => round((float) DB::table('suppliers')->where('balance', '>', 0)->sum('balance'), 2),
        ];
    }
}
