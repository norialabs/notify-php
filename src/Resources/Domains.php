<?php

namespace NoriaLabs\Notify\Resources;

class Domains extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function create(string $name, bool $customReturnPath = true): array
    {
        return $this->notify->request('POST', '/v1/domains', [
            'name' => $name,
            'custom_return_path' => $customReturnPath,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function list(): array
    {
        return $this->notify->request('GET', '/v1/domains');
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->notify->request('GET', '/v1/domains/'.rawurlencode($id));
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $id): array
    {
        return $this->notify->request('POST', '/v1/domains/'.rawurlencode($id).'/verify');
    }

    public function remove(string $id): void
    {
        $this->notify->request('DELETE', '/v1/domains/'.rawurlencode($id));
    }
}
