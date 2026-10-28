<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Enums\Role;
use App\Http\Controllers\Concerns\FiltersCreatedRange;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ReviewResource;
use App\Http\Resources\V1\UserResource;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use FiltersCreatedRange;

    public function index(Request $request): JsonResponse
    {
        $role = $request->input('role', Role::Customer->value);

        $users = $this->applyCreatedRange(User::query(), $request)
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')), fn ($q) => $q->where('role', $role))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->when($request->filled('search'), function ($q) use ($request): void {
                $term = $request->string('search');
                $q->where(function ($sub) use ($term): void {
                    $sub->where('name', 'like', '%'.$term.'%')
                        ->orWhere('email', 'like', '%'.$term.'%')
                        ->orWhere('phone', 'like', '%'.$term.'%');
                });
            })
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(__('Users fetched successfully.'), $users, fn ($items) => UserResource::collection($items));
    }

    public function show(User $user): JsonResponse
    {
        $user->load([
            'wallet',
            'addresses',
            'reviews' => fn ($q) => $q->with('product:id,name,images')->latest()->limit(5),
        ]);

        $orders = Order::with('items')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'order_number', 'total', 'order_status', 'payment_status', 'created_at']);

        return $this->success(__('User fetched successfully.'), [
            'user' => new UserResource($user),
            'orders' => $orders,
            'order_count' => Order::where('user_id', $user->id)->count(),
            'total_spent' => round((float) Order::where('user_id', $user->id)->where('payment_status', 'PAID')->sum('total'), 2),
            'reviews' => ReviewResource::collection($user->reviews),
            'reviews_count' => (int) Review::where('user_id', $user->id)->count(),
            'reviews_average_rating' => round((float) Review::where('user_id', $user->id)->avg('rating'), 1),
        ]);
    }

    public function toggleActive(User $user): JsonResponse
    {
        $user->update(['is_active' => ! $user->is_active]);

        return $this->success(__('User status updated.'), new UserResource($user));
    }

    public function block(User $user): JsonResponse
    {
        if ($user->role === Role::SuperAdmin->value) {
            return $this->error(__('Super admins cannot be blocked.'), null, 422);
        }

        $user->update(['is_active' => false]);

        // Invalidate every existing session so a signed-in user is logged out
        // immediately instead of only being refused at the next login.
        $user->tokens()->delete();

        return $this->success(__('User blocked successfully.'), new UserResource($user));
    }

    public function unblock(User $user): JsonResponse
    {
        $user->update(['is_active' => true]);

        return $this->success(__('User unblocked successfully.'), new UserResource($user));
    }
}
