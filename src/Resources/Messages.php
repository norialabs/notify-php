<?php

namespace NoriaLabs\Send\Resources;

class Messages extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->send->request('GET', '/v1/messages/'.rawurlencode($id));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(array $filters = []): array
    {
        return $this->send->request('GET', '/v1/messages'.$this->send->query($filters));
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(?int $days = null, ?string $timezone = null): array
    {
        return $this->send->request('GET', '/v1/messages/stats'.$this->send->query(['days' => $days, 'timezone' => $timezone]));
    }

    /**
     * @return array<string, mixed>
     */
    public function events(string $id, ?int $limit = null, ?string $cursor = null): array
    {
        return $this->send->request('GET', '/v1/messages/'.rawurlencode($id).'/events'.$this->page($limit, $cursor));
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(string $id): array
    {
        return $this->send->request('POST', '/v1/messages/'.rawurlencode($id).'/cancel');
    }

    /**
     * @return array<string, mixed>
     */
    public function requeue(string $id): array
    {
        return $this->send->request('POST', '/v1/messages/'.rawurlencode($id).'/requeue');
    }
}
