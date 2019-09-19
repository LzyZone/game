<?php
require_once __DIR__.'/../../vendor/autoload.php';

$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_connect($sock, '127.0.0.1', 2015);
$cmd = 'User.login';
$login = new \Lib\User\LoginRequest();
$login->setUsername('admin');
$login->setPassword('123456');
$content = $login->serializeToString();

$request = pack('SN',strlen($cmd),strlen($content)).$cmd.$content;
socket_write($sock,$request);
$ret = socket_read($sock, 1024);

$unpack_data = unpack('Scmd/Nbody_len',substr($ret,0,6));
var_dump($unpack_data);

$cmd = substr($ret,6,$unpack_data['cmd']);
$content = substr($ret,6+$unpack_data['cmd']);

if($cmd == 'error'){
    $error = new \Lib\RpcError();
    $error->mergeFromString($content);
    var_dump($error->getCode(),$error->getMessage());
}else{
    list($class,$method) = explode('.',$cmd);
    $class = '\Model\\'.$class;

    $response = new \Lib\User\LoginResponse();
    $response->mergeFromString($content);
    $id = $response->getId();
    $username = $response->getUsername();
    $token = $response->getToken();

    var_dump($cmd,$id,$username,$token);
}


socket_close($sock);
