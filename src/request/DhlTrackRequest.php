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

class DhlTrackRequest implements TrackRequest
{

    protected $carrierId = 100044;

    protected $carrierCode = 'DHL';

    protected $apiUrl = 'http://www.cn.dhl.com/shipmentTracking';

    protected $method = 'get';

    protected $maxCount = 10;

    public function __construct()
    {
        $this->client = new Client(['verify' => false, 'timeout' => 60]);
    }

    /**
     * [buildParams 创建请求参数]
     * @Author   Tinsy
     * @DateTime 2018-04-20T16:42:14+0800
     * @return   [type]                   [description]
     */
    public function buildParams($params = [])
    {
        $code_strs             = implode(',', array_column($params, 'track_code'));
        $param['AWB']          = $code_strs;
        $param['languageCode'] = 'zh';
        $param['countryCode']  = 'cn';
        return [
            'verify' => false,
            'query'  => $param,
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
            if (isset($response_item['results'])) {
                foreach ($response_item['results'] as $item) {
                    if (!empty($item['checkpoints'])) {
                        $track_log = [];
                        $is_valid  = false;
                        foreach ($item['checkpoints'] as $log) {
                            $track_log[] = [
                                'remark' => $log['date'] . ' ' . $log['location'],
                                'event'  => $log['description'],
                            ];
                            $is_valid = $is_valid || ConfigUtils::checkStrExist($log['description'], ConfigUtils::$carrierData[$this->carrierCode]['valid_str']);
                        }
                        $current_track = current($track_log);
                        $is_over       = ConfigUtils::checkStrExist($current_track['event'], ConfigUtils::$carrierData[$this->carrierCode]['over_str']);
                        $trackData[]   = [
                            'track_code'   => $item['id'],
                            'carrier_code' => $this->carrierCode,
                            'is_valid'     => $is_over ? true : $is_valid,
                            'is_over'      => $is_over,
                            'current_info' => $current_track['event'],
                            'track_log'    => $track_log,
                        ];
                        unset($trackParams[$item['id']]);
                    }
                }
            }
        }
        call_user_func($callback, $trackData) === false;
    }
}
