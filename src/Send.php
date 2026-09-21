<?php

namespace NoriaLabs\Send;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use NoriaLabs\Send\Exceptions\SendException;
use NoriaLabs\Send\Resources\Domains;
use NoriaLabs\Send\Resources\Emails;
use NoriaLabs\Send\Resources\Messages;
use NoriaLabs\Send\Resources\Senders;
use NoriaLabs\Send\Resources\Sms;
use NoriaLabs\Send\Resources\Suppressions;
use NoriaLabs\Send\Resources\Templates;
use NoriaLabs\Send\Resources\Webhooks;

class Send
{
    public const DEFAULT_BASE_URL = 'https://send.noria.co.ke';

    public function __construct(
        protected readonly Factory $http,
        protected readonly string $apiKey,
        protected readonly string $baseUrl = self::DEFAULT_BASE_URL,
        protected readonly int $timeout = 15,
        protected readonly int $retries = 2,
    ) {
        if ($apiKey === '') {
            throw new SendException('validation_error', 0, 'A Noria Send API key is required');
        }
    }

    public function emails(): Emails
    {
        return new Emails($this);
    }

    public function sms(): Sms
    {
        return new Sms($this);
    }

    public function messages(): Messages
    {
        return new Messages($this);
    }

    public function domains(): Domains
    {
        return new Domains($this);
    }

    public function senders(): Senders
    {
        return new Senders($this);
    }

    public function templates(): Templates
    {
        return new Templates($this);
    }

    public function suppressions(): Suppressions
    {
        return new Suppressions($this);
    }

    public function webhooks(): Webhooks
    {
        return new Webhooks($this);
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, ?array $body = null, array $headers = []): array
    {
        $attempt = 0;
        $last = null;

        while ($attempt <= $this->retries) {
            if ($attempt > 0) {
                usleep(min(2_000_000, 200_000 * (2 ** ($attempt - 1))));
            }

            $attempt++;

            try {
                $response = $this->pending($headers, $body !== null)
                    ->send($method, $this->url($path), $body === null ? [] : ['json' => $body]);
            } catch (ConnectionException $exception) {
                $last = SendException::network($exception->getMessage(), $exception);

                continue;
            }

            if ($response->status() === 204) {
                return [];
            }

            /** @var array<string, mixed> $decoded */
            $decoded = $response->json() ?? [];

            if ($response->successful()) {
                return $decoded;
            }

            $last = SendException::fromResponse($response->status(), $decoded);

            if (! $last->isRetryable()) {
                throw $last;
            }
        }

        throw $last ?? SendException::network('Request failed');
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function query(array $parameters): string
    {
        $filtered = array_filter($parameters, static fn (mixed $value): bool => $value !== null && $value !== '');

        return $filtered === [] ? '' : '?'.http_build_query($filtered);
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function pending(array $headers, bool $hasBody): PendingRequest
    {
        $request = $this->http
            ->withToken($this->apiKey)
            ->acceptJson()
            ->withHeaders($headers)
            ->timeout($this->timeout);

        return $hasBody ? $request->asJson() : $request;
    }

    protected function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').$path;
    }
}
