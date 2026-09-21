<?php

namespace NoriaLabs\Notify\Resources;

class Templates extends Resource
{
    /**
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    public function upsert(array $template): array
    {
        return $this->notify->request('POST', '/v1/templates', $template);
    }

    /**
     * @return array<string, mixed>
     */
    public function list(): array
    {
        return $this->notify->request('GET', '/v1/templates');
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $slug, string $channel = 'email'): array
    {
        return $this->notify->request(
            'GET',
            '/v1/templates/'.rawurlencode($slug).$this->notify->query(['channel' => $channel]),
        );
    }

    public function remove(string $slug, string $channel = 'email'): void
    {
        $this->notify->request(
            'DELETE',
            '/v1/templates/'.rawurlencode($slug).$this->notify->query(['channel' => $channel]),
        );
    }
}
