<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\VerifyTinyVmGateway;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['prefix' => 'broadcasting', 'middleware' => ['web', 'auth']])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [HandleInertiaRequests::class]);
        $middleware->alias(['tinyvm.gateway' => VerifyTinyVmGateway::class]);
    })
    ->withExceptions(fn (Exceptions $exceptions) => null)
    ->create();
