<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/23
 * Time: 14:46
 */

require_once __DIR__ . '/src/SocketMsg.php';
require_once __DIR__ . '/src/Room.php';
require_once __DIR__ . '/src/Log.php';

use DouYu\Room;
use Douyu\Log;

date_default_timezone_set('PRC');

defined('DouYuHost') || define('DouYuHost', 'openbarrage.douyutv.com');
defined('DouYuPort') || define('DouYuPort', 8601);

Log::log("程序启动!", LOG::WARN);

// $roomDataArr = getRoomIdArr(0, 99);
$roomDataArr=array_merge(
    getRoomIdArr(0, 99)
);
// print_r(count($roomDataArr));exit;
$roomObjArr = array();
foreach ($roomDataArr as $roomData) {
    array_push($roomObjArr, new  Room(DouYuHost, DouYuPort, $roomData['room_id'], 20));
}
unset($roomDataArr);
foreach ($roomObjArr as $room) {
   
    try {
        $room->onMessage=function($msg_obj,$roomId) {
            if ($msg_obj) {
                $content='';
                switch ($msg_obj['type']) {
                    case 'chat':
                        $content = "[房间号:{$roomId}] [{$msg_obj['from']['name']}]:{$msg_obj['content']}";
                        break;
                    case 'gift':
                        $content = "[房间号:{$roomId}] [{$msg_obj['from']['name']}]->赠送{$msg_obj['count']}个{$msg_obj['name']}";
                        break;
                    case 'yuwan':
                        $content = "[房间号:{$roomId}] [{$msg_obj['from']['name']}]->赠送{$msg_obj['count']}个{$msg_obj['name']}";
                        break;
                    case 'deserve':
                        $content = "[房间号:{$roomId}] [{$msg_obj['from']['name']}]->赠送{$msg_obj['count']}个{$msg_obj['name']}";
                        break;
                }
               if($content) echo iconv('UTF-8','gbk//IGNORE', $content).PHP_EOL;
               unset($content);
            }
        };
        $room->joinIn();
        $room->start();

    } catch (\Exception $e) {
        gbk_echo($e->getMessage());
        Log::log($e->getMessage(), LOG::ERROR);
    }
    $room->onConnect = function ($linkNum) use ($room) {
        $roomId = $room->getRoomId();
        $content = "成功连接到斗鱼房间号{$roomId},当前连接总数{$linkNum}";
        gbk_echo($content);
        Log::log($content, LOG::WARN);
    };
    $room->onClose = function ($linkNum, $roomID) {
        $content = "由于程序发生异常已关闭连接房间号{$roomID}!当前连接数{$linkNum}";
        gbk_echo($content);
        Log::log($content, LOG::ERROR);
    };
    $room->onError = function ($errorMsg, $roomID) {
        $errorMsg=iconv('gbk//TRANSLIT', 'UTF-8', $errorMsg);
        $content = "无法建立连接,房间号{$roomID},{$errorMsg}";
        gbk_echo($content);
        Log::log($content, LOG::ERROR);
    };
}


function getRoomIdArr($offset = 0, $limit = 99)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "http://open.douyucdn.cn/api/RoomApi/live?offset={$offset}&limit={$limit}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "cache-control: no-cache",
            "content-type: application/x-www-form-urlencoded"
        ),
    ));
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    if ($err) {
        Log::log('获取房间列表失败:' . $err, LOG::ERROR);
        return array();
    } else {
        $arr = json_decode($response, true);
        if (isset($arr['error']) && isset($arr['data']) && $arr['error'] === 0) {
            return $arr['data'];
        } else {
            return array();
        }

    }
}

function gbk_echo($msg)
{
    echo iconv('UTF-8', 'gbk//IGNORE', $msg).PHP_EOL;
}



