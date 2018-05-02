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
use GuzzleHttp\Pool;
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
        $this->client = new Client(['verify' => false, 'timeout' => 60, 'debug' => false]);
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
        return ['query' => $query];
    }
    /**
     * [request 接口请求]
     * @Author   Tinsy
     * @DateTime 2018-04-23T09:11:42+0800
     * @return   [type]                   [description]
     */
    public function request($params = [])
    {
        $results  = [];
        $params   = array_values($params);
        $requests = function ($params) {
            $total = count($params);
            for ($i = 0; $i < $total; $i++) {
                $param = $params[$i];
                yield function () use ($param) {
                    return $this->client->getAsync($this->apiUrl, $this->buildParams($param));
                };
            }
        };
        $pool = new Pool($this->client, $requests($params), [
            'concurrency' => TrackRequest::ASYNC_MAX_NUM,
            'fulfilled'   => function (ResponseInterface $response, $index) use (&$results, $params) {
                $results[$params[$index]['track_code']] = $response;
            },
            'rejected'    => function (RequestException $e, $index) use ($params) {
                ConfigUtils::log([], '第' . $index . '个发生了错误');
                ConfigUtils::log($params[$index], $e->getMessage());
            },
        ]);
        // 开始发送请求
        $promise = $pool->promise();
        $promise->wait();
        return $results;
    }
    /**
     * [getTrackData 获取物流信息]
     * @Author   Tinsy
     * @DateTime 2018-04-20T16:44:02+0800
     * @param    array                    $response [description]
     * @return   [type]                             [description]
     */
    public function getTrackData($response = [], &$trackParams = [], callable $callback)
    {
        $trackData = [];
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
                        $is_valid = $is_valid || ConfigUtils::checkStrExist($log['contents'][0]['label'], ConfigUtils::$carrierData[$this->carrierCode]['valid_str']);
                    }
                    krsort($track_log);
                    $track_log     = array_values($track_log);
                    $current_track = current($track_log);
                    $is_over       = ConfigUtils::checkStrExist($current_track['event'], ConfigUtils::$carrierData[$this->carrierCode]['over_str']);
                    $trackData[]   = [
                        'track_code'   => $track_code,
                        'carrier_code' => $this->carrierCode,
                        'is_valid'     => $is_over ? true : $is_valid,
                        'is_over'      => $is_over,
                        'current_info' => $current_track['event'],
                        'track_log'    => $track_log,
                    ];
                    unset($trackParams[$track_code]);
                }
            }
        }
        call_user_func($callback, $trackData) === false;
    }
}
