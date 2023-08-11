<?php

declare(strict_types=1);

namespace App\Jobs\Imports\Concerns;

trait TracksValidationErrors
{
    protected array $validationErrors = [];

    public function onValidationError(array $errors): void
    {
        $this->validationErrors['row:'.($this->currentIndex + 2)] = $errors;
    }

    public function validationErrors(): array
    {
        return $this->validationErrors;
    }
}
