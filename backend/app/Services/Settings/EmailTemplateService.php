<?php

namespace App\Services\Settings;

use App\Models\EmailTemplate;

class EmailTemplateService
{
    public const DEFAULT_VARIABLES = [
        'customer_name',
        'order_number',
        'amount',
        'tracking_number',
        'vendor_name',
        'site_name',
    ];

    public function render(string $key, array $variables = []): ?EmailTemplate
    {
        $template = EmailTemplate::query()->active()->where('key', $key)->first();

        if (! $template) {
            return null;
        }

        $variables = array_merge(['site_name' => config('app.name')], $variables);

        $subject = $this->substitute($template->subject, $variables);
        $body = $this->substitute($template->body, $variables);

        $template->setAttribute('subject', $subject);
        $template->setAttribute('body', $body);

        return $template;
    }

    private function substitute(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace('{{'.$key.'}}', (string) $value, $content);
        }

        return $content;
    }
}
