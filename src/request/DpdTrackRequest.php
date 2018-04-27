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
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use track\ConfigUtils;

class DpdTrackRequest implements TrackRequest
{

    protected $carrierId = 100007;

    protected $carrierCode = 'DPD';

    protected $apiUrl = 'https://tracking.dpd.de/cgi-bin/simpleTracking.cgi';

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
        $query['parcelNr'] = $param['track_code'];
        $query['locale']   = 'en_D2';
        $query['type']     = '1';
        return ['verify' => false, 'query' => $query, 'timeout' => 0];
    }
    /**
     * [request 接口请求]
     * @Author   Tinsy
     * @DateTime 2018-04-23T09:11:42+0800
     * @return   [type]                   [description]
     */
    public function request($params = [])
    {
        $promises = $results = [];
        foreach ($params as $param) {
            $promises[$param['track_code']] = $this->client->getAsync($this->apiUrl, $this->buildParams($param))->then(
                function (ResponseInterface $response) use (&$results, $param) {
                    $results[$param['track_code']] = $response;
                },
                function (RequestException $e) use ($param) {
                    ConfigUtils::log($param, $e->getMessage());
                }
            );
        }
        \GuzzleHttp\Promise\unwrap($promises);
        return $results;
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
        foreach ($response as $track_code => $response_item) {
            $response_item = $response_item->getBody()->getContents();
            $response_item = json_decode(trim(trim($response_item, '('), ')'), true);
            if (isset($response_item['TrackingStatusJSON'])) {
                $list = $response_item['TrackingStatusJSON']['statusInfos'] ?? [];
                if (!empty($list)) {
                    $track_log = [];
                    $is_valid  = false;
                    foreach ($list as $key => $log) {
                        $track_log['a' . $key] = [
                            'remark' => $log['date'] . ' ' . $log['time'] . ' ' . $log['city'],
                            'event'  => $log['contents'][0]['label'],
                        ];
                        $is_valid = $is_valid || strpos($log['contents'][0]['label'], ConfigUtils::$carrierData[$this->carrierCode]['valid_str']) !== false;
                    }
                    krsort($track_log);
                    $track_log     = array_values($track_log);
                    $current_track = current($track_log);
                    $is_over       = strpos($current_track['event'], ConfigUtils::$carrierData[$this->carrierCode]['over_str']) !== false;
                    $trackData[]   = [
                        'track_code'   => $track_code,
                        'carrier_code' => $this->carrierCode,
                        'is_valid'     => $is_valid,
                        'is_over'      => $is_over,
                        'current_info' => $current_track['event'],
                        'track_log'    => $track_log,
                    ];
                    unset($trackParams[$track_code]);
                }
            }
        }
    }
}
