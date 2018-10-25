<?php
namespace  DouYu;
/**
 * Socket消息类
 * User: Administrator
 * Date: 2018/10/20/020
 * Time: 21:02
 */


class SocketMsg
{
    /**
     * 正文包含五个部分：
     * 1. 数据长度，大小为后四部分的字节长度，占4个字节
     * 2. 内容和第一部分一样，4字节
     * 3. 斗鱼固定的请求码，本地->服务器是 0xb1,0x02,0x00,0x00，服务器->本地是0xb2,0x02,0x00,0x00，4字节消息内容
     * 4. 尾部一个空字节 0x00
     */
    private $msg_length;
    private $code;
    private $fixed_code;
    private $content;
    private $end;
    private $byte;
    private $length;

    public function __construct($content)
    {
        $messageLength = 4 + 4 + ($content == null ? 0 : strlen($content)) + 1;
        $this->msg_length = array($messageLength);
        $this->code = array($messageLength);
        $this->fixed_code = array(0x02b1);
        $this->content = $content;
        $this->end = array(0x00);
        foreach ($this->msg_length as $item) {
            $this->writeInt((int)$item);
        }
        foreach ($this->code as $item) {
            $this->writeInt((int)$item);
        }
        foreach ($this->fixed_code as $item) {
            $this->writeInt((int)$item);
        }
        $this->length += strlen($this->content);
        $this->byte .= $this->content;
        foreach ($this->end as $item) {
            $this->length += 1;
            $this->byte .= pack('L', $item);
        }
    }

    public function writeChar($string)
    {
        $this->length += strlen($string);
        $str = array_map('ord', str_split($string));
        foreach ($str as $vo) {
            $this->byte .= pack('c', $vo);
        }
        $this->byte .= pack('c', 0);
        $this->length++;
    }

    /**
     * write Int
     * @param $str
     */
    public function writeInt($str)
    {
        $this->length += 4;
        $this->byte .= pack('L', $str);
    }

    public function getByte()
    {
        return $this->byte;
    }

    public function getLength()
    {
        return $this->length;
    }
}