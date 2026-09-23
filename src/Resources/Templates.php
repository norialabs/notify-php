<?php

namespace NoriaLabs\Send\Resources;

class Templates extends Resource
{
    /**
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    public function upsert(array $template): array
    {
        return $this->send->request('POST', '/v1/templates', $template);
    }

    /**
     * @return array<string, mixed>
     */
    public function list(?int $limit = null, ?string $cursor = null, ?string $channel = null): array
    {
        return $this->send->request(
            'GET',
            '/v1/templates'.$this->send->query(['limit' => $limit, 'cursor' => $cursor, 'channel' => $channel]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $slug, string $channel = 'email'): array
    {
        return $this->send->request(
            'GET',
            '/v1/templates/'.rawurlencode($slug).$this->send->query(['channel' => $channel]),
        );
    }

    public function remove(string $slug, string $channel = 'email'): void
    {
        $this->send->request(
            'DELETE',
            '/v1/templates/'.rawurlencode($slug).$this->send->query(['channel' => $channel]),
        );
    }
}
