<?php
/**
 *
 * @authors Rei (eva51513@gmail.com)
 * @date    2018-04-20 16:29:27
 * @version $Id$
 * 默认物流接口 17track
 */
namespace track\request;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use Psr\Http\Message\ResponseInterface;
use track\ConfigUtils;

class DefaultTrackRequest implements TrackRequest
{

    protected $client;

    protected $apiUrl = 'https://t.17track.net/restapi/track';

    protected $method = 'post';

    protected $maxCount = 30;

    public function __construct()
    {
        $this->client = new Client(['verify' => false, 'timeout' => 60]);
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
                    return $this->client->postAsync($this->apiUrl, $this->buildParams($param));
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
     * [buildParams 创建请求参数]
     * @Author   Tinsy
     * @DateTime 2018-04-20T16:47:07+0800
     * @param    array                    $params [description]
     * @return   [type]                           [description]
     */
    public function buildParams($params = [])
    {
        $item_data = [];
        foreach ($params as $param) {
            $item                     = ['num' => $param['track_code']];
            $carrier_id               = ConfigUtils::$carrierData[$param['carrier_code']]['carrier_id'] ?? false;
            $carrier_id ? $item['fc'] = $carrier_id : true;
            $item_data[]              = $item;
        }
        return ['body' => json_encode(['guid' => '', 'data' => $item_data])];
    }
    /**
     * [getTrackData 获取物流信息]
     * @Author   Tinsy
     * @DateTime 2018-04-20T16:47:23+0800
     * @param    array                    $response [description]
     * @return   [type]                             [description]
     */
    public function getTrackData($response = [], &$trackParams = [], callable $callback)
    {
        $trackData    = [];
        $carrier_data = array_column(ConfigUtils::$carrierData, null, 'carrier_id');
        foreach ($response as $response_item) {
            $response_item = $response_item->getBody()->getContents();
            $response      = json_decode($response_item, true);
            if (!empty($response['dat'])) {
                foreach ($response['dat'] as $item) {
                    if ($item['delay'] == 0 && $item['track']) {
                        if ($item['track']['z0'] && $item['track']['z1']) {
                            $carrier   = $carrier_data[$item['track']['w1']];
                            $is_valid  = false;
                            $track_log = [];
                            foreach ($item['track']['z1'] as $log) {
                                $track_log[] = [
                                    'remark' => $log['a'] . ' ' . $log['c'],
                                    'event'  => $log['z'],
                                ];
                                $flag     = ConfigUtils::checkStrExist($log['z'], $carrier['valid_str']);
                                $is_valid = $is_valid || $flag;
                            }
                            $current_info = $item['track']['z0']['z'];
                            $is_over      = ConfigUtils::checkStrExist($current_info, $carrier['over_str']);
                            $trackData[]  = [
                                'track_code'   => $item['no'],
                                'carrier_code' => $carrier['carrier_code'],
                                'is_valid'     => $is_over ? true : $is_valid,
                                'is_over'      => $is_over,
                                'current_info' => $current_info,
                                'track_log'    => $track_log,
                            ];
                            unset($trackParams[$item['no']]);
                        } else {
                            $trackParams[$item['no']]['msg'] = $item['yt'];
                        }
                    }
                }
            }
        }
        call_user_func($callback, $trackData) === false;
    }
}
