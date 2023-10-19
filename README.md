<h1> elasticsearch-builder </h1>

<p> A similar laravel-query SDK.</p>
<p> 一个类似Laravel-query的SDK</p>


## 安装 

```shell
$ composer require kevin-csk/elasticsearch-builder
$ php artisan vendor:publish --provider "Kevin\ElasticsearchBuilder\ElasticsearchServiceProvider"
```

## 快速使用

```php
use Kevin\ElasticsearchBuilder\Facades\ElasticsearchBuilder;
ElasticsearchBuilder::query()->setIndex('your-index')->where('your-field', 1)->get();
```