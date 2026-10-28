<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Services\Reporting\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reportService) {}

    public function show(Request $request, string $type): JsonResponse
    {
        $reportType = ReportType::tryFrom($type);
        $vendor = $request->user()->vendorProfile;

        if (! $vendor) {
            return $this->error(__('Vendor profile not found.'), null, 404);
        }

        if (! $reportType || ! in_array($reportType, ReportType::forVendor(), true)) {
            return $this->error(__('Unsupported report type for vendors.'), null, 422);
        }

        $filters = $this->filters($request);
        $data = $this->reportService->{$this->method($reportType)}($filters, $vendor->id);

        return $this->success(__('Report generated successfully.'), $this->shape($data));
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        $vendor = $request->user()->vendorProfile;
        $reportType = ReportType::tryFrom($type);

        if (! $vendor) {
            abort(404, __('Vendor profile not found.'));
        }

        if (! $reportType || ! in_array($reportType, ReportType::forVendor(), true)) {
            abort(422, __('Unsupported report type for vendors.'));
        }

        $filters = $this->filters($request);
        $csv = $this->reportService->csv($type, $filters, $vendor->id, true);

        $filename = sprintf('%s_report_%s.csv', $type, now()->format('Y-m-d_His'));

        return response()->streamDownload(function () use ($csv) {
            $handle = fopen('php://output', 'wb');

            if ($csv['headers']) {
                fputcsv($handle, $csv['headers']);
            }
            foreach ($csv['rows'] as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return [
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'category_id' => $request->input('category_id'),
        ];
    }

    private function method(ReportType $type): string
    {
        return match ($type) {
            ReportType::Sales => 'sales',
            ReportType::Orders => 'orders',
            ReportType::Products => 'products',
            ReportType::Commission => 'commissions',
            ReportType::Wallet => 'wallet',
            ReportType::Payouts => 'payouts',
            ReportType::Returns => 'returns',
        };
    }

    /** @param  array<string, mixed>  $data */
    private function shape(array $data): array
    {
        $paginator = $data['items'];

        return [
            'summary' => $data['summary'],
            'items' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
