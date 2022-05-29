<?php

namespace App\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Monolog\Logger;
use Yansongda\Pay\Pay;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // 往服务容器中注入一个名为 alipay 的单例对象
        $this->app->singleton('alipay', function () {

            $config               = config('pay.alipay');
            $config['notify_url'] = ngrok_url('payment.alipay.notify');
            $config['return_url'] = route('payment.alipay.return');

            // 判断当前项目运行环境是否为线上环境
            if (app()->environment() !== 'production') {
                $config['mode']         = 'dev';
                $config['log']['level'] = Logger::DEBUG;
            } else {
                $config['log']['level'] = Logger::WARNING;
            }
            // 调用 Yansongda\Pay 来创建一个支付宝支付对象
            return Pay::alipay($config);
        });

        $this->app->singleton('wechat_pay', function () {

            $config = config('pay.wechat');
            $config['notify_url'] = ngrok_url('payment.wechat.notify');

            if (app()->environment() !== 'production') {
                $config['log']['level'] = Logger::DEBUG;
            } else {
                $config['log']['level'] = Logger::WARNING;
            }
            // 调用 Yansongda\Pay 来创建一个微信支付对象
            return Pay::wechat($config);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // sql 日志
        if (config('app.debug')) {
            Schema::defaultStringLength(191);
            DB::listen(function ($query) {
                /** @var QueryExecuted $query */
                // dd($query->connection, $query->connection->getDatabaseName(), $query);
                // 获取数据库名称
                $sql = 'use ' . $query->connection->getDatabaseName() . ';' . "\n" . $query->sql;
                // $sql = $query->sql;
                if (! Arr::isAssoc($query->bindings)) {
                    foreach ($query->bindings as $value) {
                        if ($value instanceof \DateTime) {
                            $value = $value->format('Y-m-d H:i:s');
                        }
                        $sql = Str::replaceFirst('?', "'{$value}'", $sql);
                    }
                }

                // dd(request()->getClientIp(), request()->getMethod(), request()->getPathInfo(), request(), $sql);
                Log::info(sprintf('[%s][%s][%s]' . "\n" . '%s;' . "\n", $query->time, request()->getMethod(), request
                ()->fullUrl(), $sql));
            });
        }

        \Illuminate\Pagination\Paginator::useBootstrap();
    }

}
