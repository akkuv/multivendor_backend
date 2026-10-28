<?php

use App\Models\HomepageSection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            'VALUE_PROPS' => [
                'items' => [
                    ['icon' => 'gem', 'title' => 'Curated selection', 'description' => 'Every piece chosen by our editors for enduring quality and craft.'],
                    ['icon' => 'truck', 'title' => 'Lightning shipping', 'description' => 'Complimentary, fully insured delivery on every order over the threshold.'],
                    ['icon' => 'shield_check', 'title' => 'The 30-day promise', 'description' => 'Effortless returns and exchanges within thirty days of receiving your order.'],
                    ['icon' => 'sparkles', 'title' => 'Concierge support', 'description' => 'A dedicated concierge is on hand for styling advice and order care.'],
                ],
            ],
        ];

        $desiredOrder = [
            'HERO',
            'VALUE_PROPS',
            'CATEGORIES',
            'BEST_SELLERS',
            'TESTIMONIALS',
            'VENDOR_STORES',
        ];

        if (! HomepageSection::query()->exists()) {
            return;
        }

        DB::transaction(function () use ($defaults, $desiredOrder): void {
            $sections = HomepageSection::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            $byType = $sections->groupBy(fn (HomepageSection $section) => $section->type->value);
            $usedIds = [];

            $position = 0;

            foreach ($desiredOrder as $type) {
                $isDefault = array_key_exists($type, $defaults);

                if ($byType->has($type)) {
                    foreach ($byType[$type] as $section) {
                        $usedIds[] = $section->id;
                        $section->update(['sort_order' => $position++]);
                    }

                    continue;
                }

                if ($isDefault) {
                    HomepageSection::query()->insert([
                        'type' => $type,
                        'title' => null,
                        'subtitle' => null,
                        'config' => json_encode($defaults[$type]),
                        'sort_order' => $position++,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            foreach ($sections as $section) {
                if (! in_array($section->id, $usedIds, true)) {
                    $section->update(['sort_order' => $position++]);
                }
            }
        });
    }

    public function down(): void
    {
        HomepageSection::query()
            ->whereIn('type', ['VALUE_PROPS'])
            ->delete();
    }
};
