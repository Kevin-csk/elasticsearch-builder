<?php
/**
 * @note   Operator${CARET}
 * @author Kevin
 */

namespace Kevin\ElasticsearchBuilder\Constants;

enum Logic:string
{
    case AND = '&&';
    case OR = '||';

    case NOT_EQUAL_TO = '!=';

    public function logic(){
        return match ($this){
            self::AND => 'must',
            self::OR => 'should',
            self::NOT_EQUAL_TO => 'must_not',
        };
    }
}
