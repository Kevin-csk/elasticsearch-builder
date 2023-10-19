<h1> elasticsearch-builder </h1>

<p> A similar laravel-query SDK.</p>
<p> 一个类似Laravel-query的SDK</p>


## 安装 

```shell
$ composer require kevin/elasticsearch-builder -vvv
$ php artisan vendor:publish --provider "Kevin\ElasticsearchBuilder\ElasticsearchServiceProvider"
```

## 快速使用

```php
ElasticsearchBuilder::query()->setIndex('your-index')->where('your-field', 1)->get();
```