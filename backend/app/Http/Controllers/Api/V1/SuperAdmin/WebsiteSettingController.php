<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\Settings\WebsiteSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteSettingController extends Controller
{
    public function __construct(protected WebsiteSettingsService $settings) {}

    public function index(): JsonResponse
    {
        return $this->success(__('Website settings fetched successfully.'), $this->settings->publicContent());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'navbar' => ['sometimes', 'array'],
            'footer' => ['sometimes', 'array'],
            'mail' => ['sometimes', 'array'],
        ]);

        $current = $this->settings->settings();
        $navbar = $this->settings->merge(WebsiteSettingsService::defaultNavbar(), $current->navbar ?? []);
        $footer = $this->settings->merge(WebsiteSettingsService::defaultFooter(), $current->footer ?? []);
        $mail = $this->settings->merge(WebsiteSettingsService::defaultMail(), $current->mail ?? []);

        if (array_key_exists('navbar', $data)) {
            $navbar = $this->normalizeNavbar(
                $this->settings->merge($navbar, $data['navbar']),
                $current->navbar['links'] ?? [],
            );
        }

        if (array_key_exists('footer', $data)) {
            $footer = $this->normalizeFooter($this->settings->merge($footer, $data['footer']));
        }

        if (array_key_exists('mail', $data)) {
            $mailData = $data['mail'];
            $mail = [
                'mail_mailer' => trim((string) ($mailData['mail_mailer'] ?? $mail['mail_mailer'] ?? 'smtp')),
                'mail_host' => trim((string) ($mailData['mail_host'] ?? $mail['mail_host'] ?? '')),
                'mail_port' => (string) ($mailData['mail_port'] ?? $mail['mail_port'] ?? '587'),
                'mail_username' => trim((string) ($mailData['mail_username'] ?? $mail['mail_username'] ?? '')),
                'mail_password' => trim((string) ($mailData['mail_password'] ?? $mail['mail_password'] ?? '')),
                'mail_encryption' => trim((string) ($mailData['mail_encryption'] ?? $mail['mail_encryption'] ?? 'tls')),
                'mail_from_address' => trim((string) ($mailData['mail_from_address'] ?? $mail['mail_from_address'] ?? '')),
                'mail_from_name' => trim((string) ($mailData['mail_from_name'] ?? $mail['mail_from_name'] ?? '')),
            ];
        }

        $current->update([
            'navbar' => $navbar,
            'footer' => $footer,
            'mail' => $mail,
        ]);

        $this->settings->forget();

        return $this->success(__('Website settings updated successfully.'), $this->settings->publicContent());
    }

    private function normalizeNavbar(array $navbar, array $storedLinks = []): array
    {
        $links = collect($navbar['links'] ?? [])
            ->filter(fn ($link) => is_array($link))
            ->values()
            ->map(fn (array $link) => [
                'label' => trim((string) ($link['label'] ?? '')),
                'url' => trim((string) ($link['url'] ?? '')),
                'new_tab' => filter_var($link['new_tab'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'departments' => $this->normalizeLinkDepartments(
                    array_key_exists('departments', $link)
                        ? $link['departments']
                        : $this->storedLinkDepartments($link, $storedLinks),
                ),
            ])
            ->filter(fn (array $link) => $link['label'] !== '' && $link['url'] !== '')
            ->values()
            ->all();

        $announcement = $navbar['announcement'] ?? [];
        $departments = $navbar['departments'] ?? [];

        return [
            'announcement' => [
                'enabled' => filter_var($announcement['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'text' => trim((string) ($announcement['text'] ?? '')),
                'url' => $this->nullableUrl($announcement['url'] ?? null),
            ],
            'logo' => [
                'text' => trim((string) ($navbar['logo']['text'] ?? '')) ?: 'Waplia',
                'image' => $this->nullableUrl($navbar['logo']['image'] ?? null),
                'url' => trim((string) ($navbar['logo']['url'] ?? '/')) ?: '/',
            ],
            'links' => $links,
            'departments' => [
                'enabled' => filter_var($departments['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'category_ids' => isset($departments['category_ids']) && is_array($departments['category_ids'])
                    ? array_values(array_unique(array_map(
                        fn ($id) => (int) $id,
                        array_filter($departments['category_ids'], fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false),
                    )))
                    : null,
                'show_all' => filter_var($departments['show_all'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'show_shop_all' => filter_var($departments['show_shop_all'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'show_collections' => filter_var($departments['show_collections'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ],
        ];
    }

    /**
     * Look up the stored departments bar override for a navigation link.
     *
     * Used when an incoming payload omits the `departments` key entirely, so
     * a client that does not know about the feature can never wipe the
     * per-page configuration that is already stored.
     */
    private function storedLinkDepartments(array $link, array $storedLinks): mixed
    {
        if ($storedLinks === []) {
            return null;
        }

        $url = trim((string) ($link['url'] ?? ''));
        $label = trim((string) ($link['label'] ?? ''));

        $match = collect($storedLinks)
            ->filter(fn ($stored) => is_array($stored))
            ->first(fn (array $stored) => trim((string) ($stored['url'] ?? '')) === $url
                && trim((string) ($stored['label'] ?? '')) === $label)
            ?? collect($storedLinks)
                ->filter(fn ($stored) => is_array($stored))
                ->first(fn (array $stored) => trim((string) ($stored['url'] ?? '')) === $url);

        return $match['departments'] ?? null;
    }

    /**
     * Normalize the per-navigation-link departments bar override.
     *
     * A null override means the link falls back to the global departments bar.
     */
    private function normalizeLinkDepartments(mixed $departments): ?array
    {
        if (! is_array($departments)) {
            return null;
        }

        return [
            'enabled' => filter_var($departments['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'category_ids' => isset($departments['category_ids']) && is_array($departments['category_ids'])
                ? array_values(array_unique(array_map(
                    fn ($id) => (int) $id,
                    array_filter($departments['category_ids'], fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false),
                )))
                : null,
            'show_all' => filter_var($departments['show_all'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'show_shop_all' => filter_var($departments['show_shop_all'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'show_collections' => filter_var($departments['show_collections'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function normalizeFooter(array $footer): array
    {
        $columns = collect($footer['columns'] ?? [])
            ->filter(fn ($column) => is_array($column))
            ->values()
            ->map(function (array $column) {
                $links = collect($column['links'] ?? [])
                    ->filter(fn ($link) => is_array($link))
                    ->values()
                    ->map(fn (array $link) => [
                        'label' => trim((string) ($link['label'] ?? '')),
                        'url' => trim((string) ($link['url'] ?? '')),
                        'new_tab' => filter_var($link['new_tab'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    ])
                    ->filter(fn (array $link) => $link['label'] !== '' && $link['url'] !== '')
                    ->values()
                    ->all();

                return [
                    'title' => trim((string) ($column['title'] ?? '')),
                    'links' => $links,
                ];
            })
            ->filter(fn (array $column) => $column['title'] !== '')
            ->values()
            ->all();

        $social = $footer['social'] ?? [];
        $contact = $footer['contact'] ?? [];

        $socialLinks = collect($social['links'] ?? [])
            ->filter(fn ($link) => is_array($link))
            ->values()
            ->map(function (array $link) {
                $platform = strtolower(trim((string) ($link['platform'] ?? '')));

                if (! in_array($platform, WebsiteSettingsService::SOCIAL_PLATFORMS, true)) {
                    $platform = 'website';
                }

                return [
                    'platform' => $platform,
                    'url' => $this->nullableUrl($link['url'] ?? null),
                    'label' => trim((string) ($link['label'] ?? '')),
                    'enabled' => filter_var($link['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                ];
            })
            ->filter(fn (array $link) => $link['url'] !== null)
            ->values()
            ->all();

        return [
            'about' => [
                'text' => trim((string) ($footer['about']['text'] ?? '')),
            ],
            'columns' => $columns,
            'social' => [
                'enabled' => filter_var($social['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'title' => trim((string) ($social['title'] ?? '')) !== '' ? trim((string) $social['title']) : 'Follow us',
                'description' => trim((string) ($social['description'] ?? '')),
                'style' => ($social['style'] ?? 'icons') === 'labeled' ? 'labeled' : 'icons',
                'links' => $socialLinks,
            ],
            'contact' => [
                'email' => trim((string) ($contact['email'] ?? '')),
                'phone' => trim((string) ($contact['phone'] ?? '')),
                'address' => trim((string) ($contact['address'] ?? '')),
            ],
            'copyright' => trim((string) ($footer['copyright'] ?? '')),
        ];
    }

    private function nullableUrl(mixed $url): ?string
    {
        $url = trim((string) $url);

        return $url === '' ? null : $url;
    }
}
