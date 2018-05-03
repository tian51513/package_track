<?php
/**
 *
 * @authors Rei (eva51513@gmail.com)
 * @date    2016-03-28 11:15:40
 * @version $Id$
 * 初始化敏感词库，将敏感词加入到Redis中，构建DFA算法模型
 */
namespace track;

class WordInit
{
    /**
     * [$keyMap 敏感词库]
     * @var array
     */
    public static $keyMap = array();

    public function __construct($key_data = [])
    {
        self::_initKeyWord($key_data);
    }
    /**
     * [_initKeyWord 生成敏感词库]
     * @Author   Tinsy
     * @DateTime 2018-05-03T11:40:56+0800
     * @param    array                    $key_data [description]
     * @return   [type]                             [description]
     */
    private static function _initKeyWord($key_data = [])
    {
        $keySet = array();
        $keySet = array_flip(array_flip(array_filter($key_data)));
        self::_insert($keySet);
    }
    /**
     * [_insert 树形处理]
     * @Author   Tinsy
     * @DateTime 2018-05-03T11:42:05+0800
     * @param    [type]                   $keySet [description]
     * @return   [type]                           [description]
     */
    private static function _insert($keySet)
    {
        foreach ($keySet as $key) {
            $charArr = self::_getChars($key); //转换成char型
            $len     = count($charArr);
            $nowMap  = &self::$keyMap;
            foreach ($charArr as $ckey => $char) {
                // if (!array_key_exists($char, self::$keyMap)) {
                //     //不存在则，则构建一个map，同时将isEnd设置为0，因为他不是最后一个]
                //     $nowMap[$char] = array('isEnd' => 0); //不是最后一个
                // }
                if (!isset($nowMap[$char])) {
                    //不存在则，则构建一个map，同时将isEnd设置为0，因为他不是最后一个]
                    $nowMap[$char] = array('isEnd' => 0); //不是最后一个
                }

                if ($ckey == $len - 1) {
                    $nowMap[$char]['isEnd'] = 1; //最后一个
                }
                $nowMap = &$nowMap[$char];
            }
        }
    }
    /**
     * [_getChars 字符处理]
     * @Author   Tinsy
     * @DateTime 2018-05-03T11:41:39+0800
     * @param    [type]                   $utf8_str [description]
     * @return   [type]                             [description]
     */
    private static function _getChars($utf8_str)
    {
        $s   = trim($utf8_str);
        $len = strlen($s);
        if ($len === 0) {
            return array();
        }

        $chars = array();
        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            $n = ord($c);
            if (($n >> 7) == 0) {
                $chars[] = $c;
            } else if (($n >> 4) == 15) {
                if ($i < $len - 3) {
                    $chars[] = $c . $s[$i + 1] . $s[$i + 2] . $s[$i + 3];
                    $i += 3;
                }
            } else if (($n >> 5) == 7) {
                if ($i < $len - 2) {
                    $chars[] = $c . $s[$i + 1] . $s[$i + 2];
                    $i += 2;
                }
            } else if (($n >> 6) == 3) {
                if ($i < $len - 1) {
                    $chars[] = $c . $s[$i + 1];
                    $i += 1;
                }
            }
        }
        return $chars;
    }
}
