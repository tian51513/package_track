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
use QL\QueryList;
use track\ConfigUtils;

class DhldeTrackRequest implements TrackRequest
{

    protected $carrierId = '07041';

    protected $carrierCode = 'DHL(DE)';

    protected $apiUrl = 'https://nolp.dhl.de/nextt-online-public/en/search?';

    protected $method = 'get';

    public function __construct()
    {
        $this->client = new Client(['verify' => false]);
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
                function (RequestException $e) use($param) {
                    ConfigUtils::log($param, $e->getMessage());
                }
            );
        }
        \GuzzleHttp\Promise\unwrap($promises);
        return $results;
    }

    /**
     * [buildParams 创建请求参数]
     * @Author   Tinsy
     * @DateTime 2018-04-20T16:42:14+0800
     * @return   [type]                   [description]
     */
    public function buildParams($param = [])
    {
        $query = [
            'cid'       => 'dhlde',
            'piececode' => $param['track_code'],
        ];
        return ['verify' => false, 'query' => $query];
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
        foreach ($response as $track_code => $page) {
            $html = $page->getBody()->getContents();
            $reg  = [
                'package_info'   => ['.mm_shipmentReference .mm_shipment-number:eq(2)', 'html'],
                'package_status' => ['.mm_shipmentStatusInnerContainer .mm_flexNoGrow dd', 'text'],
                'track_data'     => ['.mm_verfolgen-info div', 'html'],
            ];
            QueryList::html($html)->rules($reg)->query()->getData(function ($container) use (&$trackData, &$trackParams, $track_code) {
                if (isset($container['track_data'])) {
                    $reg = [
                        'date'  => ['dt', 'text'],
                        'event' => ['dd', 'text'],
                    ];
                    $is_valid                = false;
                    $container['track_data'] = QueryList::html($container['track_data'])->rules($reg)->query()->getData(function ($item) use (&$is_valid) {
                        $is_valid = $is_valid || in_array($item['event'], ConfigUtils::$carrierData[$this->carrierCode]['valid_str']);
                        return $item;
                    })->toArray();
                    $current_track = current($container['track_data']);
                    $is_valid      = strpos($current_track['event'], 'sender to DHL electronically') ? false : $is_valid;
                    $is_over       = strpos($current_track['event'], ConfigUtils::$carrierData[$this->carrierCode]['over_str']) !== false;
                    $trackData[]   = [
                        'track_code'   => $track_code,
                        'carrier_code' => $this->carrierCode,
                        'is_valid'     => $is_valid,
                        'is_over'      => $is_over,
                        'current_info' => $current_track['event'],
                        'track_log'    => $container['track_data'],
                    ];
                    unset($trackParams[$track_code]);
                }
            });
        }
    }
}
