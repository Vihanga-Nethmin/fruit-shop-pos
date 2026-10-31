<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Idempotent
{
    private const TEMP_USER     = '00000000-0000-0000-0000-000000000000'; // TEMP: gateway token eken replace
    private const STALE_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->isMethod('POST')) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');

        if ($key === null || $key === '') {
            if (config('idempotency.required')) {
                return $this->error('IDEMPOTENCY_KEY_REQUIRED', 'The Idempotency-Key header is required for this request.', 400);
            }

            return $next($request);
        }

        if (strlen($key) > 128 || !preg_match('/^[A-Za-z0-9._:\-]+$/', $key)) {
            return $this->error('INVALID_IDEMPOTENCY_KEY', 'The Idempotency-Key must be 1 to 128 characters: letters, digits, . _ : -', 422);
        }

        $route     = $request->route();
        $operation = substr($request->method() . ' ' . ($route ? $route->uri() : $request->path()), 0, 150);
        $caller    = substr($request->header('X-User-Id', self::TEMP_USER), 0, 36);
        $hash      = $this->fingerprint($request);

        $scope = fn () => IdempotencyKey::where('operation', $operation)
            ->where('caller', $caller)
            ->where('idempotency_key', $key);

        $existing = $scope()->first();

        // Expire wela, ho process eka crash wela (in-progress eke athi) nam aluthen patan gannawa
        if ($existing && (
            $existing->expires_at->isPast()
            || ($existing->status === 'IN_PROGRESS' && $existing->created_at->lt(now()->subMinutes(self::STALE_MINUTES)))
        )) {
            $existing->delete();
            $existing = null;
        }

        if ($existing) {
            return $this->respondExisting($existing, $hash);
        }

        try {
            $record = IdempotencyKey::create([
                'operation'       => $operation,
                'caller'          => $caller,
                'idempotency_key' => $key,
                'request_hash'    => $hash,
                'status'          => 'IN_PROGRESS',
                'expires_at'      => now()->addDays((int) config('idempotency.ttl_days', 7)),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Parallel requests 2k: ekak win, anith eka eka thamai balanne
            $existing = $scope()->first();

            return $existing
                ? $this->respondExisting($existing, $hash)
                : $this->error('REQUEST_IN_PROGRESS', 'A request with this key is already being processed.', 409);
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $record->delete();   // fail unoth key eka release wenawa
            throw $e;
        }

        $status = $response->getStatusCode();

        if ($status >= 200 && $status < 300) {
            $record->update([
                'status'          => 'COMPLETED',
                'response_status' => $status,
                'response_body'   => $response->getContent(),
            ]);
        } else {
            $record->delete();   // 4xx / 5xx: body eka hariyata karala same key ekak ayeth yawanna puluwan
        }

        return $response;
    }

    private function respondExisting(IdempotencyKey $existing, string $hash): Response
    {
        if (!hash_equals($existing->request_hash, $hash)) {
            return $this->error('IDEMPOTENCY_CONFLICT', 'This Idempotency-Key was already used with a different request.', 409);
        }

        if ($existing->status === 'COMPLETED') {
            return JsonResponse::fromJsonString(
                $existing->response_body,
                $existing->response_status,
                ['Idempotency-Replayed' => 'true']
            );
        }

        return $this->error('REQUEST_IN_PROGRESS', 'A request with this key is already being processed.', 409);
    }

    // Route params + input, key order wenas unath same hash eka
    private function fingerprint(Request $request): string
    {
        $route = $request->route();

        return hash('sha256', json_encode($this->canonical([
            'params' => $route ? $route->parameters() : [],
            'input'  => $request->all(),
        ])));
    }

    private function canonical($value)
    {
        if (is_array($value)) {
            ksort($value);
            foreach ($value as $k => $v) {
                $value[$k] = $this->canonical($v);
            }
        }

        return $value;
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}