<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReportSupport;
use App\Models\AuditEvent;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditController extends Controller
{
    use ReportSupport;

    public function index(Request $request)
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }

        $f = $request->validate([
            'actor_id'    => ['nullable', 'string', 'max:36'],
            'method'      => ['nullable', Rule::in(['GET', 'POST', 'PATCH', 'PUT', 'DELETE'])],
            'outcome'     => ['nullable', Rule::in(['SUCCESS', 'DENIED', 'FAILED'])],
            'entity_type' => ['nullable', 'string', 'max:80'],
            'entity_ref'  => ['nullable', 'string', 'max:100'],
            'format'      => ['nullable', Rule::in(['json', 'csv'])],
        ]);
        $range = $this->range($request);

        $q = AuditEvent::query()
            ->whereBetween('occurred_at', [$range['start'], $range['end']])
            ->orderByDesc('occurred_at');

        foreach (['actor_id', 'method', 'outcome', 'entity_type', 'entity_ref'] as $k) {
            if (!empty($f[$k])) {
                $q->where($k, $f[$k]);
            }
        }

        if (($f['format'] ?? 'json') === 'csv') {
            $rows = $q->limit(5000)->get()->map(fn ($e) => [
                'occurred_at' => $e->occurred_at->toIso8601String(),
                'actor_id'    => $e->actor_id,
                'actor_role'  => $e->actor_role,
                'method'      => $e->method,
                'path'        => $e->path,
                'status_code' => $e->status_code,
                'outcome'     => $e->outcome,
                'entity_type' => $e->entity_type,
                'entity_ref'  => $e->entity_ref,
                'payload'     => json_encode($e->payload),
            ])->all();

            return $this->csv($rows, "audit-{$range['from']}-to-{$range['to']}.csv");
        }

        return response()->json(['data' => $q->paginate(50)]);
    }
}