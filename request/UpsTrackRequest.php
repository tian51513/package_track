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

class UpsTrackRequest implements TrackRequest
{

    protected $carrierId = '100002';

    protected $carrierCode = 'UPS';

    protected $apiUrl = 'https://wwwapps.ups.com/WebTracking/track';

    protected $method = 'post';

    protected $maxCount = 1; //25;

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
        // $params_data = array_chunk($params, $this->maxCount);
        $promises = $results = [];
        foreach ($params as $param) {
            $promises[$param['track_code']] = $this->client->postAsync($this->apiUrl, $this->buildParams($param))->then(
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
     * [buildParams 创建请求参数]
     * @Author   Tinsy
     * @DateTime 2018-04-20T16:42:14+0800
     * @return   [type]                   [description]
     */
    public function buildParams($params = [])
    {
        $code_strs = $params['track_code']; //implode("\n", array_column($params, 'track_code'));
        $query     = [
            'loc'       => 'en_CN',
            'trackNums' => $code_strs,
            'track.x'   => 'Track',
        ];
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
        return ['body' => http_build_query($query), 'headers' => $headers];
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
                'progress_status' => ['.newstatus .current .infoAnchor', 'text'],
                'track_data'      => ['.dataTable', 'html'],
            ];
            QueryList::html($html)->rules($reg)->query()->getData(function ($container) use (&$trackData, &$trackParams, $track_code) {
                if (isset($container['progress_status']) && strpos($container['progress_status'], 'Voided') === false && isset($container['track_data'])) {
                    $reg = [
                        'track_node' => ["[valign=top]", 'html'],
                    ];
                    $is_valid     = false;
                    $valid_status = ConfigUtils::$carrierData[$this->carrierCode]['valid_str'];
                    $track_log    = QueryList::html($container['track_data'])->rules($reg)->query()->getData(function ($node) use (&$is_valid, $valid_status) {
                        $reg = [
                            'location' => ['td:eq(0)', 'text'],
                            'date'     => ['td:eq(1)', 'text'],
                            'time'     => ['td:eq(2)', 'text'],
                            'event'    => ['td:eq(3)', 'text'],
                        ];
                        $query          = QueryList::html($node['track_node']);
                        $item           = [];
                        $location       = $query->find('td:eq(0)')->text();
                        $location       = str_replace(["\n", "\t", "\r", '            '], '', $location);
                        $item['remark'] = $query->find('td:eq(1)')->text() . ' ' . $query->find('td:eq(2)')->text() . ' ' . $location;
                        $item['event']  = $query->find('td:eq(3)')->text();
                        $is_valid       = $is_valid || in_array($item['event'], $valid_status);
                        return $item;
                    })->toArray();
                    $current_track = current($track_log);
                    $is_over       = strpos($current_track['event'], ConfigUtils::$carrierData[$this->carrierCode]['over_str']) !== false;
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
            });
        }
    }
}
