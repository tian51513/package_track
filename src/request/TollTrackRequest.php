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

class TollTrackRequest implements TrackRequest
{

    protected $carrierId = 100009;

    protected $carrierCode = 'TOLL';

    protected $apiUrl = 'https://online.toll.com.au/v1/trackAndTrace/searchConsignments';

    protected $method = 'post';

    protected $maxCount = 10;

    public function __construct()
    {
        $this->client = new Client(['verify' => false, 'timeout' => 60, 'connect_timeout' => 60]);
    }

    /**
     * [buildParams 创建请求参数]
     * @Author   Tinsy
     * @DateTime 2018-04-20T16:42:14+0800
     * @return   [type]                   [description]
     */
    public function buildParams($params = [])
    {
        $item_data = [];
        $code_strs = implode(',', array_column($params, 'track_code'));
        $query     = [
            'connoteIds' => $code_strs,
        ];
        return ['json' => $query];
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
            $response_item = json_decode($response_item, true);
            if ($response_item['totalConnotes']) {
                foreach ($response_item['tatConnotes'] as $item) {
                    $track_log = [];
                    $is_valid  = false;
                    if (isset($item['consignmentEvents'])) {
                        foreach ($item['consignmentEvents'] as $log) {
                            $track_log[] = [
                                'remark' => $log['eventDateTime'] . ' ' . $log['location'],
                                'event'  => $log['eventDescription'],
                            ];
                            $is_valid = $is_valid || ConfigUtils::checkStrExist($log['eventDescription'], ConfigUtils::$carrierData[$this->carrierCode]['valid_str']);
                        }
                        $complete_status = ConfigUtils::$carrierData[$this->carrierCode]['over_str'];
                        $is_valid        = $item['lastEventStatus'] === 'CONNOTE FILE LODGED (E-TRADER)' ? false : $is_valid;
                        $is_over         = ConfigUtils::checkStrExist($item['lastEventStatus'], $complete_status);
                        $track_info      = [
                            'current_info' => $item['lastEventStatus'],
                            'is_valid'     => $is_over ? true : $is_valid,
                            'is_over'      => $is_over,
                            'track_code'   => $item['connote'],
                            'carrier_code' => $this->carrierCode,
                            'track_log'    => $track_log,
                        ];
                        $trackData[] = $track_info;
                        unset($trackParams[$item['connote']]);
                    }
                }
            }
        }
        call_user_func($callback, $trackData) === false;
    }
}
