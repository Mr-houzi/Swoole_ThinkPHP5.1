# Swoole+ThinkPHP 5.1

> 此项目为手动适配Swoole和ThinkPHP，除此之外也可以使用 `think-swoole`扩展。
> 文档链接：https://www.kancloud.cn/thinkphp/think-swoole/722895

## 目录

- thinkphp tp框架
    - server swoole_http_server相关业务

## 手动适配流程

将`ThinkPHP5.1`框架复制到项目根目录下，并命名为`thinkphp`。

在thinkphp目录下新建server文件夹，并创建 http_server.php编写响应的代码。

在`$http->set`中开启静态文件处理并制定静态资源目录

```
$http->set([
    'enable_static_handler' => true, //开启静态文件请求处理功能
    'document_root' => "/home/wwwroot/default/Swoole_ThinkPHP/thinkphp/public/static", //静态资源目录
]);
```

在`WorkStart`中，定义和ThinkPHP入口文件相同的操作，`start.php`文件中存在让文件运行的代码（Container::get()->run），
为了避免程序运行（程序真正运行是放在request事件中），我们没有直接引入`start.php`，而是仅引入`start.php`中加载框架基础文件的代码

```
$http->on('WorkerStart',function(swoole_server $server, $worker_id){
    // 同thinkphp 入口文件的相应操作
    // 定义应用目录
    define('APP_PATH',__DIR__ . '/../application/');
    // 加载框架中的文件
    require __DIR__ . '/../thinkphp/base.php';
});
```

在request中增加如下代码，将文件运行的代码（Container::get()->run）也加入

```
$http->on('request',function ($request,$response){

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
    Container::get('app', [APP_PATH ])
        ->run()
        ->send();
    //获取缓冲区的内容,并赋值给一个变量
    $res = ob_get_contents();
    // 清空（擦除）缓冲区并关闭输出缓冲
    ob_end_clean();
    //向客户端发送响应体
    $response->end($res); 
});
```

在request最后关闭swoole_http_server

```
/**
 * 关闭swoole_http_server
 * 由于swoole中会把“模块/控制器/方法”等作为全局变量
 * 导致访问出现问题，所以每次需要关闭swoole_http_server
 * 相应的访问方式为 http://192.168.248.132:8925/?s=index/index/test&num=1&name=hz
 */
$http->close();
```

PS：已改为面向对象访问并对其优化，访问方式：http://192.168.248.132:8925/index/index/test?a=123&b=456

完整代码见[http_server.php](./thinkphp/server/http_server.php)