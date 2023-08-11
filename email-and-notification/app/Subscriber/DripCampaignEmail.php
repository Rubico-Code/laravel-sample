<?php

declare(strict_types=1);

namespace App\Mail\Subscriber;

use App\Jobs\Middleware\RateLimitJobMiddleware;
use App\Mail\Middleware\PreventSendingDripMailMiddleware;
use App\Models\CampaignSubscriber;
use App\Models\SubscriberDrip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;
use Throwable;

class DripCampaignEmail extends Mailable implements ShouldQueue, ShouldBeUnique
{
    use Queueable, SerializesModels;

    public const DRIP_HEADER_NAME = 'X-Drip-ID';

    public CampaignSubscriber $subscriber;

    public SubscriberDrip $drip;

    public function __construct(SubscriberDrip $drip)
    {
        $this->drip = $drip->loadMissing('subscriber.campaign');
        $this->subscriber = $drip->subscriber;
    }

    public function retryUntil(): Carbon
    {
        // This time must be same or less than the drip cron interval
        return Date::now()->addMinutes(5);
    }

    public function uniqueId()
    {
        return $this->drip->getKey();
    }

    public function middleware(): array
    {
        $maxRate = config('services.ses.sending_rate');
        $quota = (int) ($maxRate * 0.9); // leave some quota for other outgoing emails

        return [
            RateLimitJobMiddleware::make($quota)->forThrottleKey('rate_limit:'.get_class($this)),
            new PreventSendingDripMailMiddleware(),
        ];
    }

    public function build(): self
    {
        $unsubscribeUrl = $this->unsubscribeUrl();

        return $this
            ->subject($this->getSubjectLine())
            ->markdown($this->templateName(), [
                'subscriber' => $this->subscriber,
                'campaign' => $this->subscriber->campaign,
                'campaignEndDateTime' => $this->endDateTime(),
                'unsubscribeUrl' => $unsubscribeUrl,
                'campaignUrl' => $this->campaignUrl(),
            ])
            ->addUnsubscribeHeaders($unsubscribeUrl);
    }

    protected function templateName(): string
    {
        if ($this->isLastEmailInSequence()) {
            return 'emails.subscriber.lastDripMessage';
        }

        return 'emails.subscriber.regularDripMessage';
    }

    protected function isLastEmailInSequence(): bool
    {
        $lastDrip = $this->subscriber->drips()->orderByDesc('scheduled_at')->first();

        return $this->drip->is($lastDrip);
    }

    protected function endDateTime(): string
    {
        return $this->subscriber->campaign->end_datetime
            ->clone()
            ->toClientTimezone()
            ->format('m-j-Y h:i a');
    }

    protected function getSubjectLine(): string
    {
        return sprintf(
            '%s - Can you support us?',
            $this->subscriber->campaign->name
        );
    }

    public function unsubscribeUrl(): string
    {
        return URL::temporarySignedRoute(
            'supporter.campaigns.subscribers.unsubscribe',
            $this->subscriber->campaign->end_datetime,
            [
                'campaign' => $this->subscriber->campaign,
                'subscriber' => $this->subscriber,
            ]
        );
    }

    public function campaignUrl(): string
    {
        $tracking = http_build_query([
            'utm_source' => 'drip',
            'utm_medium' => 'email',
        ]);

        return route('supporter.campaigns.users', [$this->subscriber->campaign, $this->subscriber->participant]).
            '?'.
            $tracking;
    }

    protected function addUnsubscribeHeaders(string $url): self
    {
        return $this->withSymfonyMessage(function (Email $message) use ($url) {
            $message->getHeaders()->addTextHeader(self::DRIP_HEADER_NAME, (string) $this->drip->getKey());
            $message->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$url.'>');
            $message->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        });
    }

    public function failed(?Throwable $exception)
    {
        $this->drip->markAsFailed()->save();
    }
}
