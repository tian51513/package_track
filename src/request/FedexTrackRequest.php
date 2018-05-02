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

class FedexTrackRequest implements TrackRequest
{

    protected $carrierId = 100003;

    protected $carrierCode = 'FEDEX';

    protected $apiUrl = 'https://www.fedex.com/trackingCal/track';

    protected $method = 'post';

    protected $maxCount = 30;

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
    public function buildParams($params = [])
    {
        $item_data = [];
        foreach ($params as $track_code => $param) {
            $item = [
                'trackNumberInfo' => [
                    'trackingNumber'    => $param['track_code'],
                    'trackingQualifier' => '',
                    'trackingCarrier'   => '',
                ],
            ];
            $item_data[] = $item;
        }
        $obj                          = new \stdClass;
        $data['TrackPackagesRequest'] = ['appType' => 'WTRK', 'appDeviceType' => 'DESKTOP', 'supportHTML' => true, 'supportCurrentLocation' => true, 'uniqueKey' => '', 'trackingInfoList' => $item_data, 'processingParameters' => $obj];
        $query                        = ['data' => json_encode($data), 'action' => 'trackpackages', 'locale' => 'zh_CN', 'version' => 1, 'format' => 'json'];
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
        foreach ($response as $response_item) {
            $response_item = $response_item->getBody()->getContents();
            $response      = json_decode($response_item, true);
            if ($response && $response['TrackPackagesResponse']['successful']) {
                foreach ($response['TrackPackagesResponse']['packageList'] as $package) {
                    if ($package['isSuccessful']) {
                        $track_log = [];
                        $is_valid  = false;
                        foreach ($package['scanEventList'] as $log) {
                            $log['scanDetails'] = $log['scanDetails'] ? '-' . $log['scanDetails'] : '';
                            $track_log[]        = [
                                'remark' => $log['date'] . ' ' . $log['time'] . ' ' . $log['scanLocation'],
                                'event'  => $log['status'] . $log['scanDetails'],
                            ];
                            $is_valid = $is_valid || ConfigUtils::checkStrExist($log['scanDetails'], ConfigUtils::$carrierData[$this->carrierCode]['valid_str']);
                        }
                        $current_track  = current($track_log);
                        $invalid_status = ['托运资讯发送给FedEx', '已取件'];
                        $is_valid       = !in_array($current_track['event'], $invalid_status);
                        $is_over        = ConfigUtils::checkStrExist($current_track['event'], ConfigUtils::$carrierData[$this->carrierCode]['over_str']);
                        $trackData[]    = [
                            'track_code'   => $package['trackingNbr'],
                            'carrier_code' => $this->carrierCode,
                            'is_valid'     => $is_over ? true : $is_valid,
                            'is_over'      => $is_over,
                            'current_info' => $current_track['event'],
                            'track_log'    => $track_log,
                        ];
                        unset($trackParams[$package['trackingNbr']]);
                    }
                }
            }
        }
        call_user_func($callback, $trackData) === false;
    }
}
