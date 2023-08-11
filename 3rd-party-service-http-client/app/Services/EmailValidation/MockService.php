<?php

declare(strict_types=1);

namespace App\app\Services\EmailValidation;

use App\app\Services\EmailValidation\Contracts\EmailValidationService;

class MockService implements EmailValidationService
{
    public function withValidCodes(array $codes = null): self
    {
        return $this;
    }

    public function withHttpOptions(array $options): self
    {
        return $this;
    }

    public function validate(string $email): bool
    {
        return true;
    }
}
