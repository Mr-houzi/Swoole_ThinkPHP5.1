<?php
/**
 * Created by PhpStorm.
 * User: Mr-houzi
 * Date: 2019/7/11 23:07
 */

use think\Container;

$http = new swoole_http_server("0.0.0.0",8925);

$http->set([
    'enable_static_handler' => true, //开启静态文件请求处理功能
    'document_root' => "/home/wwwroot/default/Swoole_TP/thinkphp/public/static", //静态资源目录
]);

$http->on('WorkerStart',function(swoole_server $server, $worker_id){
    // 同thinkphp 入口文件的相应操作
    // 定义应用目录
    define('APP_PATH',__DIR__ . '/../application/');
    // 加载框架中的文件
    require __DIR__ . '/../thinkphp/base.php';
});

$http->on('request',function ($request,$response) use ($http){

    // 将swoole中$request 与 PHP原生$_SERVER适配
    if (isset($request->server)){
        foreach ($request->server as $k => $v){
            $_SERVER[strtoupper($k)] = $v;
        }
    }
    if (isset($request->header)){
        foreach ($request->header as $k => $v){
            $_SERVER[strtoupper($k)] = $v;
        }
    }
    if (isset($request->get)){
        foreach ($request->get as $k => $v){
            $_GET[$k] = $v;
        }
    }
    if (isset($request->post)){
        foreach ($request->post as $k => $v){
            $_POST[$k] = $v;
        }
    }
    //TODO：还有Cookie等等，操作类似同上

    //打开输出控制缓冲
    ob_start();
    // 执行应用并响应
    try{
        Container::get('app', [APP_PATH ])
            ->run()
            ->send();
    }catch (\Exception $e){
        // todo
    }
    //获取缓冲区的内容,并赋值给一个变量
    $res = ob_get_contents();
    // 清空（擦除）缓冲区并关闭输出缓冲
    ob_end_clean();
    //向客户端发送响应体
    $response->end($res);
    /**
     * 关闭swoole_http_server
     * 由于swoole中会把“模块/控制器/方法”等作为全局变量
     * 导致访问出现问题，所以每次需要关闭swoole_http_server
     * 相应的访问方式为 http://192.168.248.132:8925/?s=index/index/test
     */
    $http->close();
});

$http->start();