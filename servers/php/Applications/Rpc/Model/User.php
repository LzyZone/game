<?php

namespace Model;

use Lib\RpcException;
use Lib\User\LoginRequest;
use Lib\User\LoginResponse;
use WuTi\Library\Common\functions;

class User{

    /**
     * @param $stream
     * @return LoginResponse
     * @throws \Exception
     */
    public function login($stream){
        $request = new LoginRequest();
        $request->mergeFromString($stream);
        $username = $request->getUsername();
        $password = $request->getPassword();
        throw new RpcException('Login error');
        $response = new LoginResponse();
        $response->setId(mt_rand(1,1000));
        $response->setUsername($username);
        $response->setToken(functions::makeToken());
        return $response;
    }

    public function loginResponse($stream){

    }
}