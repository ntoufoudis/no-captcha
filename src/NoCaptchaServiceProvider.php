<?php

declare(strict_types=1);

namespace Ntoufoudis\NoCaptcha;

use Illuminate\Support\ServiceProvider;

class NoCaptchaServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     */
    protected bool $defer = false;

    /**
     * Bootstrap the application events.
     */
    public function boot(): void
    {
        $app = $this->app;

        $this->publishes([
            __DIR__.'/config/captcha.php' => config_path('captcha.php'),
        ], 'config');

        $app['validator']->extend('captcha', function ($attribute, $value) use ($app) {
            return $app['captcha']->verifyResponse($value, $app['request']->getClientIp());
        });

        if ($app->bound('form')) {
            $app['form']->macro('captcha', function ($attributes = []) use ($app) {
                return $app['captcha']->display($attributes, $app->getLocale());
            });
        }
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('captcha', function ($app) {
            return new NoCaptcha(
                $app['config']['captcha.secret'],
                $app['config']['captcha.sitekey'],
                $app['config']['captcha.options'],
            );
        });
    }

    /**
     * @return string[]
     */
    public function provides(): array
    {
        return [
            'captcha',
        ];
    }
}
