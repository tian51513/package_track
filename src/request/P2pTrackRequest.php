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

class P2pTrackRequest implements TrackRequest
{

    protected $carrierId = '';

    protected $carrierCode = 'P2P';

    protected $apiUrl = 'http://www.trackmytrakpak.com?';

    protected $method = 'get';

    protected $maxCount = 1;

    public function __construct()
    {
        $this->client = new Client(['verify' => false, 'timeout' => 60]);
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
        $params   = array_values($params);
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
                $results[$params[$index]['track_code']] = $response;
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
    public function buildParams($param = [])
    {
        return ['query' => ['MyTrakPakNumber' => $param['track_code']]];
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
            $html = $page->getBody()->getContents();
            $reg  = [
                'package_info' => ['.display-ml:eq(2)', 'html'],
                'track_data'   => ['.BodyBlock:eq(1) table', 'html'],
            ];
            QueryList::html($html)->rules($reg)->query()->getData(function ($container) use (&$trackData, &$trackParams, $track_code) {
                if (isset($container['track_data'])) {
                    $reg = [
                        'track_node' => ['tr:gt(0)', 'html'],
                    ];
                    $is_valid  = false;
                    $track_log = QueryList::html($container['track_data'])->rules($reg)->query()->getData(function ($node) use (&$is_valid) {
                        $query    = QueryList::html($node['track_node']);
                        $remark   = $query->find('td:first')->text();
                        $event    = $query->find('td:eq(1)')->text();
                        $is_valid = $is_valid || ConfigUtils::checkStrExist($event, ConfigUtils::$carrierData[$this->carrierCode]['valid_str']);
                        return ['remark' => $remark, 'event' => $event];
                    })->toArray();
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
            });
        }
        call_user_func($callback, $trackData) === false;
    }
}
