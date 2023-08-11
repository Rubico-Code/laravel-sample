<?php

declare(strict_types=1);

namespace App\app\Services\EmailValidation;

use App\app\Services\EmailValidation\Contracts\EmailValidationService;
use App\Services\Middleware\GuzzleHttpCacheMiddleware;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Http\Client\PendingRequest;

class DebounceService implements EmailValidationService
{
    protected PendingRequest $client;

    protected const BASE_URL = 'https://api.debounce.io/v1/';

    protected ?array $validCodes = null;

    public function __construct(protected HttpClient $factory, protected array $config)
    {
        $this->client = $this->createClient();
    }

    protected function createClient(): PendingRequest
    {
        return $this->factory
            ->withMiddleware(new GuzzleHttpCacheMiddleware)
            ->withOptions([
                RequestOptions::VERSION => 2, // enable http2
            ])
            ->acceptJson()
            ->timeout(15) // Half of HTTP Web-server timeout
            ->throw()
            ->retry(1, 3000);
    }

    public function withValidCodes(array $codes = null): self
    {
        $this->validCodes = $codes;

        return $this;
    }

    public function withHttpOptions(array $options): static
    {
        $this->client->withOptions($options);

        return $this;
    }

    public function validate(string $email): bool
    {
        $response = $this->client
            ->get(self::BASE_URL, [
                'api' => $this->config['private_key'],
                'email' => $email,
            ]);

        return $this->isValidCode((int) $response->json('debounce.code'));
    }

    protected function isValidCode(int $code): bool
    {
        return in_array($code, $this->positiveCodes(), true);
    }

    protected function positiveCodes(): array
    {
        return $this->validCodes ?? ResultCodeEnum::defaults();
    }
}
