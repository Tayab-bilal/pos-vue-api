<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SearchController extends Controller
{
    private const LIMIT = 5;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $q = trim($validated['q'] ?? '');

        // Too short to be useful, and it would match almost everything
        if (mb_strlen($q) < 2) {
            return response()->json([
                'success' => true,
                'message' => 'Type at least 2 characters.',
                'query' => $q,
                'data' => [],
            ]);
        }

        // A % or _ typed by the user is searched for literally, it must not act as a wildcard
        $like = '%' . addcslashes($q, '\\%_') . '%';

        $groups = collect([
            $this->products($q, $like),
            $this->customers($like),
            $this->suppliers($like),
            $this->sales($like),
            $this->purchases($like),
            $this->returns($like),
            $this->payments($like),
            $this->expenses($like),
        ])->filter(fn (array $group) => count($group['items']) > 0)->values();

        return response()->json([
            'success' => true,
            'message' => 'Search results fetched successfully.',
            'query' => $q,
            'data' => $groups,
        ]);
    }

    private function products(string $q, string $like): array
    {
        $items = Product::query()
            ->with('unit:id,short_name')
            ->where(fn ($w) => $w->where('name', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhere('barcode', 'like', $like))
            // An exact barcode or SKU (a scanned code) comes first
            ->orderByRaw('CASE WHEN barcode = ? OR sku = ? THEN 0 ELSE 1 END', [$q, $q])
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(function (Product $product) {
                $stock = (float) $product->stock_quantity;

                [$badge, $tone] = match (true) {
                    ! $product->is_active => ['Inactive', 'neutral'],
                    $stock <= 0 => ['Out of stock', 'danger'],
                    $stock <= (float) $product->alert_quantity => ['Low stock', 'warning'],
                    default => ['In stock', 'success'],
                };

                return $this->item('product', $product->id, $product->name, [
                    $product->sku,
                    $product->barcode,
                    'Stock ' . $stock . ' ' . $product->unit?->short_name,
                    'Price ' . number_format((float) $product->sale_price, 2),
                ], $badge, $tone);
            });

        return $this->group('products', 'Products', $items);
    }

    private function customers(string $like): array
    {
        $items = Customer::query()
            ->where(fn ($w) => $w->where('name', 'like', $like)
                ->orWhere('company_name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('email', 'like', $like))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Customer $c) => $this->item('customer', $c->id, $c->name, [$c->company_name, $c->phone, $c->city], $c->is_active ? null : 'Inactive'));

        return $this->group('customers', 'Customers', $items);
    }

    private function suppliers(string $like): array
    {
        $items = Supplier::query()
            ->where(fn ($w) => $w->where('name', 'like', $like)
                ->orWhere('company_name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('email', 'like', $like))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Supplier $s) => $this->item('supplier', $s->id, $s->name, [$s->company_name, $s->phone, $s->city], $s->is_active ? null : 'Inactive'));

        return $this->group('suppliers', 'Suppliers', $items);
    }

    private function sales(string $like): array
    {
        $items = Sale::query()
            ->where(fn ($w) => $w->where('reference_no', 'like', $like)
                ->orWhere('customer_name', 'like', $like)
                ->orWhere('customer_phone', 'like', $like))
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Sale $sale) => $this->documentItem('sale', $sale, [
                $sale->customer_name,
                $sale->sale_date->format('d M Y'),
                number_format((float) $sale->total, 2),
            ]));

        return $this->group('sales', 'Sales', $items);
    }

    private function purchases(string $like): array
    {
        $items = Purchase::query()
            ->where(fn ($w) => $w->where('reference_no', 'like', $like)
                ->orWhere('supplier_name', 'like', $like)
                ->orWhere('supplier_phone', 'like', $like))
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Purchase $purchase) => $this->documentItem('purchase', $purchase, [
                $purchase->supplier_name,
                $purchase->purchase_date->format('d M Y'),
                number_format((float) $purchase->total, 2),
            ]));

        return $this->group('purchases', 'Purchases', $items);
    }

    private function returns(string $like): array
    {
        $sales = SaleReturn::query()
            ->where(fn ($w) => $w->where('reference_no', 'like', $like)->orWhere('customer_name', 'like', $like))
            ->orderByDesc('return_date')
            ->orderByDesc('id')
            ->limit(3)
            ->get()
            ->map(fn (SaleReturn $r) => $this->documentItem('sale_return', $r, [
                'Sales return',
                $r->customer_name,
                $r->return_date->format('d M Y'),
                number_format((float) $r->total, 2),
            ], false));

        $purchases = PurchaseReturn::query()
            ->where(fn ($w) => $w->where('reference_no', 'like', $like)->orWhere('supplier_name', 'like', $like))
            ->orderByDesc('return_date')
            ->orderByDesc('id')
            ->limit(3)
            ->get()
            ->map(fn (PurchaseReturn $r) => $this->documentItem('purchase_return', $r, [
                'Purchase return',
                $r->supplier_name,
                $r->return_date->format('d M Y'),
                number_format((float) $r->total, 2),
            ], false));

        return $this->group('returns', 'Returns', $sales->concat($purchases));
    }

    private function payments(string $like): array
    {
        $items = Payment::query()
            ->where(fn ($w) => $w->where('reference_no', 'like', $like)
                ->orWhere('party_name', 'like', $like)
                ->orWhere('transaction_ref', 'like', $like))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Payment $p) => $this->documentItem('payment', $p, [
                $p->type === 'receipt' ? 'Receipt' : 'Supplier payment',
                $p->party_name,
                $p->payment_date->format('d M Y'),
                number_format((float) $p->amount, 2),
            ], false));

        return $this->group('payments', 'Payments', $items);
    }

    private function expenses(string $like): array
    {
        $items = Expense::query()
            ->with('category:id,name')
            ->where(fn ($w) => $w->where('reference_no', 'like', $like)
                ->orWhere('paid_to', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('transaction_ref', 'like', $like))
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Expense $e) => $this->documentItem('expense', $e, [
                $e->description ?: $e->paid_to,
                $e->category?->name,
                $e->expense_date->format('d M Y'),
                number_format((float) $e->amount, 2),
            ], false));

        return $this->group('expenses', 'Expenses', $items);
    }

    private function group(string $key, string $label, Collection $items): array
    {
        return ['key' => $key, 'label' => $label, 'items' => $items->values()->all()];
    }

    /**
     * A sale, purchase, return, payment or expense. A cancelled one is marked in red.
     */
    private function documentItem(string $type, $document, array $subtitle, bool $hasPayment = true): array
    {
        $badge = null;
        $tone = 'neutral';

        if ($document->status === 'cancelled') {
            [$badge, $tone] = ['Cancelled', 'danger'];
        } elseif ($hasPayment) {
            [$badge, $tone] = match ($document->payment_status) {
                'paid' => ['Paid', 'success'],
                'partial' => ['Partial', 'warning'],
                default => ['Unpaid', 'danger'],
            };
        }

        return $this->item($type, $document->id, $document->reference_no, $subtitle, $badge, $tone);
    }

    private function item(string $type, int $id, string $title, array $subtitle = [], ?string $badge = null, string $tone = 'neutral'): array
    {
        return [
            'type' => $type,
            'id' => $id,
            'title' => $title,
            'subtitle' => implode(' · ', array_filter($subtitle, fn ($part) => filled($part))),
            'badge' => $badge,
            'tone' => $tone,
        ];
    }
}