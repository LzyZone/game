<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 主逻辑
 * 主要是处理 onMessage onClose 三个方法
 */

use \GatewayWorker\Lib\Gateway;

class Events
{
    /**
     * @var \Redis
     */
    private static $redis = null;
    const ROOM_KEY = "GOBANG:ROOM:%s";
    const CLIENT_ROOM_MAP = "GOBANG:CLIENT:ROOM:MAP";
    const GOBANGS_MAP = "GOBANS:MAP:%s";


    public static function onWorkerStart(\GatewayWorker\BusinessWorker $businessWorker){
        echo "onWorkerStart\r\n";
        self::$redis = new \Redis();
        $conn = self::$redis->connect('127.0.0.1',6379,3);
        if(!$conn){
            throw new \Exception("redis 连接失败！".self::$redis->getLastError());
        }
    }


    /**
     * 当客户端连上时触发
     * @param int $client_id
     */
    public static function onConnect($client_id)
    {
        //Gateway::sendToCurrentClient('{"cmd":"welcome","id":"'.$_SESSION['id'].'"}');
    }
    
   /**
    * 有消息时
    * @param int $client_id
    * @param string $message
    */
   public static function onMessage($client_id, $message)
   {    // 获取客户端请求
        $message_data = json_decode($message, true);
        if(!$message_data)
        {
            return ;
        }
        //var_dump($message_data);

        $room_id = isset($message_data['room_id']) ? intval($message_data['room_id']) : 0;
        $cmd = isset($message_data['cmd']) ? trim($message_data['cmd']) : '';
        if(empty($room_id) || empty($cmd)){
            return Gateway::closeClient($client_id);
        }
        $key = sprintf(self::ROOM_KEY,$room_id);

       //如果没有$_SESSION['uid']说明客户端没有登录
       if(!isset($_SESSION['uid']))
       {
           // 消息类型不是登录视为非法请求，关闭连接
           if($cmd !== 'login')
           {
               return Gateway::closeClient($client_id);
           }
       }


        switch($cmd)
        {
            case 'login':
                //$user       = $message_data['user'];
                //$password   = $message_data['password'];
                $_SESSION['uid'] = $client_id.'_'.time();
                $new_message = [
                  'cmd' => $cmd,
                  'uid' => $_SESSION['uid'],
                  'role_type' => mt_rand(0,1)
                ];
                $cache = self::$redis->hGetAll($key);
                if(empty($cache)){
                    self::$redis->hMSet($key,[$client_id=>$new_message['role_type']]);
                }else{

                    if(count($cache) >= 2){
                        Gateway::closeClient($client_id);
                        return false;
                    }

                    foreach ($cache as $ck=>$rv){
                        if(Gateway::isOnline($ck)){
                            $new_message['role_type'] = $rv ? 0 : 1;break;
                        }
                    }

                    $index = array_search($new_message['role_type'],$cache);
                    if($index !== false){
                        self::$redis->hDel($key,$index);
                        self::$redis->hDel(self::CLIENT_ROOM_MAP,$client_id);
                    }
                    self::$redis->hMSet($key,[$client_id=>$new_message['role_type']]);
                }
                self::$redis->hSet(self::CLIENT_ROOM_MAP,$client_id,$room_id);
                Gateway::sendToClient($client_id,json_encode($new_message));
                break;
            //游戏
            case 'game':
                $key = sprintf(self::ROOM_KEY,$room_id);
                $cache = self::$redis->hGetAll($key);
                $map_key = sprintf(self::GOBANGS_MAP,$room_id);
                $x = $message_data['x'];
                $y = $message_data['y'];
                $xy = $x.','.$y;
                if(self::$redis->sIsMember($map_key,$xy)){
                    return false;
                }
                self::$redis->sAdd($map_key,$xy);

                $role_map_key = $map_key.":{$client_id}";
                self::$redis->sAdd($role_map_key,$xy);

                $role_type = intval($cache[$client_id]);
                if(self::checkWin($client_id,$room_id)){
                    Gateway::sendToAll(json_encode([
                        'cmd' => 'win',
                        'role_type' =>$role_type,
                        'x' => $x,
                        'y' => $y
                    ]));
                }else{
                    //转播给所有用户
                    Gateway::sendToAll(json_encode(
                        array(
                            'cmd'       => $cmd,
                            'role_type' => $role_type,
                            'x'         => $x,
                            'y'         => $y,
                            'next'      => $role_type ? 0 : 1
                        )
                    ));
                }
                return;
            // 聊天
            case 'message':
                // 向大家说
                $new_message = array(
                    'cmd'       => 'message',
                    'id'        => $_SESSION['id'],
                    'message'   => $message_data['message'],
                );
                return Gateway::sendToAll(json_encode($new_message));
            case 'ping':
                $key = sprintf(self::ROOM_KEY,$room_id);
                $new_message['cmd'] = $cmd;
                $new_message['online_count'] = self::$redis->hLen($key);
                return Gateway::sendToClient($client_id,json_encode($new_message));
        }
   }

   private static function checkWin($client_id,$room_id){
       $map_key = sprintf(self::GOBANGS_MAP,$room_id);
       $role_map_key = $map_key.":{$client_id}";
       $members = self::$redis->sMembers($role_map_key);
       $is_win = false;
       $xset = $yset = [];
       foreach ($members as $k=>$v){
           list($x,$y) = explode(',',$v);
           $xset[$x] = isset($xset[$x]) ? $xset[$x]+1 : 1;
           if($xset[$x] == 5){
               var_dump('===1===');
               $is_win = true;
               break;
           }

           $yset[$y] = isset($yset[$y]) ? $yset[$y]+1 : 1;
           if($yset[$y] == 5){
               var_dump('===2===');
               $is_win = true;
               break;
           }
       }

       if(!$is_win){
           $step = 60;
           foreach ($members as $k=>$v){
               list($x,$y) = explode(',',$v);
               for($i=1;$i<=4;$i++){
                   $x += $step;
                   $y += $step;
                   $xy = $x.','.$y;
                   if(!in_array($xy,$members)){
                       break 1;
                   }

                   if($i == 4){
                       var_dump('===3===');
                       $is_win = true;
                       break 2;
                   }
               }
           }
       }

       if(!$is_win){
           foreach ($members as $k=>$v){
               list($x,$y) = explode(',',$v);
               for($i=1;$i<=4;$i++){
                   $x -= $step;
                   $y += $step;
                   $xy = $x.','.$y;
                   if(!in_array($xy,$members)){
                       break 1;
                   }

                   if($i == 4){
                       var_dump('===4===');
                       $is_win = true;
                       break 2;
                   }
               }
           }
       }

       return $is_win;
   }
   
   /**
    * 当用户断开连接时
    * @param integer $client_id 用户id
    */
   public static function onClose($client_id)
   {
       var_dump('close...'.$client_id);
       $room_id = self::$redis->hGet(self::CLIENT_ROOM_MAP,$client_id);
       $key = sprintf(self::ROOM_KEY,$room_id);
       self::$redis->hDel($key,$client_id);
       self::$redis->hDel(self::CLIENT_ROOM_MAP,$client_id);
       $map_key = sprintf(self::GOBANGS_MAP,$room_id);

       $role_map_key = $map_key.":{$client_id}";
       self::$redis->delete($role_map_key);

       if(!self::$redis->hLen($key)){
           self::$redis->delete($map_key);
       }else{
           self::$redis->hDel($map_key,$client_id);
       }

       if (isset($_SESSION['uid'])) {
            // 广播 xxx 退出了
            GateWay::sendToClient($client_id,json_encode(array('cmd'=>'closed', 'id'=>$_SESSION['uid'])));
       }
   }


}
