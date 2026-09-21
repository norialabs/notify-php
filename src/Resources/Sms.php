<?php

namespace NoriaLabs\Notify\Resources;

class Sms extends Resource
{
    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    public function send(array $message, ?string $idempotencyKey = null): array
    {
        return $this->notify->request(
            'POST',
            '/v1/sms',
            $message,
            $idempotencyKey === null ? [] : ['Idempotency-Key' => $idempotencyKey],
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<string, mixed>
     */
    public function sendBatch(array $messages): array
    {
        return $this->notify->request('POST', '/v1/sms/batch', ['messages' => $messages]);
    }
}
