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
        $this->client = new Client(['verify' => false]);
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
        $params_data = array_chunk($params, $this->maxCount);
        $promises    = $results    = [];
        foreach ($params_data as $params) {
            $promises[] = $this->client->postAsync($this->apiUrl, $this->buildParams($params))->then(
                function (ResponseInterface $response) use (&$results) {
                    $results[] = $response;
                },
                function (RequestException $e) use ($params) {
                    ConfigUtils::log($params, $e->getMessage());
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
        foreach ($response as $response_item) {
            $response_item = $response_item->getBody()->getContents();
            $response      = json_decode($response_item, true);
            if ($response && $response['TrackPackagesResponse']['successful']) {
                foreach ($response['TrackPackagesResponse']['packageList'] as $package) {
                    if ($package['isSuccessful']) {
                        $track_log = [];
                        $is_valid = false;
                        foreach ($package['scanEventList'] as $log) {
                            $log['scanDetails'] = $log['scanDetails'] ? '-' . $log['scanDetails'] : '';
                            $track_log[]        = [
                                'remark' => $log['date'] . ' ' . $log['time'] . ' ' . $log['scanLocation'],
                                'event'  => $log['status'] . $log['scanDetails'],
                            ];
                            $is_valid = $is_valid || strpos($log['scanDetails'], ConfigUtils::$carrierData[$this->carrierCode]['valid_str']) !== false;
                        }
                        $current_track  = current($track_log);
                        $invalid_status = ['托运资讯发送给FedEx', '已取件'];
                        $is_valid       = !in_array($current_track['event'], $invalid_status);
                        $is_over        = strpos($current_track['event'], ConfigUtils::$carrierData[$this->carrierCode]['over_str']) !== false;
                        $trackData[]    = [
                            'track_code'   => $package['trackingNbr'],
                            'carrier_code' => $this->carrierCode,
                            'is_valid'     => $is_valid,
                            'is_over'      => $is_over,
                            'current_info' => $current_track['event'],
                            'track_log'    => $track_log,
                        ];
                        unset($trackParams[$package['trackingNbr']]);
                    }
                }
            }
        }
    }
}
