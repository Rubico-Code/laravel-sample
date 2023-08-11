<?php

declare(strict_types=1);

namespace App\Mail\Models;

use App\Models\CampaignSubscriber;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Date;

class SubscriberDrip extends Model
{
    use MassPrunable;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'opened_at' => 'datetime',
    ];

    public function prunable(): Builder
    {
        return static::query()
            ->whereNull('sent_at')
            ->whereHas('subscriber.campaign', function (BuilderContract $query) {
                $query->where('end_datetime', '<', Date::now()->subDays(30));
            });
    }

    public function markAsSent(): self
    {
        return $this->fill([
            'sent_at' => $this->freshTimestamp(),
            'failed_at' => null,
        ]);
    }

    public function markAsFailed(): self
    {
        return $this->fill([
            'failed_at' => $this->freshTimestamp(),
        ]);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(CampaignSubscriber::class, 'subscriber_id');
    }
}
