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

class DpdukTrackRequest implements TrackRequest
{

    protected $carrierId = 100007;

    protected $carrierCode = 'DPD(UK)';

    protected $preApiUrl = 'http://www.dpd.co.uk/esg/shipping/shipment/_/parcel/?';

    protected $apiUrl = 'http://www.dpd.co.uk/esg/shipping/shipment/_/parcel/%s/event';

    protected $method = 'get';

    protected $maxCount = 1;

    public function __construct()
    {
        $this->client = new Client(['highlander' => true, 'timeout' => 60]);
    }

    /**
     * [buildParams 创建请求参数]
     * @Author   Tinsy
     * @DateTime 2018-04-20T16:42:14+0800
     * @return   [type]                   [description]
     */
    public function buildParams($param = [])
    {

        $query     = [];
        $query_pre = [
            'filter'         => 'Id',
            'searchCriteria' => 'deliveryReference=' . $param['track_code'],
        ];
        $response_pre = $this->client->get($this->preApiUrl . http_build_query($query_pre), [
            'headers' => [
                'highlander' => 'true',
            ],
        ]);
        $response_pre = $response_pre->getBody()->getContents();
        if ($response_pre) {
            $response_pre = json_decode($response_pre, true);
            if (!empty($response_pre['data']['parcel'])) {
                $parcel           = $response_pre['data']['parcel'][0]['parcelCode'];
                $tracking_session = $response_pre['data']['trackingSession'];
                $api_url          = sprintf($this->apiUrl, $parcel);
                $header           = [
                    'highlander' => 'true',
                    'DPDsession' => $tracking_session,
                ];
                $query = ['headers' => $header, 'parcel' => $parcel];
            }
        }
        return $query;
    }

    /**
     * [request 接口请求]
     * @Author   Tinsy
     * @DateTime 2018-04-23T09:11:42+0800
     * @return   [type]                   [description]
     */
    public function request($params = [])
    {
        $results     = [];
        $params_data = [];
        $params      = array_values($params);
        $requests    = function ($params) use (&$params_data) {
            $total = count($params);
            for ($i = 0; $i < $total; $i++) {
                $param = $params[$i];
                try {
                    $query = $this->buildParams($param);
                    if ($query) {
                        $api_url = sprintf($this->apiUrl, $query['parcel']);
                        unset($query['parcel']);
                        $params_data[] = $param;
                        yield function () use ($api_url, $query) {
                            return $this->client->getAsync($api_url, $query);
                        };
                    }
                } catch (RequestException $e) {
                    ConfigUtils::log($param, $e->getMessage());
                }
            }
        };
        $pool = new Pool($this->client, $requests($params), [
            'concurrency' => TrackRequest::ASYNC_MAX_NUM,
            'fulfilled'   => function (ResponseInterface $response, $index) use (&$results, &$params_data) {
                $results[$params_data[$index]['track_code']] = $response;
            },
            'rejected'    => function (RequestException $e, $index) use ($params_data) {
                ConfigUtils::log([], '第' . $index . '个发生了错误');
                ConfigUtils::log($params_data[$index], $e->getMessage());
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
            if (empty($response_item['error'])) {
                if (!empty($response_item['data'])) {
                    $track_log = [];
                    $is_valid  = false;
                    foreach ($response_item['data'] as $log) {
                        $log['eventLocation'] = is_array($log['eventLocation']) ? implode(' ', $log['eventLocation']) : $log['eventLocation'];
                        $track_log[]          = [
                            'remark' => $log['eventDate'] . ' ' . $log['eventLocation'],
                            'event'  => $log['eventText'],
                        ];
                        $is_valid = $is_valid || ConfigUtils::checkStrExist($log['eventText'], ConfigUtils::$carrierData[$this->carrierCode]['valid_str']);
                    }
                    $current_track = current($track_log);
                    $is_over       = ConfigUtils::checkStrExist($current_track['event'], ConfigUtils::$carrierData[$this->carrierCode]['over_str']);
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
        call_user_func($callback, $trackData) === false;
    }
}
