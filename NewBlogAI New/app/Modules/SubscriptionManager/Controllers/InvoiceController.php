<?php

namespace App\Modules\SubscriptionManager\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SubscriptionManager\Models\Invoice;
use App\Modules\SubscriptionManager\Models\Subscription;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class InvoiceController extends Controller
{
    /**
     * Display a listing of invoices.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Subscription::class);

        $user = Auth::user();
        $query = Invoice::with(['customer', 'subscription.plan'])->latest();

        // Scope to tenant if not admin/support
        if (! in_array($user->role, [1, 5], true)) {
            if ($user->customer_id) {
                $query->where('customer_id', $user->customer_id);
            } else {
                return response()->json(['data' => []]);
            }
        } elseif ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        $invoices = $query->paginate(15);

        return response()->json($invoices);
    }

    /**
     * Display listing of invoices for a customer.
     */
    public function customerInvoices(string $customerId): JsonResponse
    {
        Gate::authorize('viewAny', Subscription::class);

        $user = Auth::user();
        if (! in_array($user->role, [1, 5], true) && $user->customer_id !== $customerId) {
            abort(403, 'Unauthorized access to customer invoices.');
        }

        $invoices = Invoice::with(['subscription.plan'])
            ->where('customer_id', $customerId)
            ->latest()
            ->get();

        return response()->json(['data' => $invoices]);
    }

    /**
     * Display invoice details.
     */
    public function show(string $id): JsonResponse
    {
        Gate::authorize('viewAny', Subscription::class);

        $invoice = Invoice::with(['customer', 'subscription.plan', 'transactions'])->find($id);

        if (! $invoice) {
            throw new ModelNotFoundException("Invoice with ID '{$id}' not found.");
        }

        $user = Auth::user();
        if (! in_array($user->role, [1, 5], true) && $user->customer_id !== $invoice->customer_id) {
            abort(403, 'Unauthorized access to this invoice.');
        }

        return response()->json(['data' => $invoice]);
    }
}
