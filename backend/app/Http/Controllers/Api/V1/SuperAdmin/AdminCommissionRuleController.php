<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Enums\CommissionLevel;
use App\Enums\CommissionRuleType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CommissionRule;
use App\Models\Product;
use App\Models\VendorProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCommissionRuleController extends Controller
{
    public function index(): JsonResponse
    {
        $rules = CommissionRule::query()->orderBy('level')->orderBy('id')->get();

        return $this->success(__('Commission rules fetched successfully.'), [
            'rules' => $rules,
            'levels' => array_map(
                fn (CommissionLevel $level): array => [
                    'value' => $level->value,
                    'label' => $level->label(),
                    'priority' => $level->priority(),
                ],
                CommissionLevel::cases(),
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['level'] = $data['level'] ?? CommissionLevel::Global->value;

        $rule = CommissionRule::create($data);

        return $this->success(__('Commission rule created successfully.'), $rule, 201);
    }

    public function show(CommissionRule $commissionRule): JsonResponse
    {
        return $this->success(__('Commission rule fetched successfully.'), $commissionRule);
    }

    public function update(Request $request, CommissionRule $commissionRule): JsonResponse
    {
        $data = $this->validated($request, $commissionRule);

        $commissionRule->update($data);

        return $this->success(__('Commission rule updated successfully.'), $commissionRule->fresh());
    }

    public function destroy(CommissionRule $commissionRule): JsonResponse
    {
        $commissionRule->delete();

        return $this->success(__('Commission rule deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?CommissionRule $rule = null): array
    {
        $data = $request->validate([
            'level' => ['sometimes', 'in:'.implode(',', array_column(CommissionLevel::cases(), 'value'))],
            'scope_id' => ['sometimes', 'nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0'],
            'type' => ['sometimes', 'in:'.implode(',', array_column(CommissionRuleType::cases(), 'value'))],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['type'] = $data['type'] ?? CommissionRuleType::Percentage->value;

        $level = $data['level'] ?? $rule?->level->value;

        if ($level === CommissionLevel::Global->value) {
            $data['scope_id'] = null;
        } elseif ($level !== null) {
            $scopeId = $data['scope_id'] ?? null;
            $this->assertScopeExists($level, $scopeId);

            $data['scope_id'] = $scopeId;
        }

        return $data;
    }

    protected function assertScopeExists(string $level, ?int $scopeId): void
    {
        $model = match ($level) {
            CommissionLevel::Category->value => Category::class,
            CommissionLevel::Vendor->value => VendorProfile::class,
            CommissionLevel::Product->value => Product::class,
            default => null,
        };

        if ($model && ($scopeId === null || ! $model::query()->whereKey($scopeId)->exists())) {
            abort(422, __('A valid scope is required for this commission level.'));
        }
    }
}
