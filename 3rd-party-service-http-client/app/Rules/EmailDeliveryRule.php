<?php

declare(strict_types=1);

namespace Rules;

use App\Services\EmailValidation\Contracts\EmailValidationService;
use Closure;
use Exception;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use function App\Rules\app;
use function App\Rules\report;
use function App\Rules\throw_if;

class EmailDeliveryRule implements ValidationRule
{
    protected bool $throwOnErrors = false;

    protected ?array $validCodes = null;

    protected array $httpOptions = [];

    public function __construct()
    {
        //
    }

    public static function make(): self
    {
        return new static();
    }

    public function throwOnErrors(): self
    {
        $this->throwOnErrors = true;

        return $this;
    }

    public function withValidCodes(?array $codes = null): self
    {
        $this->validCodes = $codes;

        return $this;
    }

    public function withHttpOptions(array $options): self
    {
        $this->httpOptions = $options;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $valid = app(EmailValidationService::class)
                ->withValidCodes($this->validCodes)
                ->withHttpOptions($this->httpOptions)
                ->validate($value);
        } catch (ConnectionException $exception) {
            $this->reportOrLogException($exception);

            // Send a different message on timeout errors
            $fail('Failed to connect to email validation service. Try again.');

            return;
        } catch (RequestException $exception) {
            $this->reportOrLogException($exception);

            $fail('Unable to check :attribute for delivery. Try again.');

            return;
        }

        if (! $valid) {
            $fail('The :attribute is not a deliverable email address.');
        }
    }

    protected function reportOrLogException(Exception $exception)
    {
        throw_if($this->throwOnErrors, $exception);

        if (app()->hasDebugModeEnabled()) {
            report($exception);
        } else {
            Log::warning($exception);
        }
    }
}
