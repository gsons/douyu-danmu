<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/23
 * Time: 14:16
 */

namespace DouYu;

class Room extends \Thread
{
    /**
     * TCP地址
     * @access private
     * @var string
     */
    private $address;

    /**
     * 端口
     * @access private
     * @var string
     */
    private $port;

    /**
     * socket对象
     * @access private
     * @var resource
     */
    private $socket;


    /**
     * 心跳周期
     * @var int
     */
    private $heartTime;

    /**
     * 上次发送心跳时间
     * @var int
     */
    private $sendHeartTime;

    /**
     * 连接成功回调
     * @access public
     * @var callable
     */
    public $onConnect;

    /**
     * 连接失败回调
     * @access public
     * @var callable
     */
    public $onError;

    /**
     * 接受消息回调
     * @access public
     * @var callable
     */
    public $onMessage;

    /**
     * 关闭连接回调
     * @access public
     * @var callable
     */
    public $onClose;

    /**
     * 房间号码
     * @access private
     * @var string
     */
    private $roomId;

    /**
     * 斗鱼礼物信息
     * @access private
     * @var string
     */
    private $giftInfo;


    /**
     * 连接总数
     * @access protected
     * @var int
     */
    protected static $linkNum = 0;

    /**
     * 心跳周期
     * @var int
     */
    private $isLinked;

    /**
     * 架构方法
     * DouYu constructor.
     * @param $address
     * @param $port
     * @param  $roomId
     * @param int $heartTime 默认45s;
     */
    public function __construct($address, $port, $roomId, $heartTime = 45)
    {
        $this->address = $address;
        $this->port = $port;
        $this->roomId = $roomId;
        $this->heartTime = $heartTime;
        $this->isLinked=false;
        $this->sendHeartTime = time();
    }

    public  function run()
    {
        while (true) {
            if (!$this->socket) break;
            if (time() - $this->sendHeartTime > $this->heartTime) {
                $this->sendHeartTime = time();
                $this->send(sprintf('type@=keeplive/tick@=%s/', $this->sendHeartTime));
            }
            $out = false;
            if (is_resource($this->socket)) $out = @socket_read($this->socket, 2048);
            if ($out !== false) {
                $this->parserChat($out);
                unset($out);
            } else {
                $this->throwSocketError("斗鱼房间号{$this->roomId},SOCKET 接收消息失败");
            }
        }
    }

    /**
     * 启动TCP 建立连接
     * @access public
     * @throws \Exception
     */
    public function joinIn()
    {
        if (is_resource($this->socket)) return $this;
        $this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->socket || $this->throwSocketError("加入斗鱼房间号{$this->roomId}时,建立SOCKET失败");
        $result = false;//连接结果
        if (is_resource($this->socket)) $result = @socket_connect($this->socket, $this->address, $this->port);
        $result && self::$linkNum++; $result && $this->isLinked=true;
        $result ? $this->onConnect && call_user_func($this->onConnect, self::$linkNum, $this->roomId) : $this->onError && call_user_func($this->onError, socket_strerror(socket_last_error()), $this->roomId);
        $result || $this->throwSocketError("加入斗鱼房间号{$this->roomId}时,SOCKET连接失败");
        //登录房间
        $this->send("type@=loginreq/roomid@={$this->roomId}/");
        //加入弹幕组
        $groupId = -9999;
        $this->send(sprintf('type@=joingroup/rid@=%s/gid@=%s/', $this->roomId, $groupId));
        return $this;
    }

    /**
     * 停止监听弹幕
     */
    public function stopTcp()
    {
        is_resource($this->socket) && @socket_close($this->socket);
        is_resource($this->socket) && $this->socket = null;
    }

    /**
     * 发送消息
     * @param  string $msg
     * @access private
     * @throws \Exception
     */
    public  function send($msg)
    {
        $message = new SocketMsg($msg);
        $byte = $message->getByte();
        $length = $message->getLength();
        $res = false;
        if (is_resource($this->socket)) $res = @socket_write($this->socket, $byte, $length);
        $res || $this->throwSocketError("斗鱼房间号{$this->roomId},SOCKET 发送消息失败");
    }


    /**
     * 自定义socket异常
     * @access private
     * @param $msg
     * @throws \Exception
     */
    public  function throwSocketError($msg)
    {
       
        call_user_func($this->onClose, self::$linkNum, $this->roomId);
        if(is_resource($this->socket)){
              @socket_close($this->socket);
              if($this->isLinked) self::$linkNum--;
              $this->isLinked=false;
        }
        $errMsg = @socket_strerror(@socket_last_error());
        throw new  \Exception("{$msg}:" . iconv('gbk//TRANSLIT', 'UTF-8', $errMsg));
    }

    /**
     * 启动循环读取
     * @access private
     * @throws \Exception
     */
    public function runLoopRead()
    {
        while (true) {
            if (!$this->socket) break;
            if (time() - $this->sendHeartTime > $this->heartTime) {
                $this->sendHeartTime = time();
                $this->send(sprintf('type@=keeplive/tick@=%s/', $this->sendHeartTime));
            }
            $out = false;
            if (is_resource($this->socket)) $out = socket_read($this->socket, 2048);
            if ($out !== false) {
                $this->parserChat($out);
            } else {
                $this->throwSocketError("斗鱼房间号{$this->roomId},SOCKET 接收消息失败");
            }
        }
    }

    /**
     * 单次读取socket
     * @access private
     * @throws \Exception
     */
    public function runSingleRead()
    {
        if (!$this->socket) return;
        if (time() - $this->sendHeartTime > $this->heartTime) {
            $this->sendHeartTime = time();
            $this->send(sprintf('type@=keeplive/tick@=%s/', $this->sendHeartTime));
        }
        $out = false;
        if (is_resource($this->socket)) $out = @socket_read($this->socket, 2048);
        if ($out !== false) {
            $this->parserChat($out);
        } else {
//            $this->socket=null;
            $this->throwSocketError("斗鱼房间号{$this->roomId},SOCKET 接收消息失败");
        }
    }

    /**
     * 解析弹幕信息
     * @param string $content 弹幕信息
     * @throws \Exception
     */
    public function parserChat($content)
    {
        preg_match_all('/(type@=.*?)\x00/', $content, $matches);
        foreach ($matches[1] as $vo) {
            $msg = preg_replace('/@=/', '":"', $vo);
            $msg = preg_replace('/\//', '","', $msg);
            $msg = substr($msg, 0, strlen($msg) - 3);
            $msg = '{"' . $msg . '"}';
            $obj = json_decode($msg, true);
            $msg_obj = false;
            switch ($obj['type']) {
                case 'chatmsg':
                    $msg_obj = $this->buildChat($obj);
                    break;
                case 'dgb':
                    $msg_obj = $this->buildGift($obj);
                    break;
                case 'bc_buy_deserve':
                    $msg_obj = $this->buildDeserve($obj);
                    break;
            }
            if($msg_obj) call_user_func($this->onMessage,$msg_obj,$this->getRoomId());
            unset($msg_obj);
        }

    }

    /**
     * 组装聊天消息数组
     * @access private
     * @param $msgArr
     * @return array
     */
    public  function buildChat($msgArr)
    {
        $plat = 'pc_web';
        if (isset($msgArr['ct']) && $msgArr['ct'] == '1') {
            $plat = 'android';
        } else if (isset($msgArr['ct']) && $msgArr['ct'] == '2') {
            $plat = 'ios';
        }
        return array(
            'type' => 'chat',
            'time' => time(),
            'id' => $msgArr['cid'],
            'content' => $msgArr['txt'],
            'from' => array(
                'name' => $msgArr['nn'],
                'rid' => $msgArr['uid'],
                'level' => $msgArr['level'],
                'plat' => $plat
            )
        );
    }

    /**
     *  组装礼物消息数组
     * @access private
     * @param $msgArr
     * @return mixed
     * @throws \Exception
     */
    public  function buildGift($msgArr)
    {
        $this->giftInfo = $this->giftInfo || $this->getGiftInfo();
        if (!$this->giftInfo) throw new  \Exception('获取礼物信息失败');
        $freeGift = array('name' => '鱼丸', 'price' => 0, 'is_yuwan' => false);
        $gift = isset($this->giftInfo[$msgArr['gfid']]) ? $this->giftInfo[$msgArr['gfid']] : $freeGift;
        $msg_obj = array(
            'type' => 'gift',
            'time' => time(),
            'name' => $gift['name'],
            'from' => array(
                'name' => $msgArr['nn'],
                //'rid' => $msgArr['uid'],
                'level' => $msgArr['level']
            ),
            // 'id' => `{$msgArr['uid']}{$msgArr['rid']}{$msgArr['gfid']}{$msgArr['hits']}{$msgArr['level']}`,
            'count' => $msgArr['gfcnt'] || 1,
            'price' => ($msgArr['gfcnt'] || 1) * $gift['price'],
            'earn' => ($msgArr['gfcnt'] || 1) * $gift['price']
        );
        if ($gift['is_yuwan']) {
            $msg_obj['type'] = 'yuwan';
            unset($msg_obj['price']);
            unset($msg_obj['earn']);
        }
        return $msg_obj;
    }

    /**
     *  组装酬勤消息数组
     * @access private
     * @param $msgArr
     * @return mixed
     */
    public  function buildDeserve($msgArr)
    {
        $name = '初级酬勤';
        $price = 15;
        if ($msgArr['lev'] === '2') {
            $name = '中级酬勤';
            $price = 30;
        } else if ($msgArr['lev'] === '3') {
            $name = '高级酬勤';
            $price = 50;
        }
        $sui = $msgArr['sui'];
        $sui = preg_replace('/@A=/g', '":"', $sui);
        $sui = preg_replace('/@S/g', '","', $sui);
        $sui = substr(0, strlen($sui) - 2, $sui);
        $sui = json_decode($sui, true);
        return array(
            'type' => 'deserve',
            'time' => time(),
            'name' => $name,
            'from' => array(
                'name' => $sui['nick'],
                //'rid' => $sui['id'],
                'level' => $sui['level'],
            ),
            //'id' => "{$sui['id']}{$msgArr['rid']}{$msgArr['lev']}{$msgArr['hits']}{$sui['level']}{$sui['exp']}",
            'count' => $msgArr['cnt'] || 1,
            'price' => $price,
            'earn' => $price
        );
    }

    public function getGiftInfo()
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://open.douyucdn.cn/api/RoomApi/room/{$this->roomId}",
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
            return false;
        } else {
            $info = json_decode($response, true);
            $res = array();
            foreach ($info['data']['gift'] as $v) {
                $res[$v['id']] = array('name' => $v['name'], 'price' => $v['pc'], 'is_yuwan' => $v['type'] == '1' ? true : false);
            }
            return $res;
        }
    }

    /**
     * @return string
     */
    public function getAddress()
    {
        return $this->address;
    }


    /**
     * @return string
     */
    public function getPort()
    {
        return $this->port;
    }


    /**
     * @return resource
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * @return int
     */
    public function getHeartTime()
    {
        return $this->heartTime;
    }


    /**
     * @return int
     */
    public function getSendHeartTime()
    {
        return $this->sendHeartTime;
    }


    /**
     * @return string
     */
    public function getRoomId()
    {
        return $this->roomId;
    }


}



