<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Services\Reporting\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reportService) {}

    public function show(Request $request, string $type): JsonResponse
    {
        $reportType = $this->resolve($type);

        if (! in_array($reportType, ReportType::forAdmin(), true)) {
            return $this->error(__('Unsupported report type.'), null, 422);
        }

        $filters = $this->filters($request);
        $data = $this->reportService->{$this->method($reportType)}($filters);

        return $this->success(__('Report generated successfully.'), $this->shape($data));
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        $reportType = $this->resolve($type);

        if (! in_array($reportType, ReportType::forAdmin(), true)) {
            abort(422, __('Unsupported report type.'));
        }

        $filters = $this->filters($request);
        $csv = $this->reportService->csv($type, $filters, null, false);

        return $this->streamCsv($type, $csv);
    }

    // ------------------------------------------------------------------ //

    private function resolve(string $type): ReportType
    {
        return ReportType::from($type);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return [
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'vendor_id' => $request->input('vendor_id'),
            'category_id' => $request->input('category_id'),
        ];
    }

    private function method(ReportType $type): string
    {
        return match ($type) {
            ReportType::Sales => 'sales',
            ReportType::Orders => 'orders',
            ReportType::Products => 'products',
            ReportType::Vendors => 'vendors',
            ReportType::Customers => 'customers',
            ReportType::Commission => 'commissions',
            ReportType::Payments => 'payments',
            ReportType::Refunds => 'refunds',
            ReportType::Returns => 'returns',
            ReportType::Shipping => 'shipping',
            ReportType::Wallet => 'wallet',
            ReportType::Payouts => 'payouts',
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

    /** @param  array{headers: list<string>, rows: Collection}  $csv */
    private function streamCsv(string $type, array $csv): StreamedResponse
    {
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
}
