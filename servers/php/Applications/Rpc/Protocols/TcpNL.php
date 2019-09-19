<?php

namespace Protocols;

class TcpNL{
    CONST HEAD_LEN = 6;
    const HEAD_PACK = 'SN';//S--Cmd,N--BodyLen

    public static function input($recv_buffer)
    {
        if(strlen($recv_buffer) < self::HEAD_LEN)
        {
            // 不够10字节，返回0继续等待数据
            return 0;
        }
        $unpack_data = unpack('Scmd/Nbody_len',$recv_buffer);
        //var_dump($unpack_data);
        return self::HEAD_LEN+$unpack_data['cmd'] + $unpack_data['body_len'];
    }

    public static function decode($recv_buffer)
    {
        // 去掉首部4字节，得到包体Json数据
        $head_str = substr($recv_buffer, 0,self::HEAD_LEN);
        $unpack_data = unpack('Scmd/Nbody_len',$head_str);
        $data = [];
        $data['cmd'] = substr($recv_buffer,self::HEAD_LEN,$unpack_data['cmd']);
        $data['content'] = substr($recv_buffer,self::HEAD_LEN+$unpack_data['cmd']);
        return $data;
    }

    public static function encode($data)
    {
        // Json编码得到包体['cmd'=>'login','content'=>'']
        // 计算整个包的长度，首部4字节+包体字节数
        $cmd_len = strlen($data['cmd']);
        $total_length = strlen($data['content']);
        // 返回打包的数据
        return pack(self::HEAD_PACK,$cmd_len,
                $total_length) . $data['cmd'].$data['content'];
    }
}
