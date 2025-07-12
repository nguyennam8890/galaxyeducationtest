<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class RateLimitByIp
{
    public function handle2(Request $request, Closure $next)
    {
        $ip = $request->ip();
        $key = 'rl:' . app()->environment() . ':' . $ip;
        $maxAttempts = 5;
        $decaySeconds = 60;

        try {
            $current = Redis::incr($key);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Redis unavailable'], 503);
        }

        if ($current == 1) {
            // Nếu là lần đầu thì set TTL (thời gian hết hạn)
            Redis::expire($key, $decaySeconds);
        }
        //Kiểm tra có vượt quá giới hạn chưa
        if ($current > $maxAttempts) {
            // trả về số giây còn lại
            $ttl = Redis::ttl($key);
            return response()->json([
                'message' => 'Too many requests',
                'retry_after' => $ttl
            ], 429);
        }

        return $next($request);
    }

    public function handle(Request $request, Closure $next)
    {
        $ip = $request->ip();
        $key = 'rate_limit:' . app()->environment() . ':' . $ip;

        $maxAttempts = 5;
        $decaySeconds = 60;

        $current = Cache::get($key, 0);

        if ($current >= $maxAttempts) {
            $ttl = Cache::get($key . ':ttl');

            return response()->json([
                'message' => 'Too many requests',
                'retry_after' => $ttl ?? 'unknown'
            ], 429);
        }

        // Nếu lần đầu: đặt TTL mới
        if ($current === 0) {
            Cache::put($key . ':ttl', $decaySeconds, $decaySeconds);
        }

        // Tăng và đặt lại thời gian sống
        Cache::put($key, $current + 1, $decaySeconds);

        return $next($request);
    }
}
