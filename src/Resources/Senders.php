<?php

namespace NoriaLabs\Notify\Resources;

class Senders extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function create(string $senderId, string $channel = 'sms'): array
    {
        return $this->notify->request('POST', '/v1/senders', ['sender_id' => $senderId, 'channel' => $channel]);
    }

    /**
     * @return array<string, mixed>
     */
    public function list(): array
    {
        return $this->notify->request('GET', '/v1/senders');
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->notify->request('GET', '/v1/senders/'.rawurlencode($id));
    }

    public function remove(string $id): void
    {
        $this->notify->request('DELETE', '/v1/senders/'.rawurlencode($id));
    }
}
