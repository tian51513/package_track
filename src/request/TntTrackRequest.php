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
use Psr\Http\Message\ResponseInterface;
use track\ConfigUtils;

class TntTrackRequest implements TrackRequest
{

    protected $carrierId = 100004;

    protected $carrierCode = 'TNT';

    protected $apiUrl = 'https://www.tnt.com/api/v2/shipment?';

    protected $method = 'get';

    protected $maxCount = 1;

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
    public function buildParams($param = [])
    {
        $query['con']        = $param['track_code'];
        $query['searchType'] = 'CON';
        $query['locale']     = 'en_US';
        return ['verify' => false, 'query' => $query];
    }
    /**
     * [request 接口请求]
     * @Author   Tinsy
     * @DateTime 2018-04-23T09:11:42+0800
     * @return   [type]                   [description]
     */
    public function request($params = [])
    {
        $promises = $results = [];
        foreach ($params as $param) {
            $promises[$param['track_code']] = $this->client->getAsync($this->apiUrl, $this->buildParams($param))->then(
                function (ResponseInterface $response) use (&$results, $param) {
                    $results[$param['track_code']] = $response;
                },
                function (RequestException $e) use ($param) {
                    ConfigUtils::log($param, $e->getMessage());
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
        foreach ($response as $track_code => $response_item) {
            $response_item = $response_item->getBody()->getContents();
            $response_item = json_decode($response_item, true);
            if (isset($response_item['tracker.output']['consignment'])) {
                $track     = $response_item['tracker.output']['consignment'][0];
                $list      = $track['statusData'] ?? [];
                $track_log = [];
                $is_valid  = false;
                foreach ($list as $log) {
                    $track_log[] = [
                        'remark' => $log['localEventDate'] . ' ' . $log['depot'],
                        'event'  => $log['statusDescription'],
                    ];
                    $is_valid = $is_valid || ConfigUtils::checkStrExist($log['statusDescription'], ConfigUtils::$carrierData[$this->carrierCode]['valid_str']);
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
}
