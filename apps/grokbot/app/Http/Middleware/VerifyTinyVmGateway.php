<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyTinyVmGateway
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('tinyvm.gateway.token');
        $provided = (string) $request->bearerToken();

        abort_if($expected === '' || ! hash_equals($expected, $provided), 401, 'Invalid TinyVM gateway credential.');

        return $next($request);
    }
}
