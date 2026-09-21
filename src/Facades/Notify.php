<?php

namespace NoriaLabs\Notify\Facades;

use Illuminate\Support\Facades\Facade;
use NoriaLabs\Notify\Notify as Client;
use NoriaLabs\Notify\Resources\Domains;
use NoriaLabs\Notify\Resources\Emails;
use NoriaLabs\Notify\Resources\Messages;
use NoriaLabs\Notify\Resources\Senders;
use NoriaLabs\Notify\Resources\Sms;
use NoriaLabs\Notify\Resources\Suppressions;
use NoriaLabs\Notify\Resources\Templates;
use NoriaLabs\Notify\Resources\Webhooks;

/**
 * @method static Emails emails()
 * @method static Sms sms()
 * @method static Messages messages()
 * @method static Domains domains()
 * @method static Senders senders()
 * @method static Templates templates()
 * @method static Suppressions suppressions()
 * @method static Webhooks webhooks()
 *
 * @see Client
 */
class Notify extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }
}
