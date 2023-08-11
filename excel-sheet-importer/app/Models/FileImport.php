<?php

declare(strict_types=1);

namespace App\Jobs\Models;

use App\Enums\FileImportType;
use App\Models\Admin;
use App\Models\Scopes\FileImportScope;
use App\Models\Traits\HasFile;
use Illuminate\Contracts\Mail\Attachable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Mail\Attachment;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;

class FileImport extends Model implements Attachable
{
    use Prunable;
    use FileImportScope,
        HasFile;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'finished_at' => 'datetime',
        'meta' => 'array',
    ];

    protected $appends = [
        'type_label',
        'has_errors',
    ];

    public function prunable(): Builder
    {
        return static::where('created_at', '<=', Date::now()->subDays(30));
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return FileImportType::getDescription($this->type);
    }

    public function getHasErrorsAttribute(): bool
    {
        return $this->hasErrors();
    }

    public function hasErrors(): bool
    {
        $exceptions = Arr::get($this->meta, 'exceptions', []);
        $validationErrors = Arr::get($this->meta, 'validationErrors', []);

        return count($exceptions) || count($validationErrors);
    }

    public function toMailAttachment(): Attachment
    {
        $fileName = $this->getKey().'-'.'import-stats.json';

        return Attachment::fromData(function () {
            return json_encode($this->meta, JSON_PRETTY_PRINT);
        }, $fileName)->withMime('application/json');
    }
}
