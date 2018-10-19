# douyu-danmu

douyu-danmu 是php版斗鱼直播弹幕监听模块。

参考:https://github.com/BacooTang/douyu-danmu

## 简单使用

通过如下代码，可以初步通过php对弹幕进行处理。

```php
require 'DouYu.php';
require 'Message.php';
use Douyu\DouYu;
function shutdown_function()
{
}
set_error_handler('shutdown_function');
register_shutdown_function('shutdown_function');


$douYu = new DouYu('openbarrage.douyutv.com', 8601,5670832);

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

```

## API

### 开始监听弹幕

```php
$douYu = new DouYu('openbarrage.douyutv.com', 8601,5670832);
try {
    $douYu->startTcp();
} catch (\Exception $e) {
    exit($e->getMessage());
}
```


#### chat消息
```javascript
    {
        type: 'chat',
        time: '毫秒时间戳(服务器无返回time,此处为本地收到消息时间),Number',
        from: {
            name: '发送者昵称,String',
            rid: '发送者rid,String',
            level: '发送者等级,Number',
            plat: '发送者平台(android,ios,pc_web,unknow),String'
        },
        id: '弹幕唯一id,String',
        content: '聊天内容,String'
    }
```

#### gift消息
```javascript
    {
        type: 'gift',
        time: '毫秒时间戳(服务器无返回time,此处为本地收到消息时间),Number',
        name: '礼物名称,String',
        from: {
            name: '发送者昵称,String',
            rid: '发送者rid,String',
            level: '发送者等级,Number'
        },
        id: '礼物唯一id,String',
        count: '礼物数量,Number',
        price: '礼物总价值(单位鱼翅),Number',
        earn: '礼物总价值(单位元),Number'
    }
```

#### yuwan消息
```javascript
    {
        type: 'yuwan',
        time: '毫秒时间戳(服务器无返回time,此处为本地收到消息时间),Number',
        name: '礼物名称,String',
        from: {
            name: '发送者昵称,String',
            rid: '发送者rid,String',
            level: '发送者等级,Number'
        },
        id: '礼物唯一id,String',
        count: '礼物数量,Number'
    }
```

#### deserve消息
```javascript
    {
        type: 'deserve',
        time: '毫秒时间戳(服务器无返回time,此处为本地收到消息时间),Number',
        name: '初级酬勤，中级酬勤，高级酬勤',
        from: {
            name: '发送者昵称,String',
            rid: '发送者rid,String',
            level: '发送者等级,Number'
        },
        id: '礼物唯一id,String',
        count: '酬勤数量,Number',
        price: '酬勤总价值(单位鱼翅),Number',
        earn: '酬勤总价值(单位元),Number'
    }
```