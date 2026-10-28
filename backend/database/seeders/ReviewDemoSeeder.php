<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ReviewDemoSeeder extends Seeder
{
    /**
     * Customer-facing review copy, bucketed by sentiment, written for the
     * ethnic-wear catalogue so PDPs read like a real fashion store.
     *
     * @var array<string, array<int, array{title: string, body: string}>>
     */
    private const COPY = [
        'positive' => [
            ['title' => 'Even prettier in person', 'body' => 'The zardozi work is far more intricate than the photos suggest and the fabric has a lovely weight. Wore it to a sangeet and got compliments all evening.'],
            ['title' => 'Worth every rupee', 'body' => 'Tailoring was impeccable and it arrived beautifully packed. The fit across the bust and waist was exactly as promised.'],
            ['title' => 'Drapes like a dream', 'body' => 'The fabric has a gorgeous fall and the border sits perfectly. Needed no pinning at all.'],
            ['title' => 'My wedding-day favourite', 'body' => 'Bought this for my reception and it photographed beautifully. The colour is rich and true to the images.'],
            ['title' => 'Comfortable for long functions', 'body' => 'Light enough to dance in all night and the lining means no itching. Would happily order again.'],
            ['title' => 'Lovely handwork', 'body' => 'You can tell it is hand-embroidered — the sequins catch the light just the right amount.'],
            ['title' => 'Perfect festive pick', 'body' => 'Ordered for Diwali and it looked far more expensive than it was. The stitching team adjusted the length on request.'],
            ['title' => 'Exactly as described', 'body' => 'Colour, weight and measurements all matched the listing. Dispatch was quick and tracking was accurate.'],
            ['title' => 'Great quality silk', 'body' => 'The Kanjivaram weave is dense and the zari has a warm gold tone rather than the harsh yellow you often get.'],
            ['title' => 'Received so many compliments', 'body' => 'Everyone asked where it was from. The blouse fit was spot on with the measurements I sent.'],
        ],
        'neutral' => [
            ['title' => 'Beautiful but runs slightly large', 'body' => 'The work is stunning; I would size down if you are between sizes. Tailoring fixed it easily.'],
            ['title' => 'Lovely piece, delicate fabric', 'body' => 'Gorgeous to look at but the net needs careful handling. Dry clean only, for sure.'],
            ['title' => 'Good, slightly darker shade', 'body' => 'Quality is genuinely nice, though the maroon reads a touch deeper than the photos on my screen.'],
            ['title' => 'Pretty but heavy', 'body' => 'The embroidery adds weight, so it is better for a seated function than a full day on your feet.'],
            ['title' => 'Nice, took a while to arrive', 'body' => 'Happy with the piece once it came, though dispatch took a day longer than the estimate.'],
        ],
        'critical' => [
            ['title' => 'Fit needed work', 'body' => 'The fabric and work are fine, but the ready measurements were off for me and I had to get it altered locally.'],
            ['title' => 'Shade differs from photos', 'body' => 'The work is nice; however the colour looked noticeably lighter than the listing on my display.'],
            ['title' => 'Loose thread on the border', 'body' => 'A small length of the border stitching had come loose on arrival. Support arranged a return without any fuss.'],
            ['title' => 'Not as heavy as expected', 'body' => 'Expected a heavier silk for the price. Still wearable but not quite what I pictured.'],
        ],
    ];

    public function run(): void
    {
        $users = User::where('role', Role::Customer)->pluck('id')->all();
        $products = Product::query()->whereNotNull('vendor_id')->get(['id', 'vendor_id', 'images']);

        if (empty($users) || $products->isEmpty()) {
            return;
        }

        $now = now();
        $used = [];

        // 1) Every product carries a handful of approved customer reviews so its PDP
        //    shows a real rating summary, distribution bars and review cards.
        foreach ($products as $product) {
            $target = mt_rand(1, min(5, count($users)));

            foreach (range(1, $target) as $ignored) {
                $userId = $this->freeUserId($used, $users, $product->id);

                if ($userId === null) {
                    continue;
                }

                $rating = collect([5, 5, 5, 4, 4, 4, 3, 2])->random();
                $copy = $this->copyFor($rating);
                $createdAt = $now->copy()->subDays(mt_rand(0, 150))->subHours(mt_rand(0, 23));

                $this->makeReview($userId, $product->id, $product->vendor_id, $rating, $copy, $product->images ?? [], true, false, false, $createdAt, 0.75);
            }
        }

        // 2) A spread of moderation states so the admin review queue has work to do:
        //    pending, hidden and reported reviews across the catalogue.
        $states = [
            ['count' => 24, 'approved' => false, 'hidden' => false, 'reported' => false, 'verified' => 0.2, 'images' => 0.3],
            ['count' => 10, 'approved' => true, 'hidden' => true, 'reported' => false, 'verified' => 0.3, 'images' => 0.3],
            ['count' => 8, 'approved' => false, 'hidden' => false, 'reported' => true, 'verified' => 0.1, 'images' => 0.4],
            ['count' => 4, 'approved' => true, 'hidden' => false, 'reported' => true, 'verified' => 0.4, 'images' => 0.4],
        ];

        foreach ($states as $state) {
            foreach (range(1, $state['count']) as $ignored) {
                $product = $products->random();
                $userId = $this->freeUserId($used, $users, $product->id);

                if ($userId === null) {
                    continue;
                }

                $rating = collect([5, 5, 4, 4, 3, 3, 2, 1])->random();
                $copy = $this->copyFor($rating);
                $createdAt = $now->copy()->subDays(mt_rand(0, 120))->subHours(mt_rand(0, 23));

                $this->makeReview($userId, $product->id, $product->vendor_id, $rating, $copy, $product->images ?? [], $state['approved'], $state['hidden'], $state['reported'], $createdAt, $state['verified'], $state['images']);
            }
        }
    }

    /**
     * Return an unused customer id for this product (the table enforces a unique
     * user_id + product_id pair) or null when every customer has already reviewed it.
     *
     * @param  array<string, bool>  $used
     * @param  array<int, int>  $users
     */
    private function freeUserId(array &$used, array $users, int $productId): ?int
    {
        $shuffled = $users;
        shuffle($shuffled);

        foreach ($shuffled as $userId) {
            $key = "{$userId}|{$productId}";

            if (! isset($used[$key])) {
                $used[$key] = true;

                return $userId;
            }
        }

        return null;
    }

    /**
     * @return array{title: string, body: string}
     */
    private function copyFor(int $rating): array
    {
        $bucket = match (true) {
            $rating >= 4 => 'positive',
            $rating === 3 => 'neutral',
            default => 'critical',
        };

        return collect(self::COPY[$bucket])->random();
    }

    /**
     * @param  array{title: string, body: string}  $copy
     * @param  array<int, string>  $pool
     */
    private function makeReview(
        int $userId,
        int $productId,
        int $vendorId,
        int $rating,
        array $copy,
        array $pool,
        bool $approved,
        bool $hidden,
        bool $reported,
        Carbon $createdAt,
        float $verifiedChance,
        float $imageChance = 0.45,
    ): void {
        $review = Review::factory()->make([
            'user_id' => $userId,
            'product_id' => $productId,
            'vendor_id' => $vendorId,
            'rating' => $rating,
            'title' => $copy['title'],
            'body' => $copy['body'],
            'images' => $this->pickImages($pool, $imageChance),
            'is_approved' => $approved,
            'is_hidden' => $hidden,
            'is_verified' => mt_rand(0, 100) / 100 <= $verifiedChance,
            'reported_at' => $reported ? $createdAt->copy()->addDays(mt_rand(0, 10)) : null,
            'report_reason' => $reported ? 'Contains inaccurate claims about the product.' : null,
        ]);

        $review->created_at = $createdAt;
        $review->updated_at = $createdAt;
        $review->save();
    }

    /**
     * @param  array<int, string>  $images
     * @return array<int, string>
     */
    private function pickImages(array $images, float $chance): array
    {
        if (empty($images) || mt_rand(0, 100) / 100 > $chance) {
            return [];
        }

        $picked = collect($images)->random(min(2, count($images)));

        return array_values(is_array($picked) ? $picked : [$picked]);
    }
}
