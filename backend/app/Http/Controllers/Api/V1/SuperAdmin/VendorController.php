<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Enums\NotificationType;
use App\Enums\VendorStatus;
use App\Http\Controllers\Concerns\FiltersCreatedRange;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveVendorRequest;
use App\Http\Requests\StoreVendorRequest;
use App\Http\Requests\UpdateVendorRequest;
use App\Http\Resources\V1\VendorResource;
use App\Models\VendorProfile;
use App\Services\Auth\AuthService;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    use FiltersCreatedRange;

    public function __construct(
        protected AuthService $authService,
        protected NotificationDispatcher $dispatcher,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $vendors = $this->applyCreatedRange(VendorProfile::query(), $request)
            ->with('user')
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper((string) $request->string('status'))))
            ->when($request->filled('search'), function ($q) use ($request): void {
                $term = $request->string('search');
                $q->where(function ($sub) use ($term): void {
                    $sub->where('store_name', 'like', '%'.$term.'%')
                        ->orWhereHas('user', fn ($u) => $u->where('email', 'like', '%'.$term.'%'));
                });
            })
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 12));

        return $this->paginated(__('Vendors fetched successfully.'), $vendors, fn ($items) => VendorResource::collection($items));
    }

    public function show(VendorProfile $vendor): JsonResponse
    {
        $vendor->load([
            'user',
            'wallet',
            'products:id,vendor_id,name,price,status,approval_status',
            'orders:id,vendor_id,vendor_order_number,status,total',
        ]);

        $payouts = $vendor->payouts()->orderByDesc('created_at')->limit(10)->get();

        return $this->success(__('Vendor fetched successfully.'), new VendorResource($vendor, [
            'wallet' => $vendor->wallet,
            'payouts' => $payouts,
            'product_count' => $vendor->products->count(),
            'order_count' => $vendor->orders->count(),
            'total_sales' => round((float) $vendor->orders->sum('total'), 2),
            'documents' => $vendor->business_documents,
            'bank' => [
                'account_name' => $vendor->bank_account_name,
                'account_number' => $vendor->bank_account_number,
                'ifsc' => $vendor->bank_ifsc,
                'bank_name' => $vendor->bank_name,
            ],
            'pan' => $vendor->pan,
        ]));
    }

    public function store(StoreVendorRequest $request): JsonResponse
    {
        $result = $this->authService->registerVendor($request->validated());

        return $this->success(__('Vendor created successfully.'), new VendorResource($result['vendor']->load('user')));
    }

    public function update(UpdateVendorRequest $request, VendorProfile $vendor): JsonResponse
    {
        $vendor->update($request->validated());

        return $this->success(__('Vendor updated successfully.'), new VendorResource($vendor->fresh()->load('user')));
    }

    public function approve(ApproveVendorRequest $request, VendorProfile $vendor): JsonResponse
    {
        $status = VendorStatus::tryFrom(strtoupper((string) $request->string('status')));

        if ($status === null) {
            return $this->error(__('Invalid vendor status.'), null, 422);
        }

        $vendor->update([
            'status' => $status->value,
            'is_approved' => $status->isApproved(),
            'commission_rate' => $request->filled('commission_rate')
                ? $request->float('commission_rate')
                : $vendor->commission_rate,
        ]);

        if ($vendor->fresh(['user'])->user && $status->isApproved()) {
            $this->dispatcher->send(
                $vendor->user,
                NotificationType::VendorApproved,
                __('Your vendor account is approved'),
                __('Congratulations! :store has been approved and is now live.', ['store' => $vendor->store_name]),
                ['vendor_id' => $vendor->id],
            );
        }

        return $this->success(__('Vendor status updated successfully.'), new VendorResource($vendor->fresh()->load('user')));
    }

    public function activate(VendorProfile $vendor): JsonResponse
    {
        $vendor->update([
            'status' => VendorStatus::Approved->value,
            'is_approved' => true,
        ]);

        return $this->success(__('Vendor activated successfully.'), new VendorResource($vendor->fresh()->load('user')));
    }

    public function suspend(VendorProfile $vendor): JsonResponse
    {
        $vendor->update([
            'status' => VendorStatus::Suspended->value,
            'is_approved' => false,
        ]);

        return $this->success(__('Vendor suspended successfully.'), new VendorResource($vendor->fresh()->load('user')));
    }

    public function reject(Request $request, VendorProfile $vendor): JsonResponse
    {
        $vendor->update([
            'status' => VendorStatus::Rejected->value,
            'is_approved' => false,
        ]);

        return $this->success(__('Vendor rejected successfully.'), new VendorResource($vendor->fresh()->load('user')));
    }

    public function destroy(VendorProfile $vendor): JsonResponse
    {
        $vendor->user?->delete();
        $vendor->delete();

        return $this->success(__('Vendor deleted successfully.'));
    }
}
