<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Product;
use App\Models\ProductAnswer;
use App\Models\ProductQuestion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProductQuestionDemoSeeder extends Seeder
{
    /**
     * @var array<int, array{question: string, answer: string}>
     */
    private const ANSWERED = [
        ['question' => 'Is the blouse stitched or unstitched?', 'answer' => 'The blouse piece comes unstitched with enough fabric for sizes up to XXL, so your tailor can fit it exactly to you.'],
        ['question' => 'What is the fabric?', 'answer' => 'Pure silk with a handwoven zari border. Dry clean only to protect the weave and embroidery.'],
        ['question' => 'Does the lehenga come with a dupatta?', 'answer' => 'Yes, it includes a matching net dupatta with a scalloped embroidered border.'],
        ['question' => 'How long does delivery usually take?', 'answer' => 'Most orders are dispatched within 24 hours and arrive in 2-4 working days depending on your location.'],
        ['question' => 'Can I return this if the fit is not right?', 'answer' => 'Absolutely, you have 14 days to return unworn items with tags intact for a full refund. Start the return from your account.'],
        ['question' => 'Is the colour true to the photos?', 'answer' => 'The shade is very close to the images; a slight variation can occur between screens and lighting.'],
        ['question' => 'Is it suitable for a day wedding?', 'answer' => 'Yes, the lighter palette works beautifully for daytime ceremonies and mehendi functions.'],
        ['question' => 'Do you offer custom stitching?', 'answer' => 'Yes, share your measurements after ordering and our tailoring team will adjust the fit before dispatch.'],
        ['question' => 'How heavy is the embroidery?', 'answer' => 'It is hand-embroidered, so expect a comfortable weight that still drapes well rather than feeling stiff.'],
        ['question' => 'Will the zari tarnish over time?', 'answer' => 'Stored wrapped in muslin and away from moisture, the zari keeps its sheen for years.'],
        ['question' => 'Can I get this in a different colour?', 'answer' => 'Several pieces can be made to order in alternate shades — message us and we will confirm the options.'],
        ['question' => 'Is the saree ready to wear?', 'answer' => 'It comes with an unstitched blouse piece; the saree itself is ready to drape with a fall pico already done.'],
        ['question' => 'Does it include the belt?', 'answer' => 'Yes, the embellished waist belt shown in the images is included with this set.'],
        ['question' => 'What sizes are available?', 'answer' => 'It is available in standard sizes XS to XXL, with made-to-measure on request.'],
        ['question' => 'Does it need dry cleaning?', 'answer' => 'Yes, we recommend professional dry cleaning only to protect the embroidery and fabric finish.'],
        ['question' => 'Is the lining attached?', 'answer' => 'The lehenga and blouse are fully lined and the lining is attached, so it is ready to wear straight away.'],
        ['question' => 'How heavy is it overall?', 'answer' => 'Around 2.5 kg with the full embroidery and lining, which is typical for a bridal-weight set.'],
        ['question' => 'Does the price include taxes?', 'answer' => 'Yes, the price shown is the final price and all applicable taxes are already included.'],
        ['question' => 'Is shipping free with this item?', 'answer' => 'Yes, shipping is free on orders over the qualifying amount and this item qualifies.'],
        ['question' => 'Is this a genuine handcrafted piece?', 'answer' => 'Every piece is handcrafted in our own ateliers with a karigar signature card included in the box.'],
    ];

    /**
     * @var array<int, string>
     */
    private const UNANSWERED = [
        'Will the ivory shade be restocked?',
        'Can you stitch the blouse for me?',
        'Is there a matching potli bag?',
        'Do you ship internationally?',
        'Can this be tailored for a petite frame?',
        'Are there any washing instructions I should know about?',
        'How does the sizing compare to a size L?',
        'Is this available in a smaller size?',
        'Will the maroon shade return in size L?',
        'Can I see a video of the actual piece?',
    ];

    /**
     * @var array<int, string>
     */
    private const REASONS = [
        'Contains offensive language that violates our guidelines.',
        'Looks like spam or promotional content.',
        'Unverified purchase, suspected competitor post.',
        'Harassing or disrespectful toward the seller.',
        'Contains inaccurate claims about the product.',
    ];

    public function run(): void
    {
        if (ProductQuestion::query()->count() > 3) {
            $this->command?->info('Product questions already seeded. Skipping.');

            return;
        }

        $customers = User::where('role', Role::Customer)->pluck('id')->all();
        $products = Product::query()->whereNotNull('vendor_id')->get(['id', 'vendor_id']);

        if (empty($customers) || $products->isEmpty()) {
            $this->command?->warn('No customers or products available. Skipping.');

            return;
        }

        $states = [
            ['count' => 18, 'answered' => true, 'hidden' => false, 'reported' => false],
            ['count' => 6, 'answered' => true, 'hidden' => false, 'reported' => true],
            ['count' => 8, 'answered' => false, 'hidden' => false, 'reported' => false],
            ['count' => 4, 'answered' => true, 'hidden' => true, 'reported' => false],
            ['count' => 2, 'answered' => false, 'hidden' => true, 'reported' => true],
        ];

        $now = now();
        $created = [];

        DB::transaction(function () use ($states, $customers, $products, $now, &$created): void {
            // Customer-facing Q&A: (almost) every product gets one or two answered questions
            // so its PDP shows a lived-in Questions & answers section with vendor replies.
            foreach ($products as $product) {
                foreach (range(1, mt_rand(1, 2)) as $ignored) {
                    $qa = collect(self::ANSWERED)->random();
                    $created[] = $this->createQuestion($product, $qa['question'], $qa['answer'], $customers, true, false, false, $now);
                }
            }

            // Moderation-state samples for the admin Q&A queue.
            foreach ($states as $state) {
                foreach (range(1, $state['count']) as $ignored) {
                    $qa = collect(self::ANSWERED)->random();
                    $unansweredText = ! $state['answered'] && mt_rand(0, 3) === 0
                        ? collect(self::UNANSWERED)->random()
                        : $qa['question'];

                    $product = $products->random();
                    $created[] = $this->createQuestion($product, $unansweredText, $qa['answer'], $customers, $state['answered'], $state['hidden'], $state['reported'], $now);
                }
            }
        });

        DB::transaction(function () use (&$created): void {
            foreach ($created as $entry) {
                if (! $entry['answered']) {
                    continue;
                }

                $answerAt = $entry['createdAt']->copy()->addHours(mt_rand(5, 72))->setMinutes(mt_rand(0, 59));

                $answer = ProductAnswer::factory()->make([
                    'product_question_id' => $entry['question']->id,
                    'vendor_id' => $entry['product']->vendor_id,
                    'answer' => $entry['answer'],
                    'is_hidden' => $entry['hidden'] && mt_rand(0, 2) === 0,
                ]);
                $answer->created_at = $answerAt;
                $answer->updated_at = $answerAt;
                $answer->save();
            }
        });

        $this->command?->info('Seeded '.count($created).' product questions with vendor answers.');
    }

    /**
     * Persist a single question with a deterministic created_at (the column is not mass assignable).
     *
     * @param  array<int, int>  $customers
     * @return array{question: ProductQuestion, product: Product, answer: string, answered: bool, hidden: bool, createdAt: Carbon}
     */
    private function createQuestion(Product $product, string $text, string $answer, array $customers, bool $answered, bool $hidden, bool $reported, Carbon $now): array
    {
        $createdAt = $now->copy()->subDays(mt_rand(0, 75))->subHours(mt_rand(0, 23))->setMinutes(mt_rand(0, 59));

        $question = ProductQuestion::factory()->make([
            'product_id' => $product->id,
            'user_id' => collect($customers)->random(),
            'question' => $text,
            'is_answered' => $answered,
            'is_hidden' => $hidden,
            'reported_at' => $reported
                ? $createdAt->copy()->addDays(mt_rand(0, 5))->addHours(mt_rand(0, 12))
                : null,
            'report_reason' => $reported ? collect(self::REASONS)->random() : null,
        ]);
        $question->created_at = $createdAt;
        $question->updated_at = $createdAt;
        $question->save();

        return [
            'question' => $question,
            'product' => $product,
            'answer' => $answer,
            'answered' => $answered,
            'hidden' => $hidden,
            'createdAt' => $createdAt,
        ];
    }
}
