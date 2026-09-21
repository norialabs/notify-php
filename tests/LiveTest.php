<?php

use Illuminate\Support\Facades\Mail;
use NoriaLabs\Send\Notifications\SmsMessage;
use NoriaLabs\Send\Send;
use NoriaLabs\Send\SendTransport;

beforeEach(function () {
    $key = getenv('NORIA_SEND_LIVE_KEY');
    $url = getenv('NORIA_SEND_LIVE_URL') ?: 'http://localhost:4800';

    if ($key === false || $key === '') {
        test()->markTestSkipped('Set NORIA_SEND_LIVE_KEY to run against a running Noria Send instance.');
    }

    config()->set('noria-send.key', $key);
    config()->set('noria-send.url', $url);
    config()->set('mail.from', ['address' => 'platform@norialabs.com', 'name' => 'Noria Platform']);
});

it('delivers a Laravel mailable through the running service', function () {
    $send = app(Send::class);
    $subject = 'Your zana studio sign-in link '.bin2hex(random_bytes(4));

    Mail::html('<p>Your sign-in link: <a href="https://zana.test/x">open</a></p>', function ($message) use ($subject) {
        $message->to('operator@example.com', 'Ops Team')
            ->subject($subject)
            ->replyTo('support@norialabs.com');
        $message->getHeaders()->addTextHeader(SendTransport::TAG_HEADER.'-product', 'zana');
        $message->getHeaders()->addTextHeader(SendTransport::TAG_HEADER.'-kind', 'signin');
    });

    $listed = collect($send->messages()->list(['limit' => 25, 'channel' => 'email'])['data'])->firstWhere('subject', $subject);

    expect($listed)->not->toBeNull()
        ->and($listed['to'])->toBe(['"Ops Team" <operator@example.com>'])
        ->and($listed['reply_to'])->toBe(['support@norialabs.com'])
        ->and($listed['tags'])->toEqualCanonicalizing(['product' => 'zana', 'kind' => 'signin']);

    $id = $listed['id'];

    $status = retry(20, function () use ($send, $id) {
        $message = $send->messages()->get($id);
        if ($message['status'] !== 'sent') {
            throw new RuntimeException('still '.$message['status']);
        }

        return $message['status'];
    }, 250);

    expect($status)->toBe('sent');

    $events = collect($send->messages()->events($id)['data'])->pluck('type')->sort()->values()->all();
    expect($events)->toContain('queued', 'sent', 'delivered');
});

it('replays an idempotent send instead of sending twice', function () {
    $send = app(Send::class);
    $key = 'signin-'.bin2hex(random_bytes(6));
    $subject = 'Idempotent sign-in '.bin2hex(random_bytes(4));

    $send = function () use ($subject, $key) {
        Mail::html('<p>link</p>', function ($message) use ($subject, $key) {
            $message->to('operator@example.com')->subject($subject);
            $message->getHeaders()->addTextHeader(SendTransport::IDEMPOTENCY_HEADER, $key);
        });
    };

    $send();
    $send();

    $matching = collect($send->messages()->list(['limit' => 50, 'channel' => 'email'])['data'])->where('subject', $subject);

    expect($matching)->toHaveCount(1);
});

it('carries attachments through to the service', function () {
    $send = app(Send::class);
    $subject = 'Invoice '.bin2hex(random_bytes(4));

    Mail::html('<p>Invoice attached.</p>', function ($message) use ($subject) {
        $message->to('billing@example.com')->subject($subject);
        $message->attachData('%PDF-1.4 fake invoice bytes', 'invoice-0042.pdf', ['mime' => 'application/pdf']);
    });

    $listed = collect($send->messages()->list(['limit' => 25, 'channel' => 'email'])['data'])->firstWhere('subject', $subject);
    expect($listed)->not->toBeNull();

    $id = $listed['id'];
    retry(20, function () use ($send, $id) {
        if ($send->messages()->get($id)['status'] !== 'sent') {
            throw new RuntimeException('not sent yet');
        }
    }, 250);

    expect($send->messages()->get($id)['status'])->toBe('sent');
});

it('swallows a suppressed recipient rather than failing the job', function () {
    $send = app(Send::class);
    $address = 'bounced-'.bin2hex(random_bytes(4)).'@example.com';
    $send->suppressions()->add($address, 'email', 'bounce', 'hard bounce');

    expect($send->suppressions()->has($address))->toBeTrue();

    Mail::html('<p>Nobody home</p>', function ($message) use ($address) {
        $message->to($address)->subject('Should be swallowed');
    });

    $sent = collect($send->messages()->list(['limit' => 50, 'channel' => 'email'])['data'])->firstWhere('subject', 'Should be swallowed');
    expect($sent)->toBeNull();

    $send->suppressions()->remove($address);
    expect($send->suppressions()->has($address))->toBeFalse();
});

it('delivers an sms through the running service', function () {
    $send = app(Send::class);
    $to = getenv('NORIA_SEND_LIVE_MSISDN');

    if ($to === false || $to === '') {
        test()->markTestSkipped('Set NORIA_SEND_LIVE_MSISDN to a number you control.');
    }

    $text = 'Noria Send live check '.bin2hex(random_bytes(3));
    $queued = $send->sms()->send(SmsMessage::make($text)->payload($to));

    expect($queued['channel'])->toBe('sms')
        ->and($queued['segments'])->toBe(1);

    $status = retry(20, function () use ($send, $queued) {
        $message = $send->messages()->get($queued['id']);
        if ($message['status'] !== 'sent') {
            throw new RuntimeException('still '.$message['status']);
        }

        return $message['status'];
    }, 250);

    expect($status)->toBe('sent');
});
