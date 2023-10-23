<h1> elasticsearch-builder </h1>
<p> A similar laravel-query SDK.</p>
<p> 一个类似Laravel-query的SDK</p>

## 安装 Install

```shell
$ composer require kevin-csk/elasticsearch-builder
$ php artisan vendor:publish --provider "Kevin\ElasticsearchBuilder\ElasticsearchServiceProvider"
```

## 快速使用 Quick to use

##### 初始化配置 Init config
```php
return [
    // 主机
    'host' => '',
    // 端口
    'port' => '',
    // 用户名
    'username' => '',
    // 密码
    'password' => '',
];
```

```php
use Kevin\ElasticsearchBuilder\Facades\ElasticsearchBuilder;

// 创建索引 Create index
$your_index = ElasticsearchBuilder::query()->createIndex('your-index');

// 初始化结构 Init structure
ElasticsearchBuilder::query()->initMap('your-index', 'your-structure');

// 新增或编辑单条数据 Create|Update single  data
ElasticsearchBuilder::query()->setIndex('your-index')->sync('your-data', 'your-id');

// 搜索 Search
ElasticsearchBuilder::query()->setIndex('your-index')->where('your-field', 1)->get();

```