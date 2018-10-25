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

$roomDataArr=getRoomIdArr();$roomObjArr=array();
foreach ($roomDataArr as $roomData){
    array_push($roomObjArr,new  Room(DouYuHost, DouYuPort, $roomData['room_id']));
}

//@var $room Room
foreach ($roomObjArr as $room) {
    $room->onMessage = function ($msg) use ($room) {
        $content = '';
        $roomId = $room->getRoomId();
        switch ($msg['type']) {
            case 'chat':
                $content = "[房间号:{$roomId}] [{$msg['from']['name']}]:{$msg['content']}";
                break;
            case 'gift':
                $content = "[房间号:{$roomId}] [{$msg['from']['name']}]->赠送{$msg['count']}个{$msg['name']}";
                break;
            case 'yuwan':
                $content = "[房间号:{$roomId}] [{$msg['from']['name']}]->赠送{$msg['count']}个{$msg['name']}";
                break;
            case 'deserve':
                $content = "[房间号:{$roomId}] [{$msg['from']['name']}]->赠送{$msg['count']}个{$msg['name']}";
                break;
        }
        if ($content) {
            Log::log($content);
            echo $content . PHP_EOL;
        }
    };
    $room->onConnect = function ($linkNum) use ($room) {
        $roomId = $room->getRoomId();
        $content = "成功连接到斗鱼房间号{$roomId},当前连接总数{$linkNum}";
        echo $content . PHP_EOL;
        Log::log($content, LOG::WARN);
    };
    $room->onError = function () {
        $content = "建立连接失败!!!!!!!!";
        echo $content . PHP_EOL;
        Log::log($content, LOG::ERROR);
    };
    $room->onClose = function ($linkNum, $roomID) {
        $content = "由于程序发生异常已关闭连接房间号{$roomID}!当前连接数{$linkNum}";
        echo $content . PHP_EOL;
        Log::log($content, LOG::ERROR);
    };
    $room->onError = function ($errorMsg, $roomID) {
        $content = "房间号{$roomID},{$errorMsg},无法建立连接";
        echo $content . PHP_EOL;
        Log::log($content, LOG::ERROR);
    };
}

while (true) {
    foreach ($roomObjArr as $room) {
        try {
            $room->join()->runSingleRead();
        } catch (\Exception $e) {
            echo($e->getMessage());
            Log::log($e->getMessage(), LOG::ERROR);
        }
    }
}


function getRoomIdArr()
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "http://open.douyucdn.cn/api/RoomApi/live",
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