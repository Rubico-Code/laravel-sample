<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use Closure;
use Illuminate\Support\Facades\Redis;

class RateLimitJobMiddleware
{
    protected ?string $throttleKey = null;

    public function __construct(protected int $allowedJobsInTimeSpan = 10, protected int $timeSpanInSeconds = 1)
    {
        //
    }

    public static function make(int $allowedJobsInTimeSpan = 10, int $timeSpanInSeconds = 1): self
    {
        return new self($allowedJobsInTimeSpan, $timeSpanInSeconds);
    }

    public function forThrottleKey(string $key): self
    {
        $this->throttleKey = $key;

        return $this;
    }

    public function handle($job, Closure $next): void
    {
        Redis::connection()
            ->throttle($this->throttleKey ?? 'rate_limit_lock_job:'.get_class($job))
            ->block(0)
            ->allow($this->allowedJobsInTimeSpan)
            ->every($this->timeSpanInSeconds)
            ->then(function () use ($job, $next) {
                $next($job);
            }, function () use ($job) {
                $job->release(5);
            });
    }
}
