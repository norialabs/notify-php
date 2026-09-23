<?php

namespace NoriaLabs\Send\Resources;

class Suppressions extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function add(
        string $destination,
        string $channel = 'email',
        string $reason = 'manual',
        ?string $detail = null,
        ?string $expiresAt = null,
    ): array {
        return $this->send->request('POST', '/v1/suppressions', array_filter([
            'destination' => $destination,
            'channel' => $channel,
            'reason' => $reason,
            'detail' => $detail,
            'expires_at' => $expiresAt,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * @return array<string, mixed>
     */
    public function list(?string $destination = null, ?string $channel = null, ?int $limit = null, ?string $cursor = null): array
    {
        return $this->send->request(
            'GET',
            '/v1/suppressions'.$this->send->query([
                'destination' => $destination,
                'channel' => $channel,
                'limit' => $limit,
                'cursor' => $cursor,
            ]),
        );
    }

    public function remove(string $destination, string $channel = 'email'): void
    {
        $this->send->request(
            'DELETE',
            '/v1/suppressions/'.rawurlencode($destination).$this->send->query(['channel' => $channel]),
        );
    }

    public function has(string $destination, string $channel = 'email'): bool
    {
        $result = $this->list($destination, $channel);

        return is_array($result['data'] ?? null) && $result['data'] !== [];
    }
}
