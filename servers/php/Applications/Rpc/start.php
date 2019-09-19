<?php
use Workerman\Worker;
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__.'/Protocols/TcpNL.php';
// 创建一个Worker监听2345端口，使用http协议通讯
$http_worker = new Worker('TcpNL://0.0.0.0:2015');
// 启动4个进程对外提供服务
$http_worker->count = 4;

// 接收到浏览器发送的数据时回复hello world给浏览器
$http_worker->onMessage = function($connection, $data)
{
    try{
        list($class,$method) = explode('.',$data['cmd']);
        $class = '\Model\\'.$class;
        if(!class_exists($class)){
            throw new \Lib\RpcException('the '.$class.' not found');
        }
        $model = new $class();
        if(!method_exists($model,$method)){
            throw new \Lib\RpcException('the '.$class.'->'.$method.' not found');
        }
        $response = $model->$method($data['content']);
        // 向浏览器发送hello world
        $connection->send(['cmd'=>$data['cmd'],'content'=>$response->serializeToString()]);
    }catch (\Exception $ex){
        $error = new \Lib\RpcError();
        $error->setCode($ex->getCode());
        $error->setMessage($ex->getMessage());
        $connection->send(['cmd'=>'error','content'=>$error->serializeToString()]);
    }
};

// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}
