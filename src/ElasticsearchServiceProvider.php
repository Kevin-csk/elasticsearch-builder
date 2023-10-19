<?php

declare(strict_types=1);

/**
 * @note   ElasticsearchServiceProvider${CARET}
 * @author Kevin
 */

namespace Kevin\ElasticsearchBuilder;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Support\DeferrableProvider;
use Kevin\ElasticsearchBuilder\Facades\ElasticsearchBuilder;

class ElasticsearchServiceProvider extends ServiceProvider implements DeferrableProvider
{

    /**
     * 注册服务提供者.
     */
    public function register(): void
    {
        $this->publishes([
            __DIR__.'/../config/elasticsearch.php' => config_path('elasticsearch.php'),
        ], 'config');

        $this->app->singleton('es', function () {
            return new Builder();
        });

        $this->app->alias('ElasticsearchBuilder', ElasticsearchBuilder::class);
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
