<?php

declare(strict_types=1);

namespace App\Notifications\Subscriber;

use App\Jobs\Middleware\RateLimitJobMiddleware;
use App\Models\CampaignSubscriber;
use App\Models\SubscriberDrip;
use App\Notifications\Middleware\PreventSendingDripSmsMiddleware;
use App\Support\ShortLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Channels\VonageSmsChannel;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Throwable;

class DripCampaignSmsNotification extends Notification implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public CampaignSubscriber $subscriber;

    public SubscriberDrip $drip;

    public function __construct(SubscriberDrip $drip)
    {
        $this->drip = $drip->loadMissing('subscriber.campaign');
        $this->subscriber = $drip->subscriber;
    }

    public function uniqueId()
    {
        return $this->drip->getKey();
    }

    public function middleware(): array
    {
        $maxRate = config('services.vonage.sms_sending_rate');
        $quota = (int) ($maxRate * 0.9);

        return [
            RateLimitJobMiddleware::make($quota)->forThrottleKey('rate_limit:'.get_class($this)),
            new PreventSendingDripSmsMiddleware(),
        ];
    }

    public function retryUntil(): Carbon
    {
        // This time must be same or less than the drip cron interval
        return Date::now()->addMinutes(5);
    }

    public function via(object $notifiable): array
    {
        return [VonageSmsChannel::class];
    }

    public function toVonage($notifiable): VonageMessage
    {
        return (new VonageMessage())
            ->content($this->getMessage());
    }

    protected function getMessage(): string
    {
        $endLine = sprintf(
            '%s - Thank you! %s',
            $this->getShortLink(),
            $this->subscriber->participant->profile->first_name
        );

       return 'Hi! I am trying to raise money for my team - Can you support me? '.$endLine;
    }

    protected function getShortLink(): string
    {
        return ShortLink::make($this->subscriber->campaign, $this->subscriber->participant);
    }

    public function failed(?Throwable $exception)
    {
        $this->drip->markAsFailed()->save();
    }
}
