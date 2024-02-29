<?php

declare(strict_types=1);

/**
 * @note   Builder
 * @author Kevin
 */

namespace Kevin\ElasticsearchBuilder;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\AuthenticationException;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use GuzzleHttp\Psr7\Exception\MalformedUriException;
use Http\Promise\Promise;
use Kevin\ElasticsearchBuilder\Constants\Logic;
use Kevin\ElasticsearchBuilder\Constants\Operator;
use Kevin\ElasticsearchBuilder\Exception\InvalidArgumentException;
use Kevin\ElasticsearchBuilder\Exception\InvalidConfigException;

class Builder
{
    protected string $index = '';

    protected array $params = [];

    protected array $functions = [];

    protected Client $build;

    /**
     * @throws InvalidConfigException
     */
    public function __construct()
    {
        // 从配置文件读取 Elasticsearch 服务器列表 拼接配置项
        $hosts   = explode(',', config('elasticsearch.host').':'.config('elasticsearch.port'));
        $builder = ClientBuilder::create()->setHosts($hosts)->setBasicAuthentication(config('elasticsearch.username'), config('elasticsearch.password'));
        // 如果是开发环境
        if (app()->isLocal()) {
            // 方便调试
            $builder->setLogger(app('log')->driver());
        }

        try {
            $this->build = $builder->build();
        } catch (MalformedUriException|AuthenticationException $exception) {
            throw new InvalidConfigException('Invalid configuration');
        }

        $this->params = [
            'index' => '',
            'body'  => [
                'query' => [
                    'bool' => [
                        'filter' => [],
                        'must'   => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * 设置索引.
     * Set index.
     *
     * @param  string $index
     * @return $this
     */
    public function setIndex(string $index): static
    {
        $this->index = $index;

        return $this;
    }

    /**
     * 获取一个新的query.
     * Obtain a new query.
     *
     * @author Kevin
     * @return static
     */
    public static function query(): static
    {
        return new static();
    }

    /**
     * 根据id同步单条数据.
     * Synchronize individual data based on ID.
     *
     * @author Kevin
     * @param  array                     $data
     * @param  mixed                     $id
     * @return true
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws ServerResponseException
     */
    public function sync(array $data, mixed $id): bool
    {
        $this->build->index([
            'index' => $this->index,
            'type'  => '_doc',
            'id'    => $id,
            'body'  => $data,
        ]);

        return true;
    }

    /**
     * 根据id删除单条数据.
     * Delete a single piece of data based on ID.
     *
     * @author Kevin
     * @param  mixed                     $id
     * @return true
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws ServerResponseException
     */
    public function delete(mixed $id): bool
    {
        $this->build->delete([
            'index' => $this->index,
            'id'    => $id,
        ]);

        return true;
    }

    /**
     * 创建索引.
     * Create index.
     *
     * @author Kevin
     * @param  string                    $index
     * @return bool
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws ServerResponseException
     */
    public function createIndex(string $index): bool
    {
        $this->build->indices()->create(['index' => $index]);

        return true;
    }

    /**
     * 删除索引.
     * Delete index.
     *
     * @author Kevin
     * @param  string                    $index
     * @return bool
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws ServerResponseException
     */
    public function deleteIndex(string $index): bool
    {
        $this->build->indices()->delete(['index' => $index]);

        return true;
    }

    /**
     * 索引是否存在.
     * Exists index.
     *
     * @author Kevin
     * @param  string                    $index
     * @return bool
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws ServerResponseException
     */
    public function indexExists(string $index): bool
    {
        return $this->build->indices()->exists(['index' => $index])->asBool();
    }

    /**
     * 初始化结构.
     * Init Mapping.
     *
     * @author Kevin
     * @param  string                    $index
     * @param  array                     $properties
     * @return Elasticsearch|Promise
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws ServerResponseException
     */
    public function initMap(string $index, array $properties): Elasticsearch|Promise
    {
        $params = [
            'index' => $index,
            'body'  => [
                '_source' => [
                    'enabled' => true,
                ],
                'properties' => $properties,
            ],
        ];

        return $this->build->indices()->putMapping($params);
    }

    /**
     * 同步数据.
     * Sync data.
     *
     * @author Kevin
     * @param  array                   $data
     * @param  mixed                   $id
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function syncData(array $data, mixed $id): void
    {
        // 初始化请求
        $res['body'][] = [
            'index' => [
                '_index' => $this->index,
                '_id'    => $id,
            ],
        ];
        $res['body'][] = [$data];

        // 使用 bulk 方法批量创建
        $this->build->bulk($res);
    }

    /**
     * 分页查询.
     * Paginate the given query into a simple paginator.
     *
     * @param  int                      $pageSize
     * @param  int                      $page
     * @return array
     * @throws ClientResponseException
     * @throws InvalidArgumentException
     * @throws ServerResponseException
     */
    public function paginate(int $pageSize, int $page = 1): array
    {
        $this->params['body']['from'] = ($page - 1) * $pageSize;
        $this->params['body']['size'] = $pageSize;

        return $this->get();
    }

    /**
     * 设置查询的获取值数量
     * Set the "limit" value of the query.
     *
     * @author Kevin
     * @param  int   $size
     * @return $this
     */
    public function limit(int $size): static
    {
        $this->params['body']['size'] = $size;

        return $this;
    }

    /**
     * 添加一个where子句
     * Add a basic where clause to the query.
     *
     * @author Kevin
     * @param  string $field
     * @param  mixed  $value
     * @param  string $operator
     * @param  string $queryType
     * @return $this
     */
    public function where(string $field, mixed $value, string $operator = '=', string $queryType = 'term'): static
    {
        $operatorWhere = Operator::tryFrom($operator);
        if ($operatorWhere?->where()) {
            $this->params['body']['query']['bool'][$operatorWhere?->where()][] = [$queryType => [$field => $value]];
        }

        return $this;
    }

    /**
     * 添加一个 where null 子句
     * Add a "where null" clause to the query.
     *
     * @author Kevin
     * @param  string $field
     * @param  bool   $isNull
     * @return $this
     */
    public function whereNull(string $field, bool $isNull = true): static
    {
        if ($isNull) {
            $this->params['body']['query']['bool']['must_not'] = ['exists' => ['field' => $field]];
        } else {
            $this->params['body']['query']['bool']['must'] = ['exists' => ['field' => $field]];
        }

        return $this;
    }

    /**
     * 添加搜索词-模糊查询.
     * Add search term keywords.
     *
     * @author Kevin
     * @param  array|string $keywords
     * @param  array        $fields
     * @return $this
     */
    public function keywords(string|array $keywords, array $fields): static
    {
        // 如果参数不是数组则转为数组
        $keywords = is_array($keywords) ? $keywords : [$keywords];
        foreach ($keywords as $keyword) {
            $this->params['body']['query']['bool']['must'][] = [
                'multi_match' => [
                    'query'  => $keyword,
                    'fields' => $fields,
                ],
            ];
        }

        return $this;
    }

    /**
     * 设置 minimum_should_match 参数 最小匹配数.
     * Set minimum_should_match The minimum number of matches for the match parameter.
     *
     * @author Kevin
     * @param  int     $count
     * @return Builder
     */
    public function minShouldMatch(int $count = 1): static
    {
        $this->params['body']['query']['bool']['minimum_should_match'] = $count;

        return $this;
    }

    /**
     * 设置最小匹配分数
     * Set minimum matching score.
     *
     * @author Kevin
     * @param  string $score
     * @return $this
     */
    public function min_score(string $score = '0.0'): static
    {
        $this->params['body']['min_score'] = $score;

        return $this;
    }

    /**
     * 添加排序.
     * Add an "order by" clause to the query.
     *
     * @author Kevin
     * @param  array|string $orders
     * @param  string       $direction
     * @return $this
     */
    public function orderBy(array|string $orders = ['id' => 'DESC'], string $direction = 'DESC'): static
    {
        if (is_array($orders)) {
            foreach ($orders as $field => $direction) {
                $this->params['body']['sort'][] = [$field => $direction];
            }
        } else {
            $this->params['body']['sort'][] = [$orders => $direction];
        }

        return $this;
    }

    /**
     * 添加搜索词高亮.
     * Add search term highlighting.
     *
     * @author Kevin
     * @param  array|string $fields
     * @param  string       $class
     * @return $this
     */
    public function highlight(string|array $fields, string $class): static
    {
        $fields          = is_array($fields) ? $fields : [$fields];
        $highlightFields = [];
        foreach ($fields as $field) {
            $highlightFields[$field] = new \stdClass();
        }

        $this->params['body']['highlight'] = [
            'pre_tags'            => '<span class="'.$class.'">',
            'post_tags'           => '</span>',
            'require_field_match' => true,
            'fields'              => $highlightFields,
        ];

        return $this;
    }

    /**
     * 添加一个where子句 - 嵌套查询
     * Add a where clause - nested query.
     *
     * @author Kevin
     * @param string $path
     * @param string $fields
     * @param mixed  $value
     * @param string $logic
     * @param string $queryType
     * @param bool   $isFunction
     * @return $this
     */
    public function whereNested(string $path, string $fields, mixed $value, string $logic = '||', string $queryType = 'term', bool $isFunction = true): static
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $condition = [
                    'nested' => [
                        'path'  => $path,
                        'query' => [
                            $queryType => [$fields => $item],
                        ],
                    ],
                ];
                $this->params['body']['query']['bool'][Logic::tryFrom($logic)->logic()][] = $condition;
                if ($isFunction) {
                    $this->functions[] = [
                        'filter' => $condition,
                        'weight' => 2,
                    ];
                }
            }
        } else {
            $condition = [
                'nested' => [
                    'path'  => $path,
                    'query' => [
                        $queryType => [$fields => $value],
                    ],
                ],
            ];
            $this->params['body']['query']['bool'][Logic::tryFrom($logic)->logic()][] = $condition;
            if ($isFunction) {
                $this->functions[] = [
                    'filter' => $condition,
                    'weight' => 2,
                ];
            }
        }

        return $this;
    }

    /**
     * 添加一个where子句并自定义query查询 - 嵌套查询
     * Add a where clause and customize the query - nested query.
     *
     * @note   whereNestedQuery
     * @author Kevin
     * @param  string $path
     * @param  string $logic
     * @param  bool   $isFunction
     * @param  array  $query
     * @return $this
     */
    public function whereNestedQuery(string $path, array $query, string $logic = '||', bool $isFunction = true): static
    {
        $condition = [
            'nested' => [
                'path'  => $path,
                'query' => $query,
            ],
        ];
        $this->params['body']['query']['bool'][Logic::tryFrom($logic)->logic()][] = $condition;
        if ($isFunction) {
            $this->functions[] = [
                'filter' => $condition,
                'weight' => 2,
            ];
        }

        return $this;
    }

    /**
     * 打分.
     * Scoring.
     *
     * @author Kevin
     * @param  string $scoreMode
     * @param  string $boostMode
     * @return $this
     */
    public function functions(string $scoreMode = 'sum', string $boostMode = 'replace'): static
    {
        $this->params['body']['query'] = [
            'function_score' => [
                'query'      => $this->params['body']['query'],
                'functions'  => $this->functions,
                'score_mode' => $scoreMode,
                'boost_mode' => $boostMode,
            ],
        ];

        return $this;
    }

    // code:分组标识,path:聚合单词名,field:聚合字段,field_type:字段es类型
    /*
     * $arr = [
     *      [
     *          'code' => '标签',
     *          'path' => 'tags',
     *          'field_type' => '',
     *          'child' => [
     *              [
     *                  'code' => '标签名称',
     *                  'path' => 'tags.name',
     *                  'field_type' => '',
     *              ]
     *           ]
     *      ],
     *      [
     *          'code' => 'category',
     *          'path' => 'category',
     *          'field_type' => '',
     *          'child' => [
     *              [
     *                  'code' => 'category.name',
     *                  'path' => 'category.name',
     *                  'field_type' => '',
     *                  'child' => [
     *                      [
     *                          'code' => 'category.value',
     *                          'path' => 'category.value',
     *                          'field_type' => '',
     *                      ]
     *                  ]
     *              ]
     *           ]
     *      ]
     * ];
     */
    public function aggs($arr)
    {
        $this->getAggs($arr);

        return $this;
    }

    public function getAggs(array $items, array $aggregations = [])
    {
        foreach ($items as $item) {
            $aggregations['aggs'] = [
                $item['code'] => [
                    'nested' => [
                        'path' => $item['path'],
                    ],
                ],
            ];
            if (!empty($item['child'])) {
                return $this->getAggs($item, $aggregations['aggs']);
            }

            return $aggregations;
        }
    }

    /**
     * 返回构造好的查询参数.
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * 获取搜索结果.
     * Search.
     *
     * @author Kevin
     * @return array
     * @throws ClientResponseException|InvalidArgumentException|ServerResponseException
     */
    public function get(): array
    {
        if (empty($this->index)) {
            throw new InvalidArgumentException('Invalid parameter [index].');
        }
        $this->params['index'] = $this->index;

        return $this->build->search($this->params)->asArray();
    }

    /**
     * 设置索引结构.
     * Set index structure.
     *
     * @author Kevin
     * @param mixed $properties
     */
    private function _setParams(array $properties): void
    {
        $this->params = [
            'index' => $this->index,
            'body'  => [
                '_source' => [
                    'enabled' => true,
                ],
                'properties' => $properties,
            ],
        ];
    }
}
