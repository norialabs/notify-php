<?php

namespace NoriaLabs\Send\Resources;

class Domains extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function create(string $name, bool $customReturnPath = true): array
    {
        return $this->send->request('POST', '/v1/domains', [
            'name' => $name,
            'custom_return_path' => $customReturnPath,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function list(?int $limit = null, ?string $cursor = null): array
    {
        return $this->send->request('GET', '/v1/domains'.$this->page($limit, $cursor));
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->send->request('GET', '/v1/domains/'.rawurlencode($id));
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $id): array
    {
        return $this->send->request('POST', '/v1/domains/'.rawurlencode($id).'/verify');
    }

    public function remove(string $id): void
    {
        $this->send->request('DELETE', '/v1/domains/'.rawurlencode($id));
    }
}
