<?php

declare(strict_types=1);

namespace App\Jobs\Models\Traits;

use App\Support\Uploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

trait HasFile
{
    public static function bootHasFile(): void
    {
        static::deleted(function (self $resource) {
            Storage::delete($resource->file);
        });
    }

    public function getFileStorageDiskDir(): string
    {
        return 'private/file-imports';
    }

    public function initializeHasFile(): void
    {
        $this->appends[] = 'file_url';
    }

    public function setFileAttribute(UploadedFile $uploadedFile): void
    {
        $this->attributes['file'] = Storage::putFile(Uploader::dirPath($this->getFileStorageDiskDir()), $uploadedFile, 'private');
    }

    public function getFileUrlAttribute(): string
    {
        return Storage::temporaryUrl($this->file, Date::now()->addMinutes(30));
    }
}
