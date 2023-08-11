<?php

declare(strict_types=1);

namespace App\Jobs\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class TempFile
{
    protected string $filePath;

    protected string $prefix = 'laravel_temp_';

    public function __construct(string $prefix = null)
    {
        $this->filePath = tempnam(sys_get_temp_dir(), $prefix ?? $this->prefix);
    }

    public static function fresh(): self
    {
        return new self();
    }

    public function withSuffix(string $suffix): self
    {
        $newPath = $this->filePath.$suffix;
        File::move($this->filePath, $newPath);
        $this->filePath = $newPath;

        return $this;
    }

    public function write($stream): self
    {
        File::put($this->filePath, $stream, true);

        return $this;
    }

    public function getPath(): string
    {
        return $this->filePath;
    }

    public function __toString(): string
    {
        return $this->getPath();
    }

    public function delete(): bool
    {
        $result = File::delete($this->filePath);

        if (! $result) {
            Log::warning('Failed to delete temp file', [
                'path' => $this->getPath(),
                'error' => error_get_last(),
            ]);
        }

        return $result;
    }
}
