<?php

namespace App\Notifications\Channels;

use App\Services\GreenApiService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel
{
    protected GreenApiService $whatsapp;

    public function __construct(GreenApiService $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        if (!$this->whatsapp->isEnabled()) {
            return;
        }

        if (!method_exists($notification, 'toWhatsApp')) {
            Log::warning('WhatsAppChannel: Notification is missing toWhatsApp method.', ['notification' => get_class($notification)]);
            return;
        }

        $messageData = $notification->toWhatsApp($notifiable);

        if (empty($messageData['to']) || empty($messageData['content'])) {
            Log::warning('WhatsAppChannel: Missing recipient number or content.', ['notification' => get_class($notification)]);
            return;
        }

        $this->whatsapp->sendMessage($messageData['to'], $messageData['content']);
    }
}
