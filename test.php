<?php
require 'DouYu.php';
require 'Message.php';
use Douyu\DouYu;
function shutdown_function()
{
}
set_error_handler('shutdown_function');
register_shutdown_function('shutdown_function');


$douYu= new DouYu('openbarrage.douyutv.com', 8601,5670832);

$douYu->onConnect = function () {
    echo '连接成功!'.PHP_EOL;
};
$douYu->onError = function ($errMsg) {
    echo '连接失败!' . $errMsg.PHP_EOL;
};
$douYu->onClose = function () {
    echo '关闭连接'.PHP_EOL;
};
$douYu->onMessage = function ($msg) {
    switch ($msg['type']){
        case 'chat':
            $content="[{$msg['from']['name']}]:{$msg['content']}";
            break;
        case 'gift':
            $content="[{$msg['from']['name']}]->赠送{$msg['count']}个{$msg['name']}";
            break;
        case 'yuwan':
            $content= "[{$msg['from']['name']}]->赠送{$msg['count']}个{$msg['name']}";
            break;
        case 'deserve':
            $content="[{$msg['from']['name']}]->赠送{$msg['count']}个{$msg['name']}";
            break;
    }
    file_put_contents('log.txt',$content.PHP_EOL,FILE_APPEND);
    echo $content;
    // print_r($msg);
    print PHP_EOL;
};
try {
    $douYu->startTcp();
} catch (\Exception $e) {
    exit($e->getMessage());
}
