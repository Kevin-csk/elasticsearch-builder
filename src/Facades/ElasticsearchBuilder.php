<?php
/**
 * @note   ElasticsearchBuilder${CARET}
 * @author Kevin
 */

namespace Kevin\ElasticsearchBuilder\Facades;

use Illuminate\Support\Facades\Facade;

class ElasticsearchBuilder extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'es';
    }
}