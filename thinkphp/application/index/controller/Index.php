<?php
namespace app\index\controller;

class Index
{
    public function index()
    {
//        return 'hello ow';
        print_r($_GET);
    }

    public function test()
    {
        return 'this is index/index/test';
    }

    public function hello($name = 'ThinkPHP5')
    {
        return 'hello,' . $name;
    }
}
