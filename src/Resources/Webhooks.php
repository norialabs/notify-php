<?php

namespace NoriaLabs\Send\Resources;

class Webhooks extends Resource
{
    /**
     * @param  array<int, string>  $eventTypes
     * @return array<string, mixed>
     */
    public function create(string $url, array $eventTypes = [], ?string $description = null): array
    {
        return $this->send->request('POST', '/v1/webhook-endpoints', array_filter([
            'url' => $url,
            'event_types' => $eventTypes,
            'description' => $description,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * @return array<string, mixed>
     */
    public function list(): array
    {
        return $this->send->request('GET', '/v1/webhook-endpoints');
    }

    public function remove(string $id): void
    {
        $this->send->request('DELETE', '/v1/webhook-endpoints/'.rawurlencode($id));
    }
}
