<?php

declare(strict_types=1);

/**
 * @note   ElasticsearchServiceProvider${CARET}
 * @author Kevin
 */

namespace Kevin\ElasticsearchBuilder;

use Illuminate\Support\ServiceProvider;

class ElasticsearchServiceProvider extends ServiceProvider
{
    /**
     * 标记着提供器是延迟加载的.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * 注册服务提供者.
     */
    public function register()
    {
        $this->publishes([
            __DIR__.'/../config/elasticsearch.php' => config_path('elasticsearch.php'),
        ], 'config');

        $this->app->singleton('es', function () {
            return new Builder();
        });
    }

    /**
     * 取得提供者提供的服务
     *
     * @return array
     */
    public function provides()
    {
        return ['es'];
    }
}
