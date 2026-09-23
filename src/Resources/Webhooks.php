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
    public function list(?int $limit = null, ?string $cursor = null): array
    {
        return $this->send->request('GET', '/v1/webhook-endpoints'.$this->page($limit, $cursor));
    }

    /**
     * @param  array{url?: string, description?: string, event_types?: array<int, string>, enabled?: bool}  $changes
     * @return array<string, mixed>
     */
    public function update(string $id, array $changes): array
    {
        return $this->send->request('PATCH', '/v1/webhook-endpoints/'.rawurlencode($id), $changes);
    }

    public function remove(string $id): void
    {
        $this->send->request('DELETE', '/v1/webhook-endpoints/'.rawurlencode($id));
    }
}
