<?php

namespace App\Services\Product;

use App\Enums\NotificationType;
use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VendorProfile;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(protected ?NotificationDispatcher $dispatcher = null)
    {
        $this->dispatcher ??= app(NotificationDispatcher::class);
    }

    public function createForVendor(VendorProfile $vendor, array $data): Product
    {
        return DB::transaction(function () use ($vendor, $data): Product {
            $product = $vendor->products()->create([
                'category_id' => $data['category_id'],
                'subcategory_id' => $data['subcategory_id'] ?? null,
                'brand_id' => $data['brand_id'] ?? null,
                'name' => $data['name'],
                'short_description' => $data['short_description'] ?? null,
                'description' => $data['description'] ?? null,
                'highlights' => $data['highlights'] ?? [],
                'specifications' => $data['specifications'] ?? [],
                'manufacturer_info' => $data['manufacturer_info'] ?? null,
                'tax_class' => $data['tax_class'] ?? 'standard',
                'gst' => $data['gst'] ?? null,
                'hsn_code' => $data['hsn_code'] ?? null,
                'price' => $data['price'] ?? 0,
                'mrp' => $data['mrp'] ?? null,
                'stock' => $data['stock'] ?? 0,
                'sku' => $data['sku'] ?? null,
                'images' => $this->imageUrlList($data['images'] ?? []),
                'attributes' => $data['attributes'] ?? [],
                'status' => $data['status'] ?? ProductStatus::Draft->value,
                'approval_status' => ProductApprovalStatus::PendingApproval->value,
                'featured' => $data['featured'] ?? false,
                'best_seller' => $data['best_seller'] ?? false,
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'meta_keywords' => $data['meta_keywords'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'is_returnable' => $data['is_returnable'] ?? true,
                'return_days' => $data['return_days'] ?? null,
                'return_policy' => $data['return_policy'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->syncImages($product, $data['images'] ?? []);
            $this->syncVideos($product, $data['videos'] ?? []);
            $this->syncVariants($product, $data['variants'] ?? []);
            $this->syncFees($product, $data['fees'] ?? []);
            $this->syncFaqs($product, $data['faqs'] ?? []);
            $this->syncCollections($product, $data['collection_ids'] ?? null);

            $this->normalizeProductPrice($product);

            $this->notifyAdminsOnSubmission($product, $vendor);

            return $this->loadForResponse($product);
        });
    }

    public function updateForVendor(VendorProfile $vendor, Product $product, array $data): Product
    {
        $this->assertOwnsProduct($vendor, $product);

        return DB::transaction(function () use ($product, $data, $vendor): Product {
            $fields = [
                'category_id', 'subcategory_id', 'brand_id', 'name', 'short_description',
                'description', 'highlights', 'specifications', 'manufacturer_info',
                'tax_class', 'gst', 'hsn_code',
                'price', 'mrp', 'stock', 'sku', 'attributes',
                'status', 'featured', 'best_seller',
                'meta_title', 'meta_description', 'meta_keywords', 'is_active',
                'is_returnable', 'return_days', 'return_policy',
            ];

            $update = [];
            foreach ($fields as $field) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }

                if ($field === 'price' && $data['price'] === null) {
                    continue;
                }

                $update[$field] = $data[$field];
            }

            if ($product->approval_status === ProductApprovalStatus::Rejected->value) {
                $update['approval_status'] = ProductApprovalStatus::PendingApproval->value;
                $update['rejected_at'] = null;
                $update['rejection_reason'] = null;
            }

            $product->update($update);

            if (($update['approval_status'] ?? null) === ProductApprovalStatus::PendingApproval->value) {
                $this->notifyAdminsOnSubmission($product->fresh(), $vendor);
            }

            if (array_key_exists('images', $data)) {
                $this->syncImages($product, $data['images']);
            }

            if (array_key_exists('videos', $data)) {
                $this->syncVideos($product, $data['videos']);
            }

            if (array_key_exists('variants', $data)) {
                $this->syncVariants($product, $data['variants']);
            }

            if (array_key_exists('fees', $data)) {
                $this->syncFees($product, $data['fees']);
            }

            if (array_key_exists('faqs', $data)) {
                $this->syncFaqs($product, $data['faqs']);
            }

            if (array_key_exists('collection_ids', $data)) {
                $this->syncCollections($product, $data['collection_ids']);
            }

            $this->normalizeProductPrice($product);

            return $this->loadForResponse($product);
        });
    }

    public function approve(Product $product): Product
    {
        $product->update([
            'approval_status' => ProductApprovalStatus::Approved->value,
            'status' => ProductStatus::Published->value,
            'is_active' => true,
            'approved_at' => now(),
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);

        $this->notifyVendor($product, NotificationType::ProductApproved, __('Product approved'), __('Your product ":product" has been approved and is now live.', ['product' => $product->name]));

        return $this->loadForResponse($product);
    }

    public function reject(Product $product, ?string $reason = null): Product
    {
        $product->update([
            'approval_status' => ProductApprovalStatus::Rejected->value,
            'status' => ProductStatus::Draft->value,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->notifyVendor($product, NotificationType::ProductRejected, __('Product rejected'), __('Your product ":product" was rejected.', ['product' => $product->name]).($reason ? ' '.__('Reason: :reason', ['reason' => $reason]) : ''));

        return $this->loadForResponse($product);
    }

    private function notifyVendor(Product $product, NotificationType $type, string $title, string $message): void
    {
        $vendorUser = $product->loadMissing('vendor.user')->vendor?->user;

        if ($vendorUser) {
            $this->dispatcher?->send($vendorUser, $type, $title, $message, ['product_id' => $product->id]);
        }
    }

    private function notifyAdminsOnSubmission(Product $product, VendorProfile $vendor): void
    {
        if (! $vendor->user) {
            return;
        }

        $this->dispatcher?->notifyAdmins(
            NotificationType::NewProductSubmitted,
            __('New product awaiting approval'),
            __('Vendor ":store" submitted ":product" for approval.', [
                'store' => $vendor->store_name,
                'product' => $product->name,
            ]),
            ['product_id' => $product->id, 'vendor_id' => $vendor->id],
        );
    }

    public function publish(Product $product): Product
    {
        if (! $product->isApproved()) {
            throw new \RuntimeException('Product must be approved before it can be published.');
        }

        $product->update([
            'status' => ProductStatus::Published->value,
            'is_active' => true,
        ]);

        return $this->loadForResponse($product);
    }

    public function unpublish(Product $product): Product
    {
        $product->update([
            'status' => ProductStatus::Archived->value,
        ]);

        return $this->loadForResponse($product);
    }

    public function assertOwnsProduct(VendorProfile $vendor, Product $product): void
    {
        if ($product->vendor_id !== $vendor->id) {
            abort(404, __('Product not found.'));
        }
    }

    public function loadForResponse(Product $product): Product
    {
        return $product->fresh()->load('category', 'subcategory', 'brand', 'images', 'videos', 'variants', 'fees', 'faqs', 'collections');
    }

    /**
     * Replace the product's collection assignments.
     *
     * @param  array<int, int|string>|null  $collectionIds
     */
    protected function syncCollections(Product $product, ?array $collectionIds): void
    {
        if ($collectionIds === null) {
            return;
        }

        $ids = collect($collectionIds)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        $product->collections()->sync($ids);
    }

    /**
     * Replace the product's add-on fees (handling charge, packaging, etc.).
     * Fees are non-taxable surcharges charged once per cart line.
     *
     * @param  array<int, array{name?: string, amount?: float|int|string}>  $fees
     */
    protected function syncFees(Product $product, array $fees): void
    {
        $product->fees()->delete();

        foreach ($fees as $fee) {
            $name = trim((string) ($fee['name'] ?? ''));
            $amount = (float) ($fee['amount'] ?? 0);

            if ($name === '' || $amount < 0) {
                continue;
            }

            $product->fees()->create([
                'name' => $name,
                'amount' => round($amount, 2),
            ]);
        }
    }

    /**
     * Replace the product's FAQ entries (ordered question / answer pairs).
     *
     * @param  array<int, array{question?: string, answer?: string, sort_order?: int}>  $faqs
     */
    protected function syncFaqs(Product $product, array $faqs): void
    {
        $product->faqs()->delete();

        foreach ($faqs as $index => $faq) {
            $question = trim((string) ($faq['question'] ?? ''));
            $answer = trim((string) ($faq['answer'] ?? ''));

            if ($question === '' || $answer === '') {
                continue;
            }

            $product->faqs()->create([
                'question' => $question,
                'answer' => $answer,
                'sort_order' => $faq['sort_order'] ?? $index,
            ]);
        }
    }

    /**
     * Replace structured ProductImage rows and keep the legacy `images`
     * JSON column (list of URLs) in sync for the storefront.
     */
    protected function syncImages(Product $product, array $images): void
    {
        $product->images()->forceDelete();

        $items = [];
        foreach ($images as $index => $image) {
            if (is_string($image)) {
                $items[] = ['url' => $image, 'alt_text' => null, 'is_primary' => $index === 0, 'sort_order' => $index];

                continue;
            }

            $url = $image['url'] ?? null;
            if (! $url) {
                continue;
            }

            $items[] = [
                'url' => $url,
                'alt_text' => $image['alt_text'] ?? null,
                'is_primary' => $image['is_primary'] ?? false,
                'sort_order' => $image['sort_order'] ?? $index,
            ];
        }

        if ($items !== [] && ! collect($items)->contains(fn (array $item) => $item['is_primary'])) {
            $items[0]['is_primary'] = true;
        }

        foreach ($items as $item) {
            $product->images()->create($item);
        }

        $product->update(['images' => $this->orderedUrls($items)]);
    }

    protected function syncVideos(Product $product, array $videos): void
    {
        $product->videos()->forceDelete();

        foreach ($videos as $index => $video) {
            if (is_string($video)) {
                $product->videos()->create(['url' => $video, 'sort_order' => $index]);

                continue;
            }

            $url = $video['url'] ?? null;
            if (! $url) {
                continue;
            }

            $product->videos()->create([
                'url' => $url,
                'provider' => $video['provider'] ?? 'youtube',
                'thumbnail_url' => $video['thumbnail_url'] ?? null,
                'sort_order' => $video['sort_order'] ?? $index,
            ]);
        }
    }

    /**
     * Replace the product's variant set, matching existing rows by id and
     * rejecting duplicate attribute combinations.
     */
    protected function syncVariants(Product $product, array $variants): void
    {
        $existing = $product->variants()->get()->keyBy('id');

        $hashes = [];
        foreach ($variants as $index => $variant) {
            $hash = $this->combinationHash($variant['attributes'] ?? []);

            if (isset($hashes[$hash])) {
                throw ValidationException::withMessages(
                    ['variants' => 'Duplicate variant combination at position '.($index + 1).'.']
                );
            }

            $hashes[$hash] = true;

            $id = $variant['id'] ?? null;
            if ($id && ! $existing->has($id)) {
                throw ValidationException::withMessages(
                    ['variants' => "Variant #{$id} does not belong to this product."]
                );
            }
        }

        $idsInPayload = collect($variants)->pluck('id')->filter()->all();

        $existing
            ->whereNotIn('id', $idsInPayload)
            ->each(fn (ProductVariant $variant) => $variant->forceDelete());

        foreach ($variants as $variant) {
            $id = $variant['id'] ?? null;
            $current = $id ? $existing->get($id) : null;
            $data = $this->variantColumns($variant, $current);
            $data['combination_hash'] = $this->combinationHash($variant['attributes'] ?? []);

            if ($current) {
                $current->update($data);
            } else {
                $product->variants()->create($data);
            }
        }
    }

    protected function variantColumns(array $variant, ?ProductVariant $current = null): array
    {
        $map = fn (string $field, mixed $default = null) => $variant[$field] ?? $current?->{$field} ?? $default;

        return [
            'sku' => $map('sku'),
            'barcode' => $map('barcode'),
            'price' => $variant['price'] ?? $current?->price ?? 0,
            'mrp' => $map('mrp'),
            'cost_price' => $map('cost_price'),
            'discount' => $map('discount', 0),
            'stock' => $variant['stock'] ?? $current?->stock ?? 0,
            'weight' => $map('weight'),
            'length' => $map('length'),
            'width' => $map('width'),
            'height' => $map('height'),
            'status' => $variant['status'] ?? $current?->status ?? VariantStatus::Active->value,
            'attributes' => $map('attributes', []),
            'images' => $map('images', []),
        ];
    }

    protected function normalizeProductPrice(Product $product): void
    {
        $product->refresh();

        $count = $product->variants()->where('status', VariantStatus::Active->value)->count();

        if ($count === 0) {
            return;
        }

        $minPrice = (float) $product->variants()->min('price');

        if ((float) $product->price === 0.0) {
            $product->update(['price' => $minPrice]);
        }
    }

    protected function combinationHash(array $attributes): string
    {
        ksort($attributes);

        return sha1((string) json_encode($attributes));
    }

    protected function imageUrlList(array $images): array
    {
        return $this->orderedUrls($this->normalizeImageItems($images));
    }

    protected function orderedUrls(array $items): array
    {
        usort($items, function (array $a, array $b): int {
            if ($a['is_primary'] && ! $b['is_primary']) {
                return -1;
            }

            if ($b['is_primary'] && ! $a['is_primary']) {
                return 1;
            }

            return $a['sort_order'] <=> $b['sort_order'];
        });

        return array_values(array_filter(array_map(fn (array $item) => $item['url'], $items)));
    }

    protected function normalizeImageItems(array $images): array
    {
        $items = [];
        foreach ($images as $index => $image) {
            if (is_string($image)) {
                $items[] = ['url' => $image, 'is_primary' => $index === 0, 'sort_order' => $index];

                continue;
            }

            $url = $image['url'] ?? null;
            if (! $url) {
                continue;
            }

            $items[] = [
                'url' => $url,
                'is_primary' => $image['is_primary'] ?? false,
                'sort_order' => $image['sort_order'] ?? $index,
            ];
        }

        return $items;
    }
}
