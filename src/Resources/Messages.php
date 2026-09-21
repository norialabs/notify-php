<?php

namespace NoriaLabs\Notify\Resources;

class Messages extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->notify->request('GET', '/v1/messages/'.rawurlencode($id));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(array $filters = []): array
    {
        return $this->notify->request('GET', '/v1/messages'.$this->notify->query($filters));
    }

    /**
     * @return array<string, mixed>
     */
    public function events(string $id): array
    {
        return $this->notify->request('GET', '/v1/messages/'.rawurlencode($id).'/events');
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(string $id): array
    {
        return $this->notify->request('POST', '/v1/messages/'.rawurlencode($id).'/cancel');
    }

    /**
     * @return array<string, mixed>
     */
    public function requeue(string $id): array
    {
        return $this->notify->request('POST', '/v1/messages/'.rawurlencode($id).'/requeue');
    }
}
