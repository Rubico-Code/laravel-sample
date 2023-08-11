<?php

declare(strict_types=1);

namespace App\Jobs\Models\Scopes;

use App\Support\SearchQuery;
use Illuminate\Contracts\Database\Eloquent\Builder;

trait FileImportScope
{
    public function scopeSearchKeyword(Builder $query, $keyword): void
    {
        $searchQuery = SearchQuery::from($keyword);

        if ($searchQuery->isEmpty()) {
            return;
        }

        $query->whereKeyword('name', $searchQuery);
    }
}
