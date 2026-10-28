<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'support_conversation_id' => $this->support_conversation_id,
            'sender_user_id' => $this->sender_user_id,
            'sender_role' => $this->sender_role,
            'sender_name' => $this->authorName(),
            'body' => $this->body,
            'image_url' => $this->image_url,
            'is_bot' => (bool) $this->is_bot,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
            'sender' => $this->whenLoaded('sender', fn () => [
                'id' => $this->sender?->id,
                'name' => $this->sender?->name,
                'avatar' => $this->sender?->avatar,
            ]),
        ];
    }
}
