<?php

declare(strict_types=1);

namespace App\Jobs\Imports\Concerns;

use Exception;
use Throwable;

trait TrackExceptions
{
    protected array $exceptions = [];

    public function onException(?Throwable $e): void
    {
        if (! $e instanceof Exception) {
            return;
        }

        $this->exceptions['row:'.($this->currentIndex + 2)] = [
            'code' => $e->getCode(),
            'message' => $e->getMessage(),
        ];
    }

    public function exceptions(): array
    {
        return $this->exceptions;
    }
}
