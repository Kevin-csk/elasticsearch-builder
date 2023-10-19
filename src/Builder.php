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
     * set index.
     *
     * @param  string $index
     * @return $this
     */
    public function setIndex(string $index): static
    {
        $this->params['index'] = $index;

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
     * 分页查询.
     * Paginate the given query into a simple paginator.
     *
     * @param  int                      $pageSize
     * @param  int                      $page
     * @return Elasticsearch|Promise
     * @throws ClientResponseException
     * @throws InvalidArgumentException
     * @throws ServerResponseException
     */
    public function paginate(int $pageSize, int $page = 1): Elasticsearch|Promise
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
     * @param  string $path
     * @param  string $fields
     * @param  mixed  $value
     * @param  string $logic
     * @param  string $queryType
     * @return $this
     */
    public function whereNested(string $path, string $fields, mixed $value, string $logic = '||', string $queryType = 'term'): static
    {
        $this->params['body']['query']['bool'][Logic::tryFrom($logic)->logic()][] = [
            'nested' => [
                'path'  => $path,
                'query' => [
                    $queryType => [$fields => $value],
                ],
            ],
        ];

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
     * search.
     *
     * @author Kevin
     * @return Elasticsearch|Promise
     * @throws ClientResponseException|InvalidArgumentException|ServerResponseException
     */
    public function get(): Elasticsearch|Promise
    {
        if (empty($this->params['index'])) {
            throw new InvalidArgumentException('Invalid parameter [index].');
        }

        return $this->build->search($this->params);
    }
}
