<?php
namespace Pay\Orderpay;

use Illuminate\Support\ServiceProvider;

class OrderpayProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // 发布配置文件
        $this->publishes([
            __DIR__.'/config/orderpay.php' => config_path('orderpay.php'),
        ]);
    }
    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        /*$this->app->singleton('orderpay', function ($app) {
            return new Orderpay($app['config']);
        });
		$this->app->singleton('orderpay',function(){
			return $this->app->make('Pay\Orderpay\OrderpayProvider');
		});*/
		$this->app->singleton('orderpay', function ($app) {
            return new Orderpay($app['config']);
        });
    }
}