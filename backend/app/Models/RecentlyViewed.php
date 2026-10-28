<?php

namespace App\Models;

use Database\Factories\RecentlyViewedFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecentlyViewed extends Model
{
    /** @use HasFactory<RecentlyViewedFactory> */
    use HasFactory;

    protected $table = 'recently_viewed';

    protected $fillable = [
        'user_id',
        'product_id',
        'view_count',
        'last_viewed_at',
    ];

    protected $casts = [
        'view_count' => 'integer',
        'last_viewed_at' => 'datetime',
    ];

    /**
     * Record a product view for a user, bumping the existing row if present.
     */
    public static function record(User $user, Product $product): void
    {
        $existing = self::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            $existing->forceFill([
                'view_count' => $existing->view_count + 1,
                'last_viewed_at' => now(),
            ])->save();

            return;
        }

        self::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'view_count' => 1,
            'last_viewed_at' => now(),
        ]);

        // Keep the history reasonably bounded by pruning the oldest rows.
        self::query()
            ->where('user_id', $user->id)
            ->orderBy('last_viewed_at', 'desc')
            ->offset(20)
            ->limit(1000)
            ->delete();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
