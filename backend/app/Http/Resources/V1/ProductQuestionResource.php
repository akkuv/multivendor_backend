<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductQuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'question' => $this->question,
            'is_answered' => (bool) $this->is_answered,
            'is_hidden' => (bool) $this->is_hidden,
            'reported_at' => $this->reported_at,
            'report_reason' => $this->report_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ]),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product?->id,
                'name' => $this->product?->name,
            ]),
            'answers' => $this->when($this->relationLoaded('answers'), fn () => $this->answers
                ->where('is_hidden', false)
                ->map(fn ($answer) => [
                    'id' => $answer->id,
                    'answer' => $answer->answer,
                    'vendor' => $answer->vendor ? ['id' => $answer->vendor->id, 'store_name' => $answer->vendor->store_name] : null,
                    'created_at' => $answer->created_at,
                ])
                ->values()),
            'answers_count' => $this->when($this->relationLoaded('answers'), fn () => $this->answers->where('is_hidden', false)->count()),
        ];
    }
}
