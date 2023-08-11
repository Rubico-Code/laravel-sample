<?php

declare(strict_types=1);


use App\Services\EmailValidation\Contracts\EmailValidationService;
use App\Services\EmailValidation\DebounceService;
use App\Services\EmailValidation\MockService;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\ServiceProvider;

class EmailValidationServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->scoped(EmailValidationService::class, function () {
            $config = \App\Providers\config('debounce');

            if ($config['enabled']) {
                return new DebounceService(\App\Providers\app(HttpClient::class), $config);
            }

            return new MockService();
        });
    }

    public function provides(): array
    {
        return [
            EmailValidationService::class,
        ];
    }
}
