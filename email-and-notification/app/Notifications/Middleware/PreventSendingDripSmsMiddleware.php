<?php

declare(strict_types=1);

namespace App\Notifications\Middleware;

use App\Notifications\Subscriber\DripCampaignSmsNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Log;

class PreventSendingDripSmsMiddleware
{
    public function handle(SendQueuedNotifications $job, $next)
    {
        /**
         * @var DripCampaignSmsNotification $notification
         */
        $notification = $job->notification;

        if (! config('features.sms_drips.enabled')) {
            Log::info('Skip sending SMS Drip since this feature is not enabled.', [
                'drip_id' => $notification->drip->getKey(),
            ]);

            return false;
        }

        if ($notification->drip->sent_at) {
            return false;
        }

        if (! $notification->subscriber->canReceiveDripSMSNotification()) {
            return false;
        }

        return $next($job);
    }
}
