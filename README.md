# Noria Notify for Laravel

Send transactional email and SMS through [Noria Notify](https://github.com/norialabs/notify)
instead of wiring SES and OnFon into every product. Registers a Laravel mail transport and an
SMS notification channel, so `Mail::send()` and `$user->notify()` keep working exactly as they
do today.

```bash
composer require norialabs/notify
```

This repository is a read-only split of `sdks/php` in
[`norialabs/notify`](https://github.com/norialabs/notify). Open pull requests there; the mirror
is force-pushed on every release and anything committed here is lost.

```env
MAIL_MAILER=notify
NORIA_NOTIFY_URL=https://send.noria.internal
NORIA_NOTIFY_KEY=nm_live_…
```

```php
// config/mail.php
'mailers' => [
    'notify' => ['transport' => 'notify'],
],
```

That is the whole email integration. Every `Mail::send()`, `Mail::to()->queue()`, notification
and mailable in the application now goes through the service and gets queueing, retries,
suppression, delivery events and per-project isolation without touching a call site.

## SMS

SMS arrives as a notification channel, which is how Laravel expects to send it.

```php
use Illuminate\Notifications\Notification;
use NoriaLabs\Notify\Notifications\SmsMessage;

class OtpIssued extends Notification
{
    public function __construct(private string $code) {}

    public function via($notifiable): array
    {
        return ['notify-sms'];
    }

    public function toNotifySms($notifiable): SmsMessage
    {
        return SmsMessage::make("Your code is {$this->code}")
            ->sender('NORIA')
            ->tags(['kind' => 'otp'])
            ->idempotencyKey("otp-{$this->code}");
    }
}
```

The notifiable says where it goes:

```php
public function routeNotificationForNotifySms(): string
{
    return $this->phone;
}
```

`toNotifySms()` may also return a plain string. To send a stored template instead of a body,
use `SmsMessage::make()->template('otp', ['code' => $code])`.

## Beyond the transport

The client is bound in the container and available as a facade, grouped by resource.

```php
use NoriaLabs\Notify\Facades\Notify;

Notify::sms()->send(['from' => 'NORIA', 'to' => '0712345678', 'text' => 'Your code is 482913']);
Notify::domains()->create('norialabs.com');       // returns the DNS records to publish
Notify::senders()->create('NORIA');               // registered pending approval
Notify::templates()->upsert(['slug' => 'otp', 'channel' => 'sms', 'text' => 'Code {{code}}']);
Notify::suppressions()->add('0712345678', 'sms', 'unsubscribe');
Notify::suppressions()->has('0712345678', 'sms');
Notify::messages()->list(['channel' => 'sms', 'status' => 'failed']);
Notify::messages()->events($messageId);
Notify::messages()->requeue($messageId);
```

Each accessor returns a typed resource object, so PHPStan resolves the methods on it without a
hand-maintained `@method` list.

## Per-message options

Set headers on a mailable; the transport strips them and maps them onto the API.

```php
use NoriaLabs\Notify\NotifyTransport;

Mail::html($body, function ($message) {
    $message->to($user->email)->subject('Your sign-in link');

    $headers = $message->getHeaders();
    $headers->addTextHeader(NotifyTransport::TAG_HEADER.'-product', 'zana');
    $headers->addTextHeader(NotifyTransport::IDEMPOTENCY_HEADER, "signin-{$token->id}");
    $headers->addTextHeader(NotifyTransport::SCHEDULE_HEADER, now()->addHour()->toIso8601String());
});
```

| Header | Effect |
| --- | --- |
| `X-Noria-Tag-<name>` | Becomes a tag on the message, queryable and forwarded to SES |
| `X-Noria-Idempotency-Key` | Re-sending with the same key returns the original message |
| `X-Noria-Template` | Render a stored template instead of the body |
| `X-Noria-Variables` | JSON variables for that template |
| `X-Noria-Scheduled-At` | ISO 8601 time to send at |

Any other custom header is passed through to the message itself.

## Suppressed recipients

By default a send to a suppressed address or number is a no-op rather than an exception, so one
opt-out cannot fail a queued job or a batch notification. Set `NORIA_NOTIFY_FAIL_ON_SUPPRESSED`
to raise `NotifyException` instead.

## Webhooks

```php
use NoriaLabs\Notify\WebhookVerifier;

Route::post('/webhooks/notify', function (Request $request, WebhookVerifier $verifier) {
    $event = $verifier->verify($request->getContent(), $request->header('Noria-Signature', ''));

    // $event['data']['channel'] is email or sms
    // $event['type'] is delivered, bounced, complained, opened, clicked, failed, …
});
```

Set `NORIA_NOTIFY_WEBHOOK_SECRET` to the secret shown once when the endpoint was created.

## Requirements

PHP 8.3 or newer, and **Laravel 13**. Laravel 11 and 12 were dropped: every Noria product is
moving to 13, and supporting three majors meant the test suite ran against 12 and never against
the version we actually ship on — which is how a Laravel 13 container-binding change sat
unnoticed. `^8.3` is the framework's own floor and admits 8.6 when it arrives.

## Tests

```bash
composer quality      # pint, phpstan level max, pest
```

`tests/LiveTest.php` runs against a real instance and is skipped unless you point it at one:

```bash
NORIA_NOTIFY_LIVE_KEY=nm_live_… vendor/bin/pest tests/LiveTest.php
```

The SMS case additionally needs `NORIA_NOTIFY_LIVE_MSISDN` set to a number you control.
