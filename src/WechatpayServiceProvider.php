<?php

namespace KagaDorapeko\Laravel\Wechatpay;

use Illuminate\Support\ServiceProvider;

class WechatpayServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/wechatpay.php', 'wechatpay');

        $this->app->singleton(WechatpayService::class, function () {
            return new WechatpayService;
        });
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/wechatpay.php' => config_path('wechatpay.php'),
            ], 'laravel-wechatpay-config');
        }
    }
}