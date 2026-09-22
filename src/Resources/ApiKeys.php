<?php

namespace NoriaLabs\Send\Resources;

class ApiKeys extends Resource
{
    /**
     * The token comes back on this call and on no other.
     *
     * @param  array<int, string>  $scopes
     * @return array<string, mixed>
     */
    public function issue(
        string $name,
        string $environment = 'live',
        array $scopes = ['send', 'read'],
        ?int $rateLimitPerSecond = null,
    ): array {
        return $this->send->request('POST', '/v1/api-keys', array_filter([
            'name' => $name,
            'environment' => $environment,
            'scopes' => $scopes,
            'rate_limit_per_second' => $rateLimitPerSecond,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * @return array<string, mixed>
     */
    public function list(): array
    {
        return $this->send->request('GET', '/v1/api-keys');
    }

    public function revoke(string $id): void
    {
        $this->send->request('DELETE', '/v1/api-keys/'.rawurlencode($id));
    }
}
