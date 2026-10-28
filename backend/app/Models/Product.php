<?php

namespace App\Models;

use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'category_id',
        'brand_id',
        'subcategory_id',
        'name',
        'slug',
        'short_description',
        'description',
        'highlights',
        'specifications',
        'manufacturer_info',
        'tax_class',
        'gst',
        'hsn_code',
        'price',
        'mrp',
        'stock',
        'sku',
        'images',
        'attributes',
        'is_active',
        'status',
        'approval_status',
        'featured',
        'best_seller',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'is_returnable',
        'return_days',
        'return_policy',
        'created_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'mrp' => 'decimal:2',
        'gst' => 'decimal:2',
        'stock' => 'integer',
        'reserved_stock' => 'integer',
        'images' => 'array',
        'attributes' => 'array',
        'specifications' => 'array',
        'highlights' => 'array',
        'manufacturer_info' => 'array',
        'is_active' => 'boolean',
        'featured' => 'boolean',
        'best_seller' => 'boolean',
        'is_returnable' => 'boolean',
        'return_days' => 'integer',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if (empty($product->slug)) {
                $product->slug = static::uniqueSlug((string) $product->name, $product->getKey());
            }
        });
    }

    /**
     * Build a human-readable, unique slug from a product name. Collisions get a
     * numeric suffix (`-2`, `-3`, …) rather than a random string.
     */
    public static function uniqueSlug(string $name, int|string|null $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $suffix = 2;

        while (
            static::query()
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * Resolve a route-bound product by its numeric id or its human-readable
     * slug, so storefront URLs can read `/products/orange-...-abc123` while
     * existing clients that pass an id keep working.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if ($field !== null) {
            return static::query()->where($field, $value)->first();
        }

        return is_numeric($value)
            ? static::query()->whereKey($value)->first()
            : static::query()->where('slug', $value)->first();
    }

    public function isApproved(): bool
    {
        return $this->approval_status === ProductApprovalStatus::Approved->value;
    }

    public function isPublished(): bool
    {
        return $this->status === ProductStatus::Published->value;
    }

    public function isLive(): bool
    {
        return $this->isPublished() && $this->isApproved() && $this->is_active;
    }

    /**
     * Available stock, aggregated across active variants when they exist.
     */
    public function availableStock(): int
    {
        if ($this->relationLoaded('variants') || count($this->variants ?? []) > 0) {
            $available = $this->variants
                ->where('status', VariantStatus::Active->value)
                ->sum(fn ($variant) => max($variant->stock - $variant->reserved_stock, 0));

            if ($available > 0) {
                return $available;
            }
        }

        return max($this->stock, 0) - max($this->reserved_stock, 0);
    }

    /**
     * Scope to products that are visible on the storefront.
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('status', ProductStatus::Published->value)
            ->where('approval_status', ProductApprovalStatus::Approved->value);
    }

    /**
     * Scope to products belonging to a category or any of its descendants.
     */
    public function scopeInCategoryTree(Builder $query, Category $category): Builder
    {
        $ids = $category->descendantsWithSelf();

        return $query->whereIn('category_id', $ids)->orWhereIn('subcategory_id', $ids);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class, 'coupon_product');
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'collection_product')->withPivot('sort_order')->withTimestamps();
    }

    public function videos(): HasMany
    {
        return $this->hasMany(ProductVideo::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('id');
    }

    public function fees(): HasMany
    {
        return $this->hasMany(ProductFee::class)->orderBy('id');
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(ProductFaq::class)->orderBy('sort_order');
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()
            ->where('is_approved', true)
            ->where('is_hidden', false);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ProductQuestion::class);
    }

    public function visibleQuestions(): HasMany
    {
        return $this->questions()->where('is_hidden', false);
    }

    public function recentViews(): HasMany
    {
        return $this->hasMany(RecentlyViewed::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
