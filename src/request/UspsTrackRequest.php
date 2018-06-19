<?php
/**
 *
 * @authors Rei (eva51513@gmail.com)
 * @date    2018-04-20 17:17:04
 * @version $Id$
 */
namespace track\request;

use GuzzleHttp\Client;
use QL\QueryList;
use track\ConfigUtils;

class UspsTrackRequest implements TrackRequest
{

    protected $carrierId = '21051';

    protected $carrierCode = 'USPS';

    protected $apiUrl = 'https://tools.usps.com/go/TrackConfirmAction?';

    protected $method = 'get';

    protected $maxCount = 35;

    public function __construct()
    {
        $this->client = new Client(['verify' => false, 'allow_redirects' => false, 'timeout' => 60, 'connect_timeout'=>60]);
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
        $results     = [];
        $header      = ['timeout' => 0];
        $urls        = [];
        foreach ($params_data as $params) {
            $urls[] = $this->apiUrl . $this->buildParams($params);
        }
        $curl            = (new \Ares333\Curl\Toolkit())->getCurl();
        $curl->maxThread = TrackRequest::ASYNC_MAX_NUM;
        $curl->maxTry    = 0;
        $curl->opt       = [
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $header,
        ];
        $curl->cache  = ['enable' => false, 'compress' => false, 'dir' => null, 'expire' => 86400, 'verifyPost' => false];
        $curl->onInfo = function () {};
        $curl->onFail = function () {};
        $total_count  = count($urls);
        $curl->onTask = function ($curl) use ($urls, &$results, &$total_count) {
            if ($total_count == 0) {
                return;
            }
            foreach ($urls as $url) {
                $total_count--;
                $curl->add(
                    array(
                        'opt' => array(
                            CURLOPT_URL => $url,
                        ),
                    ), function ($response, $args) use (&$results) {
                        $results[] = $response['body'];
                    }, function ($response, $args) {
                        ConfigUtils::log($response, $response['errorMsg']);
                    }
                );
            }
        };
        $curl->start();
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
        $code_strs = implode(',', array_column($params, 'track_code'));
        $query     = [
            'tLabels' => $code_strs,
            'tRef'    => 'fullpage',
            'tLc'     => '5',
        ];
        $param          = ['verify' => false, 'timeout' => 0];
        $param['query'] = $query;
        return http_build_query($query);
        // return $param;
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
        foreach ($response as $page) {
            $html = $page; //$page->getBody()->getContents();
            $reg  = ['package_track' => ['.track-bar-container', 'html']];
            QueryList::html($html)->rules($reg)->query()->getData(function ($container) use (&$trackData, &$trackParams) {
                if (isset($container['package_track'])) {
                    $reg = [
                        'track_code'        => ['.tracking-number', 'text'],
                        'track_data'        => ['.thPanalAction', 'html'],
                        'progress_status'   => ['.delivery_status h2', 'text'],
                        'expected_delivery' => ['.expected_delivery', 'html'],
                    ];
                    QueryList::html($container['package_track'])->rules($reg)->query()->getData(function ($item) use (&$trackData, &$trackParams) {
                        if (isset($item['expected_delivery']) && strlen($item['expected_delivery']) > 111) {
                            unset($item['expected_delivery']);
                            $track_data_list = array_filter(explode('<hr>', $item['track_data']));
                            $reg             = [
                                'log' => ['span', 'text'],
                            ];
                            $track_log = [];
                            $is_valid  = false;
                            foreach ($track_data_list as $track_node) {
                                $node = QueryList::html($track_node)->rules($reg)->query()->getData()->toArray();
                                $date = str_replace(["\n", "\t", "\r", '                    '], '', $node[0]['log']);
                                if (count($node) == 2) {
                                    $track_log[] = ['remark' => $date, 'event' => $node[1]['log']];
                                } else {
                                    $track_log[] = ['remark' => $date . ' ' . $node[2]['log'], 'event' => $node[1]['log']];
                                }
                                $is_valid = $is_valid || ConfigUtils::checkStrExist($node[1]['log'], ConfigUtils::$carrierData[$this->carrierCode]['valid_str']);
                            }
                            $current_track = current($track_log);
                            $is_over       = ConfigUtils::checkStrExist($current_track['event'], ConfigUtils::$carrierData[$this->carrierCode]['over_str']);
                            $trackData[]   = [
                                'track_code'   => $item['track_code'],
                                'carrier_code' => $this->carrierCode,
                                'is_valid'     => $is_over ? true : $is_valid,
                                'is_over'      => $is_over,
                                'current_info' => $current_track['event'],
                                'track_log'    => $track_log,
                            ];
                            unset($trackParams[$item['track_code']]);
                        }
                    });
                }
            });
        }
        call_user_func($callback, $trackData) === false;
    }
}
