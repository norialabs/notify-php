<?php

use Illuminate\Support\Facades\Mail;
use NoriaLabs\Notify\Notifications\SmsMessage;
use NoriaLabs\Notify\Notify;
use NoriaLabs\Notify\NotifyTransport;

beforeEach(function () {
    $key = getenv('NORIA_NOTIFY_LIVE_KEY');
    $url = getenv('NORIA_NOTIFY_LIVE_URL') ?: 'http://localhost:4800';

    if ($key === false || $key === '') {
        test()->markTestSkipped('Set NORIA_NOTIFY_LIVE_KEY to run against a running Noria Notify instance.');
    }

    config()->set('noria-notify.key', $key);
    config()->set('noria-notify.url', $url);
    config()->set('mail.from', ['address' => 'platform@norialabs.com', 'name' => 'Noria Platform']);
});

it('delivers a Laravel mailable through the running service', function () {
    $notify = app(Notify::class);
    $subject = 'Your zana studio sign-in link '.bin2hex(random_bytes(4));

    Mail::html('<p>Your sign-in link: <a href="https://zana.test/x">open</a></p>', function ($message) use ($subject) {
        $message->to('operator@example.com', 'Ops Team')
            ->subject($subject)
            ->replyTo('support@norialabs.com');
        $message->getHeaders()->addTextHeader(NotifyTransport::TAG_HEADER.'-product', 'zana');
        $message->getHeaders()->addTextHeader(NotifyTransport::TAG_HEADER.'-kind', 'signin');
    });

    $listed = collect($notify->messages()->list(['limit' => 25, 'channel' => 'email'])['data'])->firstWhere('subject', $subject);

    expect($listed)->not->toBeNull()
        ->and($listed['to'])->toBe(['"Ops Team" <operator@example.com>'])
        ->and($listed['reply_to'])->toBe(['support@norialabs.com'])
        ->and($listed['tags'])->toEqualCanonicalizing(['product' => 'zana', 'kind' => 'signin']);

    $id = $listed['id'];

    $status = retry(20, function () use ($notify, $id) {
        $message = $notify->messages()->get($id);
        if ($message['status'] !== 'sent') {
            throw new RuntimeException('still '.$message['status']);
        }

        return $message['status'];
    }, 250);

    expect($status)->toBe('sent');

    $events = collect($notify->messages()->events($id)['data'])->pluck('type')->sort()->values()->all();
    expect($events)->toContain('queued', 'sent', 'delivered');
});

it('replays an idempotent send instead of sending twice', function () {
    $notify = app(Notify::class);
    $key = 'signin-'.bin2hex(random_bytes(6));
    $subject = 'Idempotent sign-in '.bin2hex(random_bytes(4));

    $send = function () use ($subject, $key) {
        Mail::html('<p>link</p>', function ($message) use ($subject, $key) {
            $message->to('operator@example.com')->subject($subject);
            $message->getHeaders()->addTextHeader(NotifyTransport::IDEMPOTENCY_HEADER, $key);
        });
    };

    $send();
    $send();

    $matching = collect($notify->messages()->list(['limit' => 50, 'channel' => 'email'])['data'])->where('subject', $subject);

    expect($matching)->toHaveCount(1);
});

it('carries attachments through to the service', function () {
    $notify = app(Notify::class);
    $subject = 'Invoice '.bin2hex(random_bytes(4));

    Mail::html('<p>Invoice attached.</p>', function ($message) use ($subject) {
        $message->to('billing@example.com')->subject($subject);
        $message->attachData('%PDF-1.4 fake invoice bytes', 'invoice-0042.pdf', ['mime' => 'application/pdf']);
    });

    $listed = collect($notify->messages()->list(['limit' => 25, 'channel' => 'email'])['data'])->firstWhere('subject', $subject);
    expect($listed)->not->toBeNull();

    $id = $listed['id'];
    retry(20, function () use ($notify, $id) {
        if ($notify->messages()->get($id)['status'] !== 'sent') {
            throw new RuntimeException('not sent yet');
        }
    }, 250);

    expect($notify->messages()->get($id)['status'])->toBe('sent');
});

it('swallows a suppressed recipient rather than failing the job', function () {
    $notify = app(Notify::class);
    $address = 'bounced-'.bin2hex(random_bytes(4)).'@example.com';
    $notify->suppressions()->add($address, 'email', 'bounce', 'hard bounce');

    expect($notify->suppressions()->has($address))->toBeTrue();

    Mail::html('<p>Nobody home</p>', function ($message) use ($address) {
        $message->to($address)->subject('Should be swallowed');
    });

    $sent = collect($notify->messages()->list(['limit' => 50, 'channel' => 'email'])['data'])->firstWhere('subject', 'Should be swallowed');
    expect($sent)->toBeNull();

    $notify->suppressions()->remove($address);
    expect($notify->suppressions()->has($address))->toBeFalse();
});

it('delivers an sms through the running service', function () {
    $notify = app(Notify::class);
    $to = getenv('NORIA_NOTIFY_LIVE_MSISDN');

    if ($to === false || $to === '') {
        test()->markTestSkipped('Set NORIA_NOTIFY_LIVE_MSISDN to a number you control.');
    }

    $text = 'Noria Notify live check '.bin2hex(random_bytes(3));
    $queued = $notify->sms()->send(SmsMessage::make($text)->payload($to));

    expect($queued['channel'])->toBe('sms')
        ->and($queued['segments'])->toBe(1);

    $status = retry(20, function () use ($notify, $queued) {
        $message = $notify->messages()->get($queued['id']);
        if ($message['status'] !== 'sent') {
            throw new RuntimeException('still '.$message['status']);
        }

        return $message['status'];
    }, 250);

    expect($status)->toBe('sent');
});
