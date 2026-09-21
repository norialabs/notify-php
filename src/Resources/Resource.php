<?php

namespace NoriaLabs\Send\Resources;

use NoriaLabs\Send\Send;

abstract class Resource
{
    public function __construct(protected readonly Send $send) {}
}
