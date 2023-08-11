<?php

declare(strict_types=1);

namespace App\Mail\Middleware;

use App\Mail\Subscriber\DripCampaignEmail;
use Illuminate\Mail\SendQueuedMailable;

class PreventSendingDripMailMiddleware
{
    public function handle(SendQueuedMailable $job, $next)
    {
        /**
         * @var DripCampaignEmail $mailable
         */
        $mailable = $job->mailable;

        if ($mailable->drip->sent_at) {
            return false;
        }

        if (! $mailable->subscriber->canReceiveDripEmail()) {
            return false;
        }

        return $next($job);
    }
}
