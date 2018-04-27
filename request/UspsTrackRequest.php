<?php
/**
 *
 * @authors Rei (eva51513@gmail.com)
 * @date    2018-04-20 17:17:04
 * @version $Id$
 */
namespace track\request;

use GuzzleHttp\Client;
use QL\Ext\CurlMulti;
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
        $this->client = new Client(['verify' => false, 'allow_redirects' => false]);
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
        $query       = QueryList::use (CurlMulti::class);
        $urls        = [];
        foreach ($params_data as $params) {
            $urls[] = $this->apiUrl . $this->buildParams($params);
            // $results[] = QueryList::get($this->apiUrl, $this->buildParams($params), $header)->getHtml();
        }
        $query->curlMulti($urls)->success(function (QueryList $ql, CurlMulti $curl, $response) use (&$results) {
            $results[] = $response['body'];
        })->error(function ($errorInfo, CurlMulti $curl) {
            ConfigUtils::log($errorInfo, $errorInfo['error']);
        })->start([
            // 最大并发数，这个值可以运行中动态改变。
            'maxThread' => 10,
            // 触发curl错误或用户错误之前最大重试次数，超过次数$error指定的回调会被调用。
            'maxTry'    => 3,
            // 全局CURLOPT_*
            'opt'       => [
                CURLOPT_TIMEOUT        => 0,
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $header,
            ],
            // 缓存选项很容易被理解，缓存使用url来识别。如果使用缓存类库不会访问网络而是直接返回缓存。
            'cache'     => ['enable' => false, 'compress' => false, 'dir' => null, 'expire' => 86400, 'verifyPost' => false],
        ]);
        // TODO 重定向
        // $promises = $results = [];
        // foreach ($params_data as $key => $params) {
        //     $promises[] = $this->client->getAsync($this->apiUrl, $this->buildParams($params))->then(
        //         function (ResponseInterface $response) use (&$results) {
        //             $results[] = $response;
        //         },
        //         function (RequestException $e) {
        //             //TODO 错误日志
        //             echo $e->getMessage() . "\n";
        //             echo $e->getRequest()->getMethod();
        //         }
        //     );
        // }
        // \GuzzleHttp\Promise\unwrap($promises);

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
    public function getTrackData($response = [], &$trackData = [], &$trackParams = [])
    {
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
                                $is_valid = $is_valid || strpos($node[1]['log'], ConfigUtils::$carrierData[$this->carrierCode]['valid_str']) !== false;
                            }
                            $current_track = current($track_log);
                            $is_over       = strpos($current_track['event'], ConfigUtils::$carrierData[$this->carrierCode]['over_str']) !== false;
                            $trackData[]   = [
                                'track_code'   => $item['track_code'],
                                'carrier_code' => $this->carrierCode,
                                'is_valid'     => $is_valid,
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
    }
}
