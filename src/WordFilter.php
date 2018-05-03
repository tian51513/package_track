<?php
/**
 *
 * @authors Rei (eva51513@gmail.com)
 * @date    2016-03-28 11:16:08
 * @version $Id$
 * 过滤工具类
 */
namespace track;

class WordFilter
{

    private static $keyMap; //敏感词trie树
    private static $minMatchTYpe = 1; //最小匹配规则
    private static $maxMatchType = 2; //最大匹配规则
    private static $charArr      = array(); //待测文本字符数组信息集
    private static $sensIndex    = array(); //敏感词索引坐标
    public static $sensMap       = array(); //敏感词集
    private static $testedStr    = ''; //待测文本
    private static $breakChar    = array('*', '=', '+', '|', '?', '{', '}', '(', ')', '<', '>', '.', '_', '-'); //间隔符
    private static $passBreak    = false;
    /**
     * [__construct 初始化类]
     */
    public function __construct()
    {
    }
    /**
     * [initKeyIndex 初始化]
     * @Author   Tinsy
     * @DateTime 2018-05-03T11:55:34+0800
     * @param    string                   $check_str [description]
     * @param    array                    $key_data  [description]
     */
    public function initKeyIndex($check_str ='', $key_data=[]){
        //实例trie类
        $initWord        = new WordInit($key_data);
        self::$keyMap    = $initWord::$keyMap;
        self::$testedStr = $str;
    }
    /**
     * [setStr 更新待测字符串]
     * @param [type] $str [description]
     */
    public static function setStr($str)
    {
        self::$testedStr = $str;
        self::_getChars(true);
        self::$sensIndex = array();
        self::$sensMap   = array();
    }
    /**
     * [isContaint 判断文字是否包含敏感字符]
     * @param  [type]  $matchType [匹配规则 1：最小匹配 2：最大匹配]
     * @return boolean            [description]
     */
    public static function isContaint($str = '', $matchType = 1)
    {
        $flag    = false;
        $charArr = self::_getChars();
        $len     = count($charArr);
        for ($i = 0; $i < $len; $i++) {
            $matchFlag = self::_check($i, $matchType); //判断是否包含敏感字符
            if ($matchFlag > 0) {
                //大于0存在，返回true
                $flag = true;
            }
        }
        return $flag;
    }

    /**
     * [_getWordList 获取文字中的敏感词集合]
     * @param  [type] $matchType [匹配规则 1：最小匹配 2：最大匹配]
     * @param  [type] $rType     [返回类型 1-敏感词集合  2-敏感词索引坐标集]
     * @return [type]            [description]
     */
    private static function _getWordList($matchType = 1, $rType = 1)
    {
        if (!empty(self::$sensMap)) {
            return $rType === 1 ? self::$sensMap : self::$sensIndex;
        }
        $charArr = self::_getChars();
        $len     = count($charArr);
        for ($i = 0; $i < $len; $i++) {
            $length = self::_check($i, $matchType); //判断是否包含敏感字符
            if ($length > 0) {
                //存在,加入list中
                // echo $i.'-'.$length."\n";
                $word              = mb_substr(self::$testedStr, $i, $length, 'utf-8');
                self::$sensMap[]   = $word;
                self::$sensIndex[] = array('start' => $charArr[$i]['start'], 'end' => $charArr[$i + $length - 1]['end']);
                $i                 = $i + $length - 1; //减1的原因，是因为for会自增
            }
        }
        self::$sensMap = array_flip(array_flip(self::$sensMap));
        return $rType === 1 ? self::$sensMap : self::$sensIndex;
    }

    /**
     * [_check 检查文字中是否包含敏感字符]
     * @param  [type] $txt        [待检验文字]
     * @param  [type] $start      [起始位置]
     * @param  [type] $matchType  [匹配规则 1：最小匹配 2：最大匹配]
     * @return [type]             [description]
     */
    private static function _check($start = 0, $matchType = 1)
    {
        $flag      = false; //敏感词结束标识位：用于敏感词只有1位的情况
        $matchFlag = 0; //匹配标识数默认为0
        $word      = 0;
        $nowMap    = self::$keyMap;
        $charArr   = self::_getChars();
        // echo ord('A').'--'.ord('Z').'-'.ord('a').'-'.ord('z');exit;
        $len       = count($charArr);
        $prevIndex = false;
        for ($i = $start; $i < $len; $i++) {
            $word = $charArr[$i]['isLetter'] ? strtolower($charArr[$i]['val']) : $charArr[$i]['val']; //如果是英文則轉為小寫
            if (isset($nowMap[$word])) {
                //存在，则判断是否为最后一个
                $prevIndex = $prevIndex ? $prevIndex : $i - 1;
                $nowMap    = $nowMap[$word]; //获取指定key
                $matchFlag++; //找到相应key，匹配标识+1
                // echo $start.'--'.$word.'-'.$nowMap['isEnd']."\n";
                if ($nowMap['isEnd']) {
                    //如果为最后一个匹配规则,结束循环，返回匹配标识数
                    if ($charArr[$i]['isLetter'] && (($prevIndex >= 0 && $charArr[$prevIndex]['isLetter']) || ($i + 1 < $len && $charArr[$i + 1]['isLetter']))) {
                        $flag = false;
                        continue; //如果该匹配字符串上一个或下一个仍为字母则直接继续
                    }
                    $flag = true; //结束标志位为true
                    // echo $matchFlag."-".$charArr[$i]['val']."\n";
                    if (self::$minMatchTYpe == $matchType) {
                        //最小规则，直接返回,最大规则还需继续查找
                        break;
                    }
                }
            } else {

                //不存在，直接返回
                if (self::$passBreak && array_search($word, self::$breakChar) !== false) {
                    $matchFlag++;
                    continue;
                }
                break;
            }
        }
        if ($matchFlag < 2 || !$flag) {
            //长度必须大于等于1，为词
            $matchFlag = 0;
        }
        return $matchFlag;
    }
    /**
     * [getSensMap 获取敏感词集合]
     * @return [type] [description]
     */
    public static function getSensMap($matchType = 1)
    {
        return self::_getWordList($matchType);
    }
    public static function getKeyMap()
    {
        return self::$keyMap;
    }
    public static function passBreak($flag = false)
    {
        self::$passBreak = $flag;
    }
    /**
     * [replace 替换敏感字字符]
     * @param  [type]  $txt         [待检验文字]
     * @param  integer $matchType   [匹配规则 1：最小匹配 2：最大匹配]
     * @param  string  $replaceChar [替换字符]
     * @return [type]               [description]
     */
    public static function replace($matchType = 1, $replaceChar = '***')
    {
        // $wordList = self::_getWordList($matchType);     //获取文中所有的敏感词集合
        // return str_replace($wordList, $replaceChar, self::$testedStr);
        $replaceStr = self::$testedStr;
        $cIndex     = 0;
        $sensIndex  = empty(self::$sensIndex) ? self::_getWordList($matchType, 2) : self::$sensIndex;
        foreach ($sensIndex as $key => $index) {
            $replaceStr = substr_replace($replaceStr, $replaceChar, $index['start'] - $cIndex, $index['end'] - $index['start'] + 1);
            $cIndex += ($index['end'] - $index['start'] + 1) - strlen($replaceChar);
        }
        return $replaceStr;
    }
    /**
     * [_getChars 获取字符数组]
     * @return [type]           [description]
     */
    private static function _getChars($update = false)
    {
        if (!empty(self::$charArr) && !$update) {
            return self::$charArr;
        }
        $s   = trim(self::$testedStr);
        $len = strlen($s);
        if ($len === 0) {
            return array();
        }
        $key = 0;
        for ($i = 0; $i < $len; $i++) {
            $n                   = ord($s[$i]);
            self::$charArr[$key] = array('isLetter' => false, 'val' => '', 'start' => $i, 'end' => $i);
            if (($n >> 7) == 0) {
                self::$charArr[$key]['isLetter'] = ($n > 64 && $n < 91) || ($n > 96 && $n < 123) ? true : false;
                self::$charArr[$key]['val']      = $s[$i];
                // echo '字母：'.$n."--".base_convert($n, 10, 2)."\n";
            } else if (($n >> 4) == 15) {
                if ($i < $len - 3) {
                    self::$charArr[$key]['val'] = $s[$i] . $s[$i + 1] . $s[$i + 2] . $s[$i + 3];
                    self::$charArr[$key]['end'] = $i + 3;
                    $i += 3;
                }
                // echo '4字节非字母：'.$n."--".base_convert($n, 10, 2)."\n";
            } else if (($n >> 5) == 7) {
                if ($i < $len - 2) {
                    self::$charArr[$key]['val'] = $s[$i] . $s[$i + 1] . $s[$i + 2];
                    self::$charArr[$key]['end'] = $i + 2;
                    $i += 2;
                }
                // echo '3字节非字母：'.$n."--".base_convert($n, 10, 16)."\n";
            } else if (($n >> 6) == 3) {
                if ($i < $len - 1) {
                    self::$charArr[$key]['val'] = $s[$i] . $s[$i + 1];
                    self::$charArr[$key]['end'] = $i + 1;
                    $i += 1;
                }
                // echo '2字节非字母：'.$n."--".base_convert($n, 10, 2)."\n";
            }
            unset($n);
            $key++;
        }
        unset($s, $key);
        return self::$charArr;
    }
}
