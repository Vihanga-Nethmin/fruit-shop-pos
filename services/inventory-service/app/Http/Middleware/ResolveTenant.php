<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        // TEMP: gateway hadapu passe token eken ganna widiyata wenas karanawa
        $tenantId = $request->header('X-Tenant-Id');

        if (!$tenantId) {
            return response()->json([
                'error' => ['code' => 'TENANT_MISSING', 'message' => 'Tenant not resolved'],
            ], 401);
        }

        app()->instance('tenant_id', $tenantId);

        return $next($request);
    }
}