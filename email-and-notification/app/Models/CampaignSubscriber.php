<?php

declare(strict_types=1);

namespace App\Mail\Models;

use App\Casts\LowercaseCast;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\SubscriberDrip;
use App\Models\Traits\CanBeDisabled;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class CampaignSubscriber extends Model
{
    use CanBeDisabled,
        Notifiable;

    public const MAX_PHONE_NUMBER_SUBMISSIONS = 50;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'suppressed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'email' => LowercaseCast::class,
    ];

    public function routeNotificationForVonage($notification)
    {
        return $this->phone_number;
    }

    public function markAsUnsubscribed(): self
    {
        return $this->fill(['unsubscribed_at' => $this->freshTimestamp()]);
    }

    public function scopeIsValidReceiver(Builder $query)
    {
        $query->whereNull('customer_id')
            ->whereNull('unsubscribed_at')
            ->whereNull('suppressed_at')
            ->isNotDisabled();
    }

    public function canReceiveDripEmail(): bool
    {
        return is_null($this->suppressed_at) &&
            is_null($this->unsubscribed_at) &&
            is_null($this->customer_id) &&
            ! $this->isDisabled();
    }

    public function canReceiveDripSMSNotification(): bool
    {
        return $this->canReceiveDripEmail();
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function drips(): HasMany
    {
        return $this->hasMany(SubscriberDrip::class, 'subscriber_id');
    }
}
