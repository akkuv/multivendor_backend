<?php

namespace App\Http\Requests\Api\V1\Support;

use Illuminate\Foundation\Http\FormRequest;

class SendSupportMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:4000'],
            // URL returned by the shared image upload endpoint. Optional and
            // strictly a URL so messages never carry file bytes or scripts.
            'image_url' => ['nullable', 'string', 'url', 'max:2048'],
            // Vendor only: asking the assistant to stay quiet turns the bot off
            // for this message so an admin reads the human intent instead.
            'skip_bot' => ['sometimes', 'boolean'],
        ];
    }
}
