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
use QL\QueryList;
use track\ConfigUtils;

class YodelTrackRequest implements TrackRequest
{

    protected $carrierId = '100017';

    protected $carrierCode = 'Yodel';

    protected $apiUrl = 'http://yodel.co.uk/tracking/';

    protected $method = 'get';

    protected $maxCount = 1;

    public function __construct()
    {
        $this->client = new Client(['verify' => false, 'allow_redirects' => false, 'timeout' => 60]);
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
                    return $this->client->getAsync($this->apiUrl . '/' . $param['track_code']);
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
     * [buildParams 创建请求参数]
     * @Author   Tinsy
     * @DateTime 2018-04-20T16:42:14+0800
     * @return   [type]                   [description]
     */
    public function buildParams($params = [])
    {

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
        foreach ($response as $track_code => $page) {
            $html = $page->getBody()->getContents();
            $reg  = [
                'track_data'     => ['.tracking-history', 'html'],
                'package_status' => ['.sub-header:first', 'text'],
            ];
            QueryList::html($html)->rules($reg)->query()->getData(function ($container) use (&$trackData, &$trackParams, $track_code) {
                if (isset($container['track_data'])) {
                    $reg = [
                        'date'     => ['.datetime', 'text'],
                        'location' => ['.location', 'text'],
                        'event'    => ['.description', 'text'],
                    ];
                    $is_valid                = false;
                    $container['track_data'] = QueryList::html($container['track_data'])->rules($reg)->query()->getData(function ($item) use (&$is_valid) {
                        $item = [
                            'remark' => $item['date'] . ' ' . $item['location'],
                            'event'  => $item['event'],
                        ];
                        $is_valid = $is_valid || ConfigUtils::checkStrExist($item['event'], ConfigUtils::$carrierData[$this->carrierCode]['valid_str']);
                        return $item;
                    })->toArray();
                    $current_track = current($container['track_data']);
                    $is_over       = ConfigUtils::checkStrExist($current_track['event'], ConfigUtils::$carrierData[$this->carrierCode]['over_str']);
                    $trackData[]   = [
                        'track_code'   => $track_code,
                        'carrier_code' => $this->carrierCode,
                        'is_valid'     => $is_over ? true : $is_valid,
                        'is_over'      => $is_over,
                        'current_info' => $current_track['event'],
                        'track_log'    => $container['track_data'],
                    ];
                    unset($trackParams[$track_code]);
                }
            });
        }
        call_user_func($callback, $trackData) === false;
    }
}
