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
    ): array {
        return $this->send->request('POST', '/v1/suppressions', array_filter([
            'destination' => $destination,
            'channel' => $channel,
            'reason' => $reason,
            'detail' => $detail,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * @return array<string, mixed>
     */
    public function list(?string $destination = null, ?string $channel = null): array
    {
        return $this->send->request(
            'GET',
            '/v1/suppressions'.$this->send->query(['destination' => $destination, 'channel' => $channel]),
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
