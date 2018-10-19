<?php
/**
 * 斗鱼弹幕
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/18
 * Time: 14:04
 */


namespace DouYu;

class DouYu
{
    /**
     * TCP地址
     * @access private
     * @var string
     */

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
     * 架构方法
     * DouYu constructor.
     * @param $address
     * @param $port
     * @param  $roomId
     */
    public function __construct($address, $port, $roomId)
    {
        $this->address = $address;
        $this->port = $port;
        $this->roomId = $roomId;
    }

    /**
     * 启动TCP 建立连接
     * @access public
     * @throws \Exception
     */
    public function startTcp()
    {
        $this->endTcp();
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->socket || $this->throwSocketError('建立SOCKET失败');
        $result = socket_connect($this->socket, $this->address, $this->port);
        $result ? call_user_func($this->onConnect) : call_user_func($this->onError, socket_strerror(socket_last_error()));
        $result || $this->throwSocketError('SOCKET连接失败');
        //登录房间
        $this->send("type@=loginreq/roomid@={$this->roomId}/");
        //加入弹幕组
        $groupId = -9999;
        $this->send(sprintf('type@=joingroup/rid@=%s/gid@=%s/', $this->roomId, $groupId));
        $this->readMsg();
    }

    /**
     * 停止监听弹幕
     */
    public function  endTcp(){
        $this->socket && socket_close($this->socket);
        $this->socket &&  $this->socket=null;
    }

    /**
     * 发送消息
     * @param  string $msg
     * @access private
     * @throws \Exception
     */
    private function send($msg)
    {
        $message = new Message($msg);
        $byte = $message->getByte();
        $length = $message->getLength();
        $res = socket_write($this->socket, $byte, $length);
        $res || $this->throwSocketError('SOCKET 发送消息失败');
    }


    /**
     * 自定义socket异常
     * @access private
     * @param $msg
     * @throws \Exception
     */
    private function throwSocketError($msg)
    {
        call_user_func($this->onClose);
        $this->socket && socket_close($this->socket);
        throw new  \Exception("{$msg}:" . socket_strerror(socket_last_error()));
    }

    /**
     * 读取消息
     * @access private
     * @throws \Exception
     */
    private function readMsg()
    {

        while (true) {
            if(!$this->socket) break;
            $out = socket_read($this->socket, 2048); //没有数据响应 这一行会一直阻塞
            $this->parserChat($out);
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
            $msg_obj && call_user_func($this->onMessage, $msg_obj);
        }

    }

    /**
     * 组装聊天消息数组
     * @access private
     * @param $msgArr
     * @return array
     */
    private function buildChat($msgArr)
    {
        $plat = 'pc_web';
        if ($msgArr['ct'] == '1') {
            $plat = 'android';
        } else if ($msgArr['ct'] == '2') {
            $plat = 'ios';
        }
        return [
            'type' => 'chat',
            'time' => time(),
            'id' => $msgArr['cid'],
            'content' => $msgArr['txt'],
            'from' => [
                'name' => $msgArr['nn'],
                'rid' => $msgArr['uid'],
                'level' => $msgArr['level'],
                'plat' => $plat
            ]
        ];
    }

    /**
     *  组装礼物消息数组
     * @access private
     * @param $msgArr
     * @return mixed
     * @throws \Exception
     */
    private function buildGift($msgArr)
    {
        $gift_info=$this->getGiftInfo();
        if(!$gift_info)  throw new  \Exception('获取礼物信息失败');
        $freeGift = ['name' => '鱼丸', 'price' => 0, 'is_yuwan' => false];
        $gift = isset($gift_info[$msgArr['gfid']])?$gift_info[$msgArr['gfid']]:$freeGift;
        $msg_obj = [
            'type' => 'gift',
            'time' => time(),
            'name' => $gift['name'],
            'from' => [
                'name' => $msgArr['nn'],
                'rid' => $msgArr['uid'],
                'level' => $msgArr['level']
            ],
            'id' => `{$msgArr['uid']}{$msgArr['rid']}{$msgArr['gfid']}{$msgArr['hits']}{$msgArr['level']}`,
            'count' => $msgArr['gfcnt'] || 1,
            'price' => ($msgArr['gfcnt'] || 1) * $gift['price'],
            'earn' => ($msgArr['gfcnt'] || 1) * $gift['price']
        ];
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
    private function buildDeserve($msgArr)
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
        return [
            'type' => 'deserve',
            'time' => time(),
            'name' => $name,
            'from' => [
                'name' => $sui['nick'],
                'rid' => $sui['id'],
                'level' => $sui['level'],
            ],
            'id' => "{$sui['id']}{$msgArr['rid']}{$msgArr['lev']}{$msgArr['hits']}{$sui['level']}{$sui['exp']}",
            'count' => $msgArr['cnt'] || 1,
            'price' => $price,
            'earn' => $price
        ];
    }

    public function getGiftInfo(){
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
            $info=json_decode($response,true);
            $res=[];
            foreach ($info['data']['gift'] as $v){
                $res[$v['id']]=['name'=>$v['name'], 'price'=>$v['pc'], 'is_yuwan'=>$v['type']== '1' ? true : false ];
            }
            return $res;
        }
    }
}



