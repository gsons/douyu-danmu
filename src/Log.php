<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/25/025
 * Time: 2:29
 */

namespace DouYu;


class Log
{
    const INFO = 'INFO';
    const ERROR = "ERROR";
    const WARN = "WARNING";
    public static $file='log.txt';

    public static function log($msg,$msgType=self::INFO)
    {
        $info='['.date('Y-m-d H:i:s').'] ';
        switch ($msgType) {
            case self::ERROR:
                $info.='ERROR:';
                break;
            case self::WARN:
                $info.='WARNING:';
                break;
            case self::INFO:
                $info.='INFO:';
                break;
            default:
                $info.='INFO:';
                break;
        }

        $info.=PHP_EOL;
        $info.=$msg;
        $info.=PHP_EOL;
        file_put_contents(self::$file,$info,FILE_APPEND);
    }
}

