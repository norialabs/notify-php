<?php

namespace NoriaLabs\Send\Resources;

class Senders extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function create(string $senderId, string $channel = 'sms'): array
    {
        return $this->send->request('POST', '/v1/senders', ['sender_id' => $senderId, 'channel' => $channel]);
    }

    /**
     * @return array<string, mixed>
     */
    public function list(?int $limit = null, ?string $cursor = null): array
    {
        return $this->send->request('GET', '/v1/senders'.$this->page($limit, $cursor));
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->send->request('GET', '/v1/senders/'.rawurlencode($id));
    }

    public function remove(string $id): void
    {
        $this->send->request('DELETE', '/v1/senders/'.rawurlencode($id));
    }
}
