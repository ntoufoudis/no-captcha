<?php

declare(strict_types=1);

namespace Ntoufoudis\NoCaptcha\Facades;

use Illuminate\Support\Facades\Facade;

class NoCaptcha extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'captcha';
    }
}
