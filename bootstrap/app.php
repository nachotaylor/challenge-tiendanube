<?php

use App\Exceptions\JsonServerException;
use App\Exceptions\MalformedRecordException;
use App\Exceptions\NumeratorUnavailableException;
use App\Exceptions\ResourceNotFoundException;
use App\Exceptions\TransactionCreationFailedException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(fn(ResourceNotFoundException $e) => response()->json(['error' => $e->getMessage()], 404));
        $exceptions->render(fn(NumeratorUnavailableException $e) => response()->json(['error' => $e->getMessage()], 503));
        $exceptions->render(fn(TransactionCreationFailedException $e) => response()->json(['error' => $e->getMessage()], 502));
        $exceptions->render(fn(JsonServerException $e) => response()->json(['error' => $e->getMessage()], 502));
        $exceptions->render(fn(MalformedRecordException $e) => response()->json(['error' => $e->getMessage()], 502));
    })->create();
