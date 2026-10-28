<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $templates = EmailTemplate::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');

                return $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                    ->orWhere('key', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->paginate((int) ($request->input('per_page', 15)));

        return $this->paginated(__('Email templates fetched successfully.'), $templates, fn ($items) => $items->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:email_templates,key'],
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $template = EmailTemplate::create($data);

        return $this->success(__('Email template created successfully.'), $template, 201);
    }

    public function show(EmailTemplate $emailTemplate): JsonResponse
    {
        return $this->success(__('Email template fetched successfully.'), $emailTemplate);
    }

    public function update(Request $request, EmailTemplate $emailTemplate): JsonResponse
    {
        $data = $request->validate([
            'key' => ['sometimes', 'string', 'max:80', 'alpha_dash', 'unique:email_templates,key,'.$emailTemplate->id],
            'name' => ['sometimes', 'string', 'max:255'],
            'subject' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $emailTemplate->update($data);

        return $this->success(__('Email template updated successfully.'), $emailTemplate->fresh());
    }

    public function destroy(EmailTemplate $emailTemplate): JsonResponse
    {
        $emailTemplate->delete();

        return $this->success(__('Email template deleted successfully.'));
    }

    public function toggle(EmailTemplate $emailTemplate): JsonResponse
    {
        $emailTemplate->update(['is_active' => ! $emailTemplate->is_active]);

        return $this->success(__('Email template status updated.'), $emailTemplate->fresh());
    }
}
