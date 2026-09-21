<?php

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use NoriaLabs\Notify\Exceptions\NotifyException;
use NoriaLabs\Notify\Notifications\SmsMessage;
use NoriaLabs\Notify\Notify;
use NoriaLabs\Notify\NotifyTransport;
use NoriaLabs\Notify\WebhookVerifier;

function queued(array $overrides = []): array
{
    return array_merge(['id' => 'msg_01test', 'object' => 'email', 'status' => 'queued'], $overrides);
}

function sentPayload(): array
{
    $request = Http::recorded()[0][0] ?? null;

    expect($request)->toBeInstanceOf(Request::class);

    return $request->data();
}

it('registers the transport, client and verifier', function () {
    expect(app(Notify::class))->toBeInstanceOf(Notify::class)
        ->and(app(WebhookVerifier::class))->toBeInstanceOf(WebhookVerifier::class)
        ->and((string) Mail::mailer('notify')->getSymfonyTransport())->toBe('noria');
});

it('sends a Laravel mailable through the API with html and text parts', function () {
    Http::fake(['*' => Http::response(queued(), 202)]);

    Mail::html('<p>Hello there</p>', function ($message) {
        $message->to('reader@example.test', 'Reader')
            ->cc('boss@example.test')
            ->bcc('archive@example.test')
            ->replyTo('support@example.test')
            ->subject('Welcome aboard');
    });

    $payload = sentPayload();

    expect($payload['from'])->toBe('"Noria" <hello@example.test>')
        ->and($payload['to'])->toBe(['"Reader" <reader@example.test>'])
        ->and($payload['cc'])->toBe(['boss@example.test'])
        ->and($payload['bcc'])->toBe(['archive@example.test'])
        ->and($payload['reply_to'])->toBe(['support@example.test'])
        ->and($payload['subject'])->toBe('Welcome aboard')
        ->and($payload['html'])->toBe('<p>Hello there</p>');
});

it('authenticates with the configured key and base url', function () {
    Http::fake(['*' => Http::response(queued(), 202)]);

    Mail::raw('body', fn ($message) => $message->to('a@example.test')->subject('s'));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://notify.noria.test/v1/emails'
        && $request->hasHeader('Authorization', 'Bearer nm_test_abcdefghijklmnopqrstuvwx'));
});

it('base64 encodes attachments and preserves inline content ids', function () {
    Http::fake(['*' => Http::response(queued(), 202)]);

    Mail::raw('see attached', function ($message) {
        $message->to('a@example.test')->subject('s');
        $message->attachData('invoice-bytes', 'invoice.pdf', ['mime' => 'application/pdf']);
        $message->embedData('logo-bytes', 'logo.png', 'image/png');
    });

    $attachments = sentPayload()['attachments'];

    expect($attachments)->toHaveCount(2);

    $invoice = collect($attachments)->firstWhere('filename', 'invoice.pdf');
    expect(base64_decode($invoice['content']))->toBe('invoice-bytes')
        ->and($invoice['content_type'])->toBe('application/pdf')
        ->and($invoice['disposition'])->toBe('attachment');

    $inline = collect($attachments)->firstWhere('disposition', 'inline');
    expect($inline)->not->toBeNull()
        ->and(base64_decode($inline['content']))->toBe('logo-bytes');
});

it('maps X-Noria-Tag headers to tags and keeps other custom headers', function () {
    Http::fake(['*' => Http::response(queued(), 202)]);

    Mail::raw('body', function ($message) {
        $message->to('a@example.test')->subject('s');
        $message->getHeaders()->addTextHeader('X-Noria-Tag-campaign', 'onboarding');
        $message->getHeaders()->addTextHeader('X-Noria-Tag-tenant', 'zana');
        $message->getHeaders()->addTextHeader('X-Entity-Ref-Id', 'order-42');
    });

    $payload = sentPayload();

    expect($payload['tags'])->toBe(['campaign' => 'onboarding', 'tenant' => 'zana'])
        ->and($payload['headers'])->toHaveKey('X-Entity-Ref-Id', 'order-42')
        ->and($payload['headers'])->not->toHaveKey('X-Noria-Tag-campaign')
        ->and($payload['headers'])->not->toHaveKey('Subject');
});

it('sends through a stored template when the template header is present', function () {
    Http::fake(['*' => Http::response(queued(), 202)]);

    Mail::raw('ignored', function ($message) {
        $message->to('a@example.test')->subject('s');
        $message->getHeaders()->addTextHeader(NotifyTransport::TEMPLATE_HEADER, 'welcome');
        $message->getHeaders()->addTextHeader(NotifyTransport::VARIABLES_HEADER, json_encode(['name' => 'Gitonga']));
    });

    $payload = sentPayload();

    expect($payload['template'])->toBe('welcome')
        ->and($payload['variables'])->toBe(['name' => 'Gitonga']);
});

it('passes an idempotency key as a header rather than in the body', function () {
    Http::fake(['*' => Http::response(queued(), 202)]);

    Mail::raw('body', function ($message) {
        $message->to('a@example.test')->subject('s');
        $message->getHeaders()->addTextHeader(NotifyTransport::IDEMPOTENCY_HEADER, 'signin-42');
    });

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Idempotency-Key', 'signin-42'));
    expect(sentPayload())->not->toHaveKey('idempotency_key');
});

it('swallows a suppressed recipient so one dead address cannot break a queued job', function () {
    Http::fake(['*' => Http::response([
        'error' => ['code' => 'suppressed_recipient', 'message' => 'all recipients suppressed'],
    ], 422)]);

    Mail::raw('body', fn ($message) => $message->to('blocked@example.test')->subject('s'));
})->throwsNoExceptions();

it('still raises other API errors', function () {
    Http::fake(['*' => Http::response([
        'error' => ['code' => 'domain_not_verified', 'message' => 'not verified', 'request_id' => 'req_1'],
    ], 403)]);

    Mail::raw('body', fn ($message) => $message->to('a@example.test')->subject('s'));
})->throws(NotifyException::class, 'not verified');

it('retries a 503 and then succeeds', function () {
    Http::fakeSequence()
        ->push(['error' => ['code' => 'internal_error', 'message' => 'down']], 503)
        ->push(queued(), 202);

    $result = app(Notify::class)->emails()->send(['from' => 'a@b.test', 'to' => 'c@d.test', 'subject' => 's', 'text' => 't']);

    expect($result['id'])->toBe('msg_01test');
    Http::assertSentCount(2);
});

it('does not retry a quota rejection', function () {
    Http::fake(['*' => Http::response(['error' => ['code' => 'quota_exceeded', 'message' => 'out']], 429)]);

    expect(fn () => app(Notify::class)->emails()->send(['from' => 'a@b.test', 'to' => 'c@d.test', 'subject' => 's', 'text' => 't']))
        ->toThrow(NotifyException::class);

    Http::assertSentCount(1);
});

it('exposes the rest of the API surface', function () {
    Http::fake(['*' => Http::response(['object' => 'list', 'data' => []], 200)]);
    $notify = app(Notify::class);

    $notify->sms()->send(['from' => 'NORIA', 'to' => '0712000001', 'text' => 'hi']);
    $notify->sms()->sendBatch([['to' => '0712000002', 'text' => 'hi']]);
    $notify->domains()->create('example.test');
    $notify->senders()->create('NORIA');
    $notify->templates()->upsert(['slug' => 'welcome', 'subject' => 's', 'text' => 't']);
    $notify->suppressions()->add('0712000003', 'sms', 'unsubscribe');
    $notify->webhooks()->create('https://hook.test', ['delivered']);
    $notify->messages()->requeue('msg_1');
    $notify->messages()->events('msg_1');

    $paths = collect(Http::recorded())->map(fn (array $pair): string => $pair[0]->method().' '.parse_url($pair[0]->url(), PHP_URL_PATH));

    expect($paths->all())->toBe([
        'POST /v1/sms',
        'POST /v1/sms/batch',
        'POST /v1/domains',
        'POST /v1/senders',
        'POST /v1/templates',
        'POST /v1/suppressions',
        'POST /v1/webhook-endpoints',
        'POST /v1/messages/msg_1/requeue',
        'GET /v1/messages/msg_1/events',
    ]);
});

it('reports whether a destination is suppressed', function () {
    Http::fake(['*' => Http::response(['object' => 'list', 'data' => [['destination' => 'a@b.test']]], 200)]);

    expect(app(Notify::class)->suppressions()->has('a@b.test'))->toBeTrue();
});

it('verifies a webhook signature and returns the event', function () {
    $payload = json_encode(['id' => 'evt_1', 'type' => 'delivered']);
    $timestamp = time();
    $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_testsecret');

    expect(app(WebhookVerifier::class)->verify($payload, $signature))
        ->toBe(['id' => 'evt_1', 'type' => 'delivered']);
});

it('rejects a tampered webhook body, a stale timestamp and a malformed header', function () {
    $verifier = app(WebhookVerifier::class);
    $payload = json_encode(['id' => 'evt_1', 'type' => 'delivered']);
    $timestamp = time();
    $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_testsecret');

    expect(fn () => $verifier->verify(str_replace('delivered', 'bounced', $payload), $signature))
        ->toThrow(NotifyException::class, 'Invalid webhook signature');

    $stale = time() - 3600;
    $staleSignature = 't='.$stale.',v1='.hash_hmac('sha256', "{$stale}.{$payload}", 'whsec_testsecret');
    expect(fn () => $verifier->verify($payload, $staleSignature))
        ->toThrow(NotifyException::class, 'outside the tolerance window');

    expect(fn () => $verifier->verify($payload, 'nonsense'))
        ->toThrow(NotifyException::class, 'Malformed Noria-Signature header');
});

it('talks to production unless told otherwise', function () {
    Http::fake(['*' => Http::response(['object' => 'list', 'data' => []], 200)]);

    (new Notify(app(Factory::class), 'nm_live_x'))->messages()->list();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://send.noria.co.ke/v1/messages');
});

it('requires an api key', function () {
    new Notify(app(Factory::class), '');
})->throws(NotifyException::class, 'API key is required');

class OtpIssued extends Notification
{
    public function via(mixed $notifiable): array
    {
        return ['notify-sms'];
    }

    public function toNotifySms(mixed $notifiable): SmsMessage
    {
        return SmsMessage::make('Your code is 482913')->sender('NORIA')->tags(['kind' => 'otp']);
    }
}

class PlainOtp extends Notification
{
    public function via(mixed $notifiable): array
    {
        return ['notify-sms'];
    }

    public function toNotifySms(mixed $notifiable): string
    {
        return 'Your code is 000111';
    }
}

class Subscriber
{
    use Notifiable;

    public function routeNotificationForNotifySms(): string
    {
        return '0712000099';
    }
}

it('sends an sms notification through the notify-sms channel', function () {
    Http::fake(['*' => Http::response(['id' => 'msg_01sms', 'object' => 'sms', 'status' => 'queued'], 202)]);

    NotificationFacade::send([new Subscriber], new OtpIssued);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://notify.noria.test/v1/sms');

    expect(sentPayload())->toBe([
        'to' => '0712000099',
        'from' => 'NORIA',
        'text' => 'Your code is 482913',
        'tags' => ['kind' => 'otp'],
    ]);
});

it('accepts a plain string from a notification', function () {
    Http::fake(['*' => Http::response(['id' => 'msg_01sms', 'object' => 'sms', 'status' => 'queued'], 202)]);

    NotificationFacade::send([new Subscriber], new PlainOtp);

    expect(sentPayload())->toBe(['to' => '0712000099', 'text' => 'Your code is 000111']);
});

it('swallows a suppressed number so one opt-out cannot break a queued notification', function () {
    Http::fake(['*' => Http::response([
        'error' => ['code' => 'suppressed_recipient', 'message' => 'opted out'],
    ], 422)]);

    NotificationFacade::send([new Subscriber], new OtpIssued);
})->throwsNoExceptions();

it('builds an sms template payload without a body', function () {
    expect(SmsMessage::make()->template('otp', ['code' => '482913'])->payload('254712000001'))->toBe([
        'to' => '254712000001',
        'template' => 'otp',
        'variables' => ['code' => '482913'],
    ]);
});
