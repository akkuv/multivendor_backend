<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Enums\AdminLedgerType;
use App\Http\Controllers\Controller;
use App\Models\AdminFinancialLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LedgerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', Rule::in(array_column(AdminLedgerType::cases(), 'value'))],
        ]);

        $entries = AdminFinancialLedger::query()
            ->with(['order', 'refund', 'user'])
            ->when(
                isset($validated['type']),
                fn ($q) => $q->where('type', $validated['type']),
            )
            ->when(
                $request->filled('order_id'),
                fn ($q) => $q->where('order_id', $request->integer('order_id')),
            )
            ->when(
                $request->filled('user_id'),
                fn ($q) => $q->where('user_id', $request->integer('user_id')),
            )
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(__('Ledger entries fetched successfully.'), $entries, fn ($items) => $items);
    }

    public function show(AdminFinancialLedger $entry): JsonResponse
    {
        $entry->load(['order', 'refund', 'user']);

        return $this->success(__('Ledger entry fetched successfully.'), $entry);
    }
}
