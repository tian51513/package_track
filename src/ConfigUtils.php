<?php
/**
 *
 * @authors Rei (eva51513@gmail.com)
 * @date    2018-04-25 17:12:41
 * @version $Id$
 */
namespace track;

use Doctrine\Common\Cache\PhpFileCache;

class ConfigUtils
{
    /**
     * [$logPath 日志路径]
     * @var string
     */
    public static $logPath = './track_log/';
    /**
     * [$carrierData 运输商对应数据]
     * api-接口文件 carrier_id-17track运输商ID valid_str-有效对应str部分 over_str-完成对应str部分 carrier_code-运输商代号
     * @var [type]
     */
    public static $carrierData = [
        'AuPost'      => ['api' => 'AupostTrackRequest', 'carrier_id' => '1151', 'valid_str' => 'Shipping information approved by Australia Post', 'over_str' => 'Delivered', 'carrier_code' => 'AuPost'],
        'DePost'      => ['api' => 'DepostTrackRequest', 'carrier_id' => false, 'valid_str' => true, 'over_str' => ['zugestellt', 'delivered '], 'carrier_code' => 'DePost'],
        'DHL'         => ['api' => 'DhlTrackRequest', 'carrier_id' => '100001', 'valid_str' => ['DHL已取件', 'Shipment picked up'], 'over_str' => ['已派送-签收人', 'Delivered'], 'carrier_code' => 'DHL'],
        'DHL(DE)'     => ['api' => 'DhldeTrackRequest', 'carrier_id' => '7041', 'valid_str' => ['The shipment has been processed in the parcel center', 'The shipment has arrived in the destination country'], 'over_str' => 'delivered', 'carrier_code' => 'DHL(DE)'],
        'DPD'         => ['api' => 'DpdTrackRequest', 'carrier_id' => '100007', 'valid_str' => 'In transit', 'over_str' => ['Delivered', 'Picked up from DPD ParcelShop by consignee'], 'carrier_code' => 'DPD'],
        'DPD(UK)'     => ['api' => 'DpdukTrackRequest', 'carrier_id' => '100010', 'valid_str' => 'on its way to our depot', 'over_str' => 'delivered', 'carrier_code' => 'DPD(UK)'],
        'EMS'         => ['api' => 'EmsTrackRequest', 'carrier_id' => '3013', 'valid_str' => 'Parcel collected', 'over_str' => 'delivered', 'carrier_code' => 'EMS'],
        'FEDEX'       => ['api' => 'FedexTrackRequest', 'carrier_id' => '100003', 'valid_str' => 'Left FedEx origin facility', 'over_str' => ['已送达', 'Delivered'], 'carrier_code' => 'FEDEX'],
        'P2P'         => ['api' => 'P2pTrackRequest', 'carrier_id' => false, 'valid_str' => 'TRAKPAK PROCESS CENTRE UK', 'over_str' => 'DELIVERED', 'carrier_code' => 'P2P'],
        'Parcelforce' => ['api' => 'ParcelforceTrackRequest', 'carrier_id' => '11033', 'valid_str' => ['Collected', 'On route to hub', 'Exported from the UK'], 'over_str' => 'Delivered', 'carrier_code' => 'Parcelforce'],
        'Royalmail'   => ['api' => 'RoyalmailTrackRequest', 'carrier_id' => false, 'valid_str' => ['Arrived at sorting center', 'Item Received'], 'over_str' => 'Delivered', 'carrier_code' => 'Royalmail'],
        'TNT'         => ['api' => 'TntTrackRequest', 'carrier_id' => '100004', 'valid_str' => 'Shipment received at origin depot', 'over_str' => 'delivered', 'carrier_code' => 'TNT'],
        'TOLL'        => ['api' => 'TollTrackRequest', 'carrier_id' => '100009', 'valid_str' => 'SORTED TO CHUTE', 'over_str' => ['FREIGHT DELIVERED', 'POD AVAILABLE ONLINE', 'PAPER POD RECEIVED FOR IMAGING', 'Delivery'], 'carrier_code' => 'TOLL'],
        'UPS'         => ['api' => 'UpsTrackRequest', 'carrier_id' => '100002', 'valid_str' => ['Departure Scan', 'Collection Scan', 'Pickup Scan'], 'over_str' => 'Delivered', 'carrier_code' => 'UPS'],
        'USPS'        => ['api' => 'UspsTrackRequest', 'carrier_id' => '21051', 'valid_str' => 'Accepted at USPS Origin Facilit', 'over_str' => 'Delivered', 'carrier_code' => 'USPS'],
        'Yodel'       => ['api' => 'YodelTrackRequest', 'carrier_id' => '100017', 'valid_str' => 'Your parcel is at our sort centre', 'over_str' => 'delivered', 'carrier_code' => 'Yodel'],
    ];
    /**
     * [$validEvent 有效事件]
     * @var [type]
     */
    public static $validEvent = [
        'shipping information approved by australia post',
        'dhl已取件',
        'shipment picked up',
        'the shipment has been processed in the parcel center',
        'the shipment has arrived in the destination country',
        'in transit',
        'on its way to our depot',
        'parcel collected',
        'left fedex origin facility',
        'trakpak process centre uk',
        'collected',
        'on route to hub',
        'arrived at sorting center',
        'item received',
        'shipment received at origin depot',
        'sorted to chute',
        'departure scan',
        'collection scan',
        'pickup Scan',
        'accepted at usps origin facilit',
        'your parcel is at our sort centre',
    ];
    /**
     * [$completeEvent 完成事件]
     * @var [type]
     */
    public static $completeEvent = [
        'delivered',
        'zugestellt',
        '已派送-签收人',
        'picked up from dpd parcelShop by consignee',
        '已送达',
        'pod available online',
        'paper pod received for imaging',
    ];
    /**
     * [$exceptionMsg 异常信息 1-客人不在2-地址问题3-需自提4-退回5-清关6-待付关税7-客人拒收]
     * @var [type]
     */
    protected $exceptionMsg = [
        'not home'                 => 1,
        'not there'                => 1,
        'not located'              => 1,
        'not available'            => 1,
        'not present'              => 1,
        'business closed'          => 1,
        'company closed'           => 1,
        'driver left card'         => 1,
        'address not correct'      => 2,
        'consignee address'        => 2,
        'address error'            => 2,
        'address information'      => 2,
        'address error'            => 2,
        'awaiting collection'      => 3,
        'ready for pick up'        => 3,
        'pickup parcelshop'        => 3,
        'available for collection' => 3,
        'held for collection'      => 3,
        'awaiting customer pickup' => 3,
        'returned'                 => 4,
        'customs clearance'        => 5,
        'clearance instructions'   => 5,
        'broker'                   => 5,
        'payment'                  => 6,
        'duties '                  => 6,
        'taxes'                    => 6,
        'charges'                  => 6,
        'refused'                  => 7,
    ];
    /**
     * [log 日志]
     * @Author   Tinsy
     * @DateTime 2018-04-27T09:06:00+0800
     * @param    array                    $data [description]
     * @return   [type]                         [description]
     */
    public static function log($data = [], $msg = '', $level = '1')
    {
        self::clearLog();
        $level_config = [
            '1' => 'error',
            '2' => 'notice',
            '3' => 'warn',
        ];
        $prefix   = $level_config[$level] ?? 'error';
        $savepath = self::$logPath . $prefix . '_' . date('Ymd') . '.log';
        $now      = date('Y-m-d H:i:s');
        $log      = json_encode($data, JSON_UNESCAPED_UNICODE);
        error_log("[{$now}] " . '---' . $msg . "\r\n\r\n{$log}\r\n\r\n", 3, $savepath);
    }
    /**
     * [checkStrExist 判断字符串是否存在]
     * @Author   Tinsy
     * @DateTime 2018-04-27T13:50:45+0800
     * @param    [type]                   $check_str  [待检测字符串]
     * @param    [type]                   $search_str [匹配数据]
     * @return   [type]                               [description]
     */
    public static function checkStrExist($check_str, $search_str)
    {
        $flag = false;
        if ($check_str && $search_str) {
            $check_str = strtolower($check_str);
            if (is_array($search_str)) {
                foreach ($search_str as $str) {
                    $str = strtolower($str);
                    if (strpos($check_str, $str) !== false) {
                        $flag = true;
                        break;
                    }
                }
            } elseif (is_string($search_str)) {
                $search_str = strtolower($search_str);
                $flag       = strpos($check_str, $search_str) !== false;
            } elseif (is_bool($search_str)) {
                $flag = $search_str;
            }
        }
        return $flag;
    }
    /**
     * [clearLog 清除过期日志]
     * @Author   Tinsy
     * @DateTime 2018-05-30T09:50:33+0800
     * @param    integer                  $expire_month [description]
     * @return   [type]                                 [description]
     */
    public static function clearLog($expire_month = 1)
    {
        if (!is_dir(self::$logPath)) {
            mkdir(self::$logPath, 0755, true);
        }
        $handle = @opendir(self::$logPath);
        $now    = time();
        while (false !== ($file_path = readdir($handle))) {
            if ($file_path != '.' && $file_path != '..') {
                $file_path   = self::$logPath . $file_path;
                $update_time = filemtime($file_path);
                clearstatcache();
                if ($update_time < mktime(0, 0, 0, date('m', $now) - $expire_month, date('d', $now), date('Y', $now))) {
                    unlink($file_path);
                }
            }
        }
        closedir($handle);
    }
    /**
     * [cache 缓存]
     * @Author   Tinsy
     * @DateTime 2018-05-30T17:11:47+0800
     * @param    [type]                   $key    [description]
     * @param    string                   $value  [description]
     * @param    integer                  $expire [description]
     * @return   [type]                           [description]
     */
    public static function cache($key, $value = '', $expire = 0)
    {
        $cache  = new PhpFileCache(self::$logPath);
        $result = true;
        if (!is_null($key)) {
            if ($value === '') {
                /*
                搜索
                 */
                $result = $cache->fetch($key);
            } elseif (is_null($value)) {
                /*
                删除
                 */
                $result = $cache->delete($key);
            } else {
                /*
                设置
                 */
                $result = $cache->save($key, $value, $expire);
            }
        }
        return $result;
    }
    /**
     * [setLogPath description]
     * @Author   Tinsy
     * @DateTime 2018-06-20T16:09:49+0800
     * @param    [type]                   $log_path [description]
     */
    public static function setLogPath($log_path)
    {
        self::$logPath = $log_path;
    }
    /**
     * [setCarrierData description]
     * @Author   Tinsy
     * @DateTime 2018-07-16T15:04:30+0800
     * @param    [type]                   $carrier_code [description]
     * @param    [type]                   $carrier_data [description]
     */
    public static function setCarrierData($carrier_code, $key, $value)
    {
        self::$carrierData[$carrier_code][$key] = $value;
    }
    /**
     * [setExceptionMsg description]
     * @Author   Tinsy
     * @DateTime 2018-07-17T09:36:31+0800
     * @param    [type]                   $msg  [description]
     * @param    [type]                   $code [description]
     */
    public static function setExceptionMsg($msg, $code)
    {
        self::$exceptionMsg[$msg] = $code;
    }
    /**
     * [setValidEvent description]
     * @Author   Tinsy
     * @DateTime 2018-07-17T09:52:39+0800
     * @param    [type]                   $event [description]
     */
    public static function setValidEvent($event)
    {
        self::$validEvent[] = $event;
    }
    /**
     * [setCompleteEvent description]
     * @Author   Tinsy
     * @DateTime 2018-07-17T09:53:17+0800
     * @param    [type]                   $event [description]
     */
    public static function setCompleteEvent($event)
    {
        self::$completeEvent[] = $event;
    }

    /**
     * [jsonReg 匹配json字符串]
     * @Author   Tinsy
     * @DateTime 2018-08-02T16:55:21+0800
     * @param    string                   $str [description]
     * @return   [type]                        [description]
     */
    public static function jsonReg($str = '', &$match = [])
    {
        //基础元素
        $r_int   = '-?\d+'; //整数: 100, -23
        $r_blank = '\s*'; //空白
        $r_obj_l = '\\{' . $r_blank; // {
        $r_obj_r = $r_blank . '\\}'; // }
        $r_arr_l = '\\[' . $r_blank; // [
        $r_arr_r = $r_blank . '\\]'; // [
        $r_comma = $r_blank . ',' . $r_blank; //逗号
        $r_colon = $r_blank . ':' . $r_blank; //冒号

        //基础数据类型
        $r_str  = '"(?:\\\\"|[^"])+"'; //双引号字符串
        $r_num  = "{$r_int}(?:\\.{$r_int})?(?:[eE]{$r_int})?"; //数字(整数,小数,科学计数): 100,-23; 12.12,-2.3; 2e9,1.2E-8
        $r_bool = '(?:true|false)'; //bool值
        $r_null = 'null'; //null

        //衍生类型
        $r_key = $r_str; //json中的key
        $r_val = "(?:(?P>json)|{$r_str}|{$r_num}|{$r_bool}|{$r_null})"; //json中val: 可能为 json对象,字符串,num, bool,null
        $r_kv  = "{$r_key}{$r_colon}{$r_val}"; //json中的一个kv结构

        $r_arr = "{$r_arr_l}{$r_val}(?:{$r_comma}{$r_val})*{$r_arr_r}"; //数组: 由val列表组成
        $r_obj = "{$r_obj_l}{$r_kv}(?:{$r_comma}{$r_kv})*{$r_obj_r}"; //对象: 有kv结构组成
        $reg   = "/(?<json>(?:{$r_obj}|{$r_arr}))/is"; //数组或对象
        return preg_match($reg, $str, $match);
    }
}
