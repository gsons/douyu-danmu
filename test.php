<?php
require 'DouYu.php';
require 'Message.php';
use Douyu\DouYu;
function shutdown_function()
{
}
set_error_handler('shutdown_function');
register_shutdown_function('shutdown_function');


$douYu = new DouYu('openbarrage.douyutv.com', 8601, 1767539);
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
            echo "[{$msg['from']['name']}]:{$msg['content']}";
            print PHP_EOL;
            break;
        case 'gift':
            echo "[{$msg['from']['name']}]->赠送{$msg['count']}个{$msg['name']}";
            print PHP_EOL;
            break;
        case 'yuwan':
            echo "[{$msg['from']['name']}]->赠送{$msg['count']}个{$msg['name']}";
            print PHP_EOL;
            break;
        case 'deserve':
            echo  "[{$msg['from']['name']}]->赠送{$msg['count']}个{$msg['name']}";
            print PHP_EOL;
            break;
    }
};
try {
    $douYu->startTcp();
} catch (\Exception $e) {
    exit($e->getMessage());
}
