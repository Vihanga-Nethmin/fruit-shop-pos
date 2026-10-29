<?php

namespace App\Http\Middleware;

use App\Models\AuditEvent;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuditRequests
{
    private const TEMP_USER = '00000000-0000-0000-0000-000000000000'; // TEMP: gateway token eken replace

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $isExport = $request->isMethod('GET') && $request->query('format') === 'csv';

        // Reads log wenne nae, writes saha exports witharai
        if ($request->isMethod('GET') && !$isExport) {
            return $response;
        }

        try {
            $this->record($request, $response, $isExport);
        } catch (Throwable $e) {
            report($e); // audit fail unata business response eka nawathinne nae, log eke thiyenawa
        }

        return $response;
    }

    private function record(Request $request, Response $response, bool $isExport): void
    {
        $route  = $request->route();
        $uri    = $route ? $route->uri() : $request->path();
        $params = $route ? $route->parameters() : [];
        $rel    = Str::after($uri, 'api/v1/');
        $type   = trim(Str::before($rel, '{'), '/') ?: $rel;
        $ref    = $params['id'] ?? $params['lineId'] ?? $params['requestId'] ?? null;

        $body = null;
        if ($response instanceof JsonResponse) {
            $body = $response->getData(true);
            $ref  = $ref ?? ($body['data']['id'] ?? $body['data']['transfer_id'] ?? null);
        }

        $status  = $response->getStatusCode();
        $outcome = match (true) {
            $status >= 200 && $status < 400 => 'SUCCESS',
            in_array($status, [401, 403], true) => 'DENIED',
            default => 'FAILED',
        };

        $payload = $this->redact($isExport ? $request->query() : $request->all());
        if (strlen((string) json_encode($payload)) > 8000) {
            $payload = ['truncated' => true];
        }

        AuditEvent::create([
            'occurred_at'    => now(),
            'actor_id'       => substr($request->header('X-User-Id', self::TEMP_USER), 0, 36),
            'actor_role'     => substr($request->header('X-User-Role', 'OM'), 0, 10), // TEMP
            'method'         => $request->method(),
            'route'          => Str::limit($uri, 150, ''),
            'path'           => Str::limit('/' . $request->path(), 255, ''),
            'status_code'    => $status,
            'outcome'        => $outcome,
            'entity_type'    => Str::limit($type, 80, ''),
            'entity_ref'     => $ref ? Str::limit((string) $ref, 100, '') : null,
            'payload'        => $payload ?: null,
            'ip'             => $request->ip(),
            'correlation_id' => $request->header('X-Correlation-Id') ?: (string) Str::uuid(),
        ]);
    }

    // Password, token, card wage ewa log karanne nae
    private function redact(array $data): array
    {
        foreach ($data as $k => $v) {
            if (preg_match('/pass|token|secret|card|cvv/i', (string) $k)) {
                $data[$k] = '[redacted]';
            } elseif (is_array($v)) {
                $data[$k] = $this->redact($v);
            }
        }

        return $data;
    }
}