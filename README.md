# Swoole+ThinkPHP 5.1

> 此项目为手动适配Swoole和ThinkPHP，除此之外也可以使用 `think-swoole`扩展。
> 文档链接：https://www.kancloud.cn/thinkphp/think-swoole/722895

## 项目目录

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

**PS：** http_server.php已改为面向对象。完整代码见[http_server.php](./thinkphp/server/http_server.php)

修改`thinphp/library/Request.php`的pathinfo和path方法，修改如下，解决了路由问题，保证了每次都获取最新的路径而不是一开始的路径。

*pathinfo*

```
public function pathinfo()
    {
//        if (is_null($this->pathinfo)) { //注释掉此行
            if (isset($_GET[$this->config->get('var_pathinfo')])) {
                // 判断URL里面是否有兼容模式参数
                $_SERVER['PATH_INFO'] = $_GET[$this->config->get('var_pathinfo')];
                unset($_GET[$this->config->get('var_pathinfo')]);
            } elseif ($this->isCli()) {
                // CLI模式下 index.php module/controller/action/params/...
                $_SERVER['PATH_INFO'] = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '';
            }

            // 分析PATHINFO信息
            if (!isset($_SERVER['PATH_INFO'])) {
                foreach ($this->config->get('pathinfo_fetch') as $type) {
                    if (!empty($_SERVER[$type])) {
                        $_SERVER['PATH_INFO'] = (0 === strpos($_SERVER[$type], $_SERVER['SCRIPT_NAME'])) ?
                        substr($_SERVER[$type], strlen($_SERVER['SCRIPT_NAME'])) : $_SERVER[$type];
                        break;
                    }
                }
            }

            $this->pathinfo = empty($_SERVER['PATH_INFO']) ? '/' : ltrim($_SERVER['PATH_INFO'], '/');
//        } //注释掉此行

        return $this->pathinfo;
    }
```

*path*

```
public function path()
    {
//        if (is_null($this->path)) { //注释掉此行
            $suffix   = $this->config->get('url_html_suffix');
            $pathinfo = $this->pathinfo();
            if (false === $suffix) {
                // 禁止伪静态访问
                $this->path = $pathinfo;
            } elseif ($suffix) {
                // 去除正常的URL后缀
                $this->path = preg_replace('/\.(' . ltrim($suffix, '.') . ')$/i', '', $pathinfo);
            } else {
                // 允许任何后缀访问
                $this->path = preg_replace('/\.' . $this->ext() . '$/i', '', $pathinfo);
            }
//        } //注释掉此行

        return $this->path;
    }
```

> 访问方式为 http://192.168.248.132:8925/?s=index/index/test&num=1&name=hz