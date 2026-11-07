<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ReportingController
{
    private function reportingUrl(): string
    {
        return rtrim(
            env('REPORTING_SERVICE_URL', 'http://localhost:8004'),
            '/'
        ) . '/api/v1';
    }

    private function forward(Request $request, string $path)
    {
        $response = Http::acceptJson()
            ->timeout(10)
            ->get(
                $this->reportingUrl() . $path,
                $request->query()
            );

        return response()->json(
            $response->json(),
            $response->status()
        );
    }

    // Dashboard Report
    public function dashboard(Request $request)
    {
        return $this->forward($request, '/dashboard');
    }

    // Sales Report
    public function sales(Request $request)
    {
        return $this->forward($request, '/reports/sales');
    }

    // Stock Report
    public function stock(Request $request)
    {
        return $this->forward($request, '/reports/stock');
    }

    // Stock Ageing Report
    public function stockAgeing(Request $request)
    {
        return $this->forward($request, '/reports/stock-ageing');
    }

    // Wastage Report
    public function wastage(Request $request)
    {
        return $this->forward($request, '/reports/wastage');
    }

    // Margins Report
    public function margins(Request $request)
    {
        return $this->forward($request, '/reports/margins');
    }

    // Purchases Report
    public function purchases(Request $request)
    {
        return $this->forward($request, '/reports/purchases');
    }

    // Count Variances Report
    public function countVariances(Request $request)
    {
        return $this->forward($request, '/reports/count-variances');
    }

    // Audit Events
    public function auditEvents(Request $request)
    {
        return $this->forward($request, '/audit-events');
    }

    // Create CSV Export
    public function createExport(Request $request)
    {
        $response = Http::acceptJson()
            ->timeout(30)
            ->post(
                $this->reportingUrl() . '/exports',
                $request->all()
            );

        return response()->json(
            $response->json(),
            $response->status()
        );
    }

    // Check Export Status
    public function exportStatus(string $exportId)
    {
        $response = Http::acceptJson()
            ->timeout(10)
            ->get(
                $this->reportingUrl() . '/exports/' . $exportId
            );

        return response()->json(
            $response->json(),
            $response->status()
        );
    }

    // Download CSV Export
    public function downloadExport(string $exportId)
    {
        $response = Http::timeout(30)->get(
            $this->reportingUrl() . '/exports/' . $exportId . '/download'
        );

        return response($response->body(), $response->status())
            ->withHeaders([
                'Content-Type' => $response->header(
                    'Content-Type',
                    'text/csv'
                ),
                'Content-Disposition' => $response->header(
                    'Content-Disposition',
                    'attachment; filename="export.csv"'
                ),
            ]);
    }
}
