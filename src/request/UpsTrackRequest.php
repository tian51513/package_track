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
use Log;
class UpsTrackRequest implements TrackRequest
{

    protected $carrierId = '100002';

    protected $carrierCode = 'UPS';

    protected $apiUrl = 'https://www.ups.com/track/api/Track/GetStatus?loc=en_US';

    protected $method = 'post';

    protected $maxCount = 1;//25 只一个的时候完整详情

    public function __construct()
    {
        $this->client = new Client(['verify' => false, 'timeout' => 60, 'connect_timeout'=>60]);
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
     * @DateTime 2018-04-20T16:42:14+0800
     * @return   [type]                   [description]
     */
    public function buildParams($params = [])
    {
        $query     = [
            'Locale'       => 'en_US',//zh_CN
            'TrackingNumber' => array_column($params, 'track_code'),
        ];
        $headers = ['Content-Type' => 'application/json'];
        return ['json' => $query];
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
            $response_item = $page->getBody()->getContents();
            $response_item = json_decode($response_item, true);
            if($response_item['statusCode'] == 200 && !empty($response_item['trackDetails'])){
                foreach($response_item['trackDetails'] as $details){
                    if($details['errorCode'] !== null){
                        continue;
                    }
                    $track_code = $details['trackingNumber'];
                    if(!empty($details['shipmentProgressActivities'])){
                        $track_log = [];
                        $is_valid  = false;
                        foreach ($details['shipmentProgressActivities'] as $log) {
                            $track_log[] = [
                                'remark' => $log['date'] . $log['time'] . ' ' . $log['location'],
                                'event'  => $log['activityScan'],
                            ];
                            $is_valid = $is_valid || ConfigUtils::checkStrExist($log['activityScan'], ConfigUtils::$carrierData[$this->carrierCode]['valid_str']);
                        }
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
        }
        call_user_func($callback, $trackData) === false;
    }
}
