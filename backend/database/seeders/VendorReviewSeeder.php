<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorReview;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VendorReviewSeeder extends Seeder
{
    /** @var array<int, array{vendor: int, user: int, rating: int, title: string, body: string, verified: bool, approved: bool, hidden: bool, reported?: string, days: int}> */
    private const REVIEWS = [
        ['vendor' => 1, 'user' => 7, 'rating' => 5, 'title' => 'Excellent quality and fast delivery', 'body' => 'The product arrived well before the estimated date and the packaging was top notch. I would definitely order again from Store 1.', 'verified' => true, 'approved' => true, 'hidden' => false, 'days' => 28],
        ['vendor' => 1, 'user' => 8, 'rating' => 4, 'title' => 'Good value for the price', 'body' => 'Solid build and exactly as described. Shipping took a little longer than expected but overall I am happy with the purchase.', 'verified' => true, 'approved' => true, 'hidden' => false, 'days' => 21],
        ['vendor' => 1, 'user' => 9, 'rating' => 2, 'title' => 'Not what I expected', 'body' => 'The colour was slightly different from the photos and the finish felt rushed. Customer service did respond quickly though.', 'verified' => true, 'approved' => true, 'hidden' => false, 'reported' => 'Review contains inaccurate product claim', 'days' => 12],
        ['vendor' => 2, 'user' => 10, 'rating' => 5, 'title' => 'Fantastic seller, highly recommended', 'body' => 'Genuine product with a great warranty. The store communicated proactively and the item works perfectly.', 'verified' => true, 'approved' => true, 'hidden' => false, 'days' => 25],
        ['vendor' => 2, 'user' => 11, 'rating' => 3, 'title' => 'Average experience', 'body' => 'Decent product but the customer support was difficult to reach. Took several follow ups to resolve a minor issue.', 'verified' => false, 'approved' => false, 'hidden' => false, 'days' => 6],
        ['vendor' => 2, 'user' => 12, 'rating' => 1, 'title' => 'Very disappointed', 'body' => 'Product arrived damaged and the replacement process was slow. I will not be shopping here again.', 'verified' => true, 'approved' => true, 'hidden' => true, 'reported' => 'Abusive language towards store', 'days' => 9],
        ['vendor' => 3, 'user' => 13, 'rating' => 5, 'title' => 'Best purchase this year', 'body' => 'Everything about this order was smooth. Great quality, fair pricing and lightning fast dispatch.', 'verified' => true, 'approved' => true, 'hidden' => false, 'days' => 18],
        ['vendor' => 3, 'user' => 14, 'rating' => 4, 'title' => 'Really good product', 'body' => 'Matches the description well and feels premium. Would love to see more options from this store.', 'verified' => true, 'approved' => true, 'hidden' => false, 'days' => 14],
        ['vendor' => 3, 'user' => 15, 'rating' => 2, 'title' => 'Mediocre at best', 'body' => 'The quality is acceptable but nothing special. Delivery was delayed by two days without any update.', 'verified' => false, 'approved' => true, 'hidden' => false, 'reported' => 'Possible fake purchase / no order record', 'days' => 3],
        ['vendor' => 4, 'user' => 16, 'rating' => 5, 'title' => 'Outstanding support', 'body' => 'The team went out of their way to help me choose the right size. Product quality exceeded my expectations.', 'verified' => true, 'approved' => true, 'hidden' => false, 'days' => 11],
        ['vendor' => 4, 'user' => 7, 'rating' => 4, 'title' => 'Very happy overall', 'body' => 'Good quality for the price and quick dispatch. The only minor gripe is the packaging could be more secure.', 'verified' => true, 'approved' => false, 'hidden' => false, 'days' => 5],
        ['vendor' => 4, 'user' => 8, 'rating' => 3, 'title' => 'Okay but could be better', 'body' => 'The product is fine but I had issues with the return policy. Nothing terrible, just not a perfect experience.', 'verified' => true, 'approved' => true, 'hidden' => true, 'days' => 8],
        ['vendor' => 5, 'user' => 9, 'rating' => 5, 'title' => 'Delighted with my order', 'body' => 'High quality item delivered in two days. This store has become my go to for this category.', 'verified' => true, 'approved' => true, 'hidden' => false, 'days' => 20],
        ['vendor' => 5, 'user' => 10, 'rating' => 4, 'title' => 'Great experience', 'body' => 'Smooth transaction and timely updates throughout. The product matches its description closely.', 'verified' => true, 'approved' => true, 'hidden' => false, 'days' => 7],
        ['vendor' => 5, 'user' => 11, 'rating' => 2, 'title' => 'Underwhelming', 'body' => 'Took longer than promised and the item was slightly damaged. The seller did offer a partial refund.', 'verified' => false, 'approved' => false, 'hidden' => false, 'reported' => 'Unverified purchase, likely competitor review', 'days' => 1],
    ];

    public function run(): void
    {
        $reviewsCount = VendorReview::query()->count();

        if ($reviewsCount > 3) {
            $this->command?->info('Vendor reviews already seeded. Skipping.');

            return;
        }

        $created = [];

        DB::transaction(function () use (&$created): void {
            foreach (self::REVIEWS as $data) {
                $vendor = VendorProfile::query()->find($data['vendor']);
                $user = User::query()->find($data['user']);

                if (! $vendor || ! $user) {
                    continue;
                }

                $review = VendorReview::query()->create([
                    'vendor_id' => $vendor->id,
                    'user_id' => $user->id,
                    'order_id' => null,
                    'rating' => $data['rating'],
                    'title' => $data['title'],
                    'body' => $data['body'],
                    'is_verified' => $data['verified'],
                    'is_approved' => $data['approved'],
                    'is_hidden' => $data['hidden'],
                    'reported_at' => isset($data['reported']) ? now()->subDays($data['days']) : null,
                    'report_reason' => $data['reported'] ?? null,
                ]);

                $created[] = ['review' => $review, 'days' => $data['days']];
            }
        });

        // Backdate created_at so the reviews spread over the past month.
        DB::transaction(function () use ($created): void {
            foreach ($created as $entry) {
                $entry['review']->update([
                    'created_at' => now()->subDays($entry['days'])->setTime(rand(9, 20), rand(0, 59)),
                    'updated_at' => now()->subDays($entry['days'])->setTime(rand(9, 20), rand(0, 59)),
                ]);
            }
        });

        $this->command?->info('Seeded '.count($created).' vendor reviews across stores.');
    }
}
