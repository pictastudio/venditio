<?php

namespace PictaStudio\Venditio;

use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class Venditio
{
    public static function configureUsing(Closure $callback): void
    {
        $callback(app('venditio'));
    }

    public static function configureRateLimiting(string $limiter, int $maxAttempts = 600): void
    {
        RateLimiter::for($limiter, fn (Request $request) => (
            Limit::perMinute($maxAttempts)->by($request->user()?->id ?: $request->ip())
        ));
    }
}
