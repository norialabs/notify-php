<?php

namespace NoriaLabs\Send\Notifications;

use Illuminate\Notifications\Notification;
use NoriaLabs\Send\Exceptions\SendException;
use NoriaLabs\Send\Send;

class SendSmsChannel
{
    public function __construct(
        protected readonly Send $send,
        protected readonly bool $failOnSuppressed = false,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function send(mixed $notifiable, Notification $notification): ?array
    {
        $to = $this->routeFor($notifiable, $notification);

        if ($to === null || $to === '') {
            return null;
        }

        /** @var SmsMessage|string $message */
        $message = $notification->toSendSms($notifiable); // @phpstan-ignore-line
        $message = $message instanceof SmsMessage ? $message : SmsMessage::make((string) $message);

        try {
            return $this->send->sms()->send($message->payload($to), $message->key());
        } catch (SendException $exception) {
            if ($exception->isSuppressed() && ! $this->failOnSuppressed) {
                return null;
            }

            throw $exception;
        }
    }

    protected function routeFor(mixed $notifiable, Notification $notification): ?string
    {
        if (is_object($notifiable) && method_exists($notifiable, 'routeNotificationFor')) {
            foreach (['send-sms', 'sendSms', self::class] as $name) {
                $route = $notifiable->routeNotificationFor($name, $notification);

                if (is_string($route) || is_int($route)) {
                    return (string) $route;
                }
            }
        }

        return null;
    }
}
