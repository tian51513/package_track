<?php
/**
 *
 * @authors Rei (eva51513@gmail.com)
 * @date    2018-04-20 17:17:04
 * @version $Id$
 * track_code 为数字
 */
namespace track\request;

use GuzzleHttp\Client;
use track\ConfigUtils;

class RoyalmailTrackRequest implements TrackRequest
{

    protected $carrierId = '';

    protected $carrierCode = 'Royalmail';

    protected $preUrl = 'https://www.royalmail.com';

    protected $apiUrl = 'https://api.royalmail.net/mailpieces/v2/%s/events';

    protected $method = 'get';

    protected $maxCount = 1;

    public function __construct()
    {
        $this->client = new Client(['verify' => false]);
    }

    /**
     * [buildParams 创建请求参数]
     * @Author   Tinsy
     * @DateTime 2018-04-20T16:42:14+0800
     * @return   [type]                   [description]
     */
    public function buildParams($param = [])
    {
        // $cookie_file = dirname(__FILE__).'/cookie.txt';
        // //$cookie_file = tempnam("tmp","cookie");
        // $this->apiUrl = sprintf($this->apiUrl, $param['track_code']);
        // //先获取cookies并保存
        // $ch = curl_init($this->apiUrl); //初始化
        // curl_setopt($ch, CURLOPT_HEADER, 0); //不返回header部分
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //返回字符串，而非直接输出
        // curl_setopt($ch, CURLOPT_COOKIEJAR,  $cookie_file); //存储cookies
        // curl_exec($ch);
        // curl_close($ch);
        // echo 'over';
        // exit;
        // $jar = new \GuzzleHttp\Cookie\CookieJar();
        // $response = $this->client->get($this->preUrl, ['cookies'=>$jar]);
        // foreach ($response->getHeaders() as $name => $values) {
        //     echo $name . ': ' . implode(', ', $values) . "\r\n";
        // }
        // exit;
        // TODO
    }
    /**
     * [request 接口请求]
     * @Author   Tinsy
     * @DateTime 2018-04-23T09:11:42+0800
     * @return   [type]                   [description]
     */
    public function request($params = [])
    {
        // $promises = [];
        // foreach ($params as $param) {
        //     $promises[$param['track_code']] = $this->client->getAsync($this->apiUrl, $this->buildParams($param));
        // }
        // $results = \GuzzleHttp\Promise\unwrap($promises);
        // return $results;
        // TODO
    }
    /**
     * [getTrackData 获取物流信息]
     * @Author   Tinsy
     * @DateTime 2018-04-20T16:44:02+0800
     * @param    array                    $response [description]
     * @return   [type]                             [description]
     */
    public function getTrackData($response = [], &$trackData = [], &$trackParams = [])
    {
        // foreach ($response as $track_code => $response_item) {
        //     $response_item = $response_item->getBody()->getContents();
        //     $response_item = json_decode($response_item, true);
        //     if (isset($response_item['tracker.output']['consignment'])) {
        //         $track     = $response_item['tracker.output']['consignment'][0];
        //         $list      = $track['statusData'] ?? [];
        //         $track_log = [];
        //         $is_valid  = false;
        //         foreach ($list as $log) {
        //             $track_log[] = [
        //                 'remark' => $log['localEventDate'] . ' ' . $log['depot'],
        //                 'event'  => $log['statusDescription'],
        //             ];
        //             $is_valid = $is_valid || strpos($log['statusDescription'], ConfigUtils::$carrierData['tnt']['valid_str']) !== false;
        //         }
        //         $current_track = current($track_log);
        //         $is_over       = strpos($current_track['event'], ConfigUtils::$carrierData['tnt']['over_str']) !== false;
        //         $trackData[]   = [
        //             'track_code'   => $track_code,
        //             'carrier_id'   => $this->carrierId,
        //             'is_valid'     => $is_valid,
        //             'is_over'      => $is_over,
        //             'current_info' => $current_track['event'],
        //             'track_log'    => $track_log,
        //         ];
        //         unset($trackParams[$track_code]);
        //     }
        // }
        // TODO
    }
}
