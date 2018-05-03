<?php
/**
 *
 * @authors Rei (eva51513@gmail.com)
 * @date    2018-04-25 17:12:41
 * @version $Id$
 */
namespace track;

class ConfigUtils
{
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
        'EMS'         => ['api' => 'EmsTrackRequest', 'carrier_id' => '3013', 'valid_str' => '', 'over_str' => '', 'carrier_code' => 'EMS'],
        'FEDEX'       => ['api' => 'FedexTrackRequest', 'carrier_id' => '100003', 'valid_str' => 'Left FedEx origin facility', 'over_str' => '已送达', 'carrier_code' => 'FEDEX'],
        'P2P'         => ['api' => 'P2pTrackRequest', 'carrier_id' => false, 'valid_str' => 'TRAKPAK PROCESS CENTRE UK', 'over_str' => 'DELIVERED', 'carrier_code' => 'P2P'],
        'Parcelforce' => ['api' => 'ParcelforceTrackRequest', 'carrier_id' => '11033', 'valid_str' => ['Collected', 'On route to hub', 'Exported from the UK'], 'over_str' => 'Delivered', 'carrier_code' => 'Parcelforce'],
        'Royalmail'   => ['api' => 'RoyalmailTrackRequest', 'carrier_id' => false, 'valid_str' => '', 'over_str' => '', 'carrier_code' => 'Royalmail'],
        'TNT'         => ['api' => 'TntTrackRequest', 'carrier_id' => '100004', 'valid_str' => 'Shipment received at origin depot', 'over_str' => 'delivered', 'carrier_code' => 'TNT'],
        'TOLL'        => ['api' => 'TollTrackRequest', 'carrier_id' => '100009', 'valid_str' => 'SORTED TO CHUTE', 'over_str' => ['FREIGHT DELIVERED', 'POD AVAILABLE ONLINE', 'PAPER POD RECEIVED FOR IMAGING'], 'carrier_code' => 'TOLL'],
        'UPS'         => ['api' => 'UpsTrackRequest', 'carrier_id' => '100002', 'valid_str' => ['Departure Scan', 'Collection Scan', 'Pickup Scan'], 'over_str' => 'Delivered', 'carrier_code' => 'UPS'],
        'USPS'        => ['api' => 'UspsTrackRequest', 'carrier_id' => '21051', 'valid_str' => 'Accepted at USPS Origin Facilit', 'over_str' => 'Delivered', 'carrier_code' => 'USPS'],
        'Yodel'       => ['api' => 'YodelTrackRequest', 'carrier_id' => '100017', 'valid_str' => 'Your parcel is at our sort centre', 'over_str' => 'delivered', 'carrier_code' => 'Yodel'],
    ];

    /**
     * [log 日志]
     * @Author   Tinsy
     * @DateTime 2018-04-27T09:06:00+0800
     * @param    array                    $data [description]
     * @return   [type]                         [description]
     */
    public static function log($data = [], $msg = '')
    {
        $savepath = dirname(__FILE__) . '/error_' . date('Ymd') . '.log';
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
            $check_str  = strtolower($check_str);
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
                $flag = strpos($check_str, $search_str) !== false;
            } elseif (is_bool($search_str)) {
                $flag = $search_str;
            }
        }
        return $flag;
    }
}
