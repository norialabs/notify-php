<?php

namespace NoriaLabs\Notify\Notifications;

use Illuminate\Notifications\Notification;
use NoriaLabs\Notify\Exceptions\NotifyException;
use NoriaLabs\Notify\Notify;

class NotifySmsChannel
{
    public function __construct(
        protected readonly Notify $notify,
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
        $message = $notification->toNotifySms($notifiable); // @phpstan-ignore-line
        $message = $message instanceof SmsMessage ? $message : SmsMessage::make((string) $message);

        try {
            return $this->notify->sms()->send($message->payload($to), $message->key());
        } catch (NotifyException $exception) {
            if ($exception->isSuppressed() && ! $this->failOnSuppressed) {
                return null;
            }

            throw $exception;
        }
    }

    protected function routeFor(mixed $notifiable, Notification $notification): ?string
    {
        if (is_object($notifiable) && method_exists($notifiable, 'routeNotificationFor')) {
            $route = $notifiable->routeNotificationFor('notifySms', $notification);

            if (is_string($route) || is_int($route)) {
                return (string) $route;
            }
        }

        return null;
    }
}
