<?php

namespace NoriaLabs\Notify\Resources;

use NoriaLabs\Notify\Notify;

abstract class Resource
{
    public function __construct(protected readonly Notify $notify) {}
}
