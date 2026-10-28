<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Availability;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

trait ReportSupport
{
    private function milli($v): int
    {
        return (int) round(((float) $v) * 1000);
    }

    private function cents($v): int
    {
        return (int) round(((float) $v) * 100);
    }

    private function fmt3(int $m): string
    {
        return number_format($m / 1000, 3, '.', '');
    }

    private function fmt2(int $c): string
    {
        return number_format($c / 100, 2, '.', '');
    }

    // Cost data thiyena nisa owner witharai (FR-23)
    private function denied(Request $request)
    {
        if ($request->header('X-User-Role', 'OM') !== 'OM') { // TEMP: gateway token eken replace
            return response()->json([
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Only the owner can view reports.'],
            ], 403);
        }

        return null;
    }

    // CSV formula injection walata: text cell = + - @ walin patan gattoth ' ekak ekathu karanawa
    private function safe($v)
    {
        if (is_string($v) && $v !== '' && !is_numeric($v) && in_array($v[0], ['=', '+', '-', '@'], true)) {
            return "'" . $v;
        }

        return $v;
    }

    private function csv(array $rows, string $filename)
    {
        $headers = $rows ? array_keys($rows[0]) : [];

        return response()->streamDownload(function () use ($rows, $headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $r) {
                fputcsv($out, array_map(fn ($v) => $this->safe($v), array_values($r)));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // from/to (business dates, Asia/Colombo). Default: last 30 days.
    private function range(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to'   => ['nullable', 'date_format:Y-m-d'],
        ]);

        $to   = $data['to'] ?? Availability::today();
        $from = $data['from'] ?? Carbon::parse($to)->subDays(29)->toDateString();

        if ($from > $to) {
            throw ValidationException::withMessages(['from' => ['The from date cannot be after the to date.']]);
        }

        return [
            'from'  => $from,
            'to'    => $to,
            // timestamps UTC walin save wenawa, business day eka Colombo walin
            'start' => Carbon::parse($from, 'Asia/Colombo')->startOfDay()->utc()->toDateTimeString(),
            'end'   => Carbon::parse($to, 'Asia/Colombo')->endOfDay()->utc()->toDateTimeString(),
        ];
    }
}