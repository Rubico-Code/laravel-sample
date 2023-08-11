<?php

declare(strict_types=1);

namespace App\app\Services\EmailValidation\Contracts;

interface EmailValidationService
{
    public function withValidCodes(array $codes = null): self;

    public function withHttpOptions(array $options): self;

    public function validate(string $email): bool;
}
