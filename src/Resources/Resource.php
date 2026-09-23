<?php

namespace NoriaLabs\Send\Resources;

use NoriaLabs\Send\Send;

abstract class Resource
{
    public function __construct(protected readonly Send $send) {}

    protected function page(?int $limit, ?string $cursor): string
    {
        return $this->send->query(['limit' => $limit, 'cursor' => $cursor]);
    }
}
