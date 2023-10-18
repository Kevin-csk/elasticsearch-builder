<?php
/**
 * @note   Operator${CARET}
 * @author Kevin
 */

namespace Kevin\ElasticsearchBuilder\Constants;

enum Operator:string
{
    case EQUAL_TO = '=';
    case NOT_EQUAL_TO = '!=';

    public function where(){
        return match ($this){
            self::EQUAL_TO => 'filter',
            self::NOT_EQUAL_TO => 'must_not'
        };
    }
}
