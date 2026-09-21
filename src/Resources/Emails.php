<?php

namespace NoriaLabs\Notify\Resources;

class Emails extends Resource
{
    /**
     * @param  array<string, mixed>  $email
     * @return array<string, mixed>
     */
    public function send(array $email, ?string $idempotencyKey = null): array
    {
        return $this->notify->request(
            'POST',
            '/v1/emails',
            $email,
            $idempotencyKey === null ? [] : ['Idempotency-Key' => $idempotencyKey],
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $emails
     * @return array<string, mixed>
     */
    public function sendBatch(array $emails): array
    {
        return $this->notify->request('POST', '/v1/emails/batch', ['emails' => $emails]);
    }
}
