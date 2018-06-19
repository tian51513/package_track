<?php
/**
 *
 * @authors Rei (eva51513@gmail.com)
 * @date    2018-04-20 17:17:04
 * @version $Id$
 */
namespace track\request;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use Psr\Http\Message\ResponseInterface;
use track\ConfigUtils;

class AupostTrackRequest implements TrackRequest
{

    protected $carrierId = '01151';

    protected $carrierCode = 'AuPost';

    protected $apiUrl = 'https://digitalapi.auspost.com.au/shipmentsgatewayapi/watchlist/shipments?';

    protected $method = 'get';

    protected $apiKey = 'd11f9456-11c3-456d-9f6d-f7449cb9af8e';

    protected $maxCount = 10;

    public function __construct()
    {
        $this->client = new Client(['verify' => false, 'timeout' => 60, 'connect_timeout'=>60]);
    }

    /**
     * [buildParams 创建请求参数]
     * @Author   Tinsy
     * @DateTime 2018-04-20T16:42:14+0800
     * @return   [type]                   [description]
     */
    public function buildParams($params = [])
    {
        $code_strs = implode(',', array_column($params, 'track_code'));
        return [
            'verify'  => false,
            'headers' => ['api-key' => $this->apiKey],
            'query'   => ['trackingIds' => $code_strs],
        ];
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
        $params   = array_chunk($params, $this->maxCount);
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
                $results[] = $response;
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
        foreach ($response as $response_item) {
            $response_item = $response_item->getBody()->getContents();
            $response_item = json_decode($response_item, true);
            foreach ($response_item as $item) {
                if ($item['status'] === 200) {
                    $events = $item['shipment']['articles'][0]['details'][0]['events'] ?? false;
                    if ($item['shipment']['status'] === 'Success' && $events) {
                        $track_log = [];
                        $is_valid  = false;
                        foreach ($events as $log) {
                            $track_log[] = [
                                'remark' => $log['localeDateTime'] . ' ' . $log['location'],
                                'event'  => $log['description'],
                            ];
                            $is_valid = $is_valid || ConfigUtils::checkStrExist($log['description'], ConfigUtils::$carrierData[$this->carrierCode]['valid_str']);
                        }
                        $current_track = current($track_log);
                        $is_over       = ConfigUtils::checkStrExist($current_track['event'], ConfigUtils::$carrierData[$this->carrierCode]['over_str']);
                        $trackData[]   = [
                            'track_code'   => $item['trackingIds'][0],
                            'carrier_code' => $this->carrierCode,
                            'is_valid'     => $is_over ? true : $is_valid,
                            'is_over'      => $is_over,
                            'current_info' => $current_track['event'],
                            'track_log'    => $track_log,
                        ];
                        unset($trackParams[$item['trackingIds'][0]]);
                    }
                }
            }
        }
        call_user_func($callback, $trackData) === false;
    }
}
