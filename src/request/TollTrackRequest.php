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

class TollTrackRequest implements TrackRequest
{

    protected $carrierId = 100009;

    protected $carrierCode = 'TOLL';

    protected $apiUrl = 'https://online.toll.com.au/v1/trackAndTrace/searchConsignments';

    protected $method = 'post';

    protected $maxCount = 10;

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
    public function getTrackData($response = [], &$trackData = [], &$trackParams = [], callable $callback)
    {
        foreach ($response as $track_code => $response_item) {
            $response_item = $response_item->getBody()->getContents();
            $response_item = json_decode($response_item, true);
            if ($response_item['totalConnotes']) {
                foreach ($response_item['tatConnotes'] as $item) {
                    $track_log = [];
                    $is_valid  = false;
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
                        'is_valid'     => $is_valid,
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
}
