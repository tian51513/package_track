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

class DhldeTrackRequest implements TrackRequest
{

    protected $carrierId = '07041';

    protected $carrierCode = 'DHL(DE)';

    protected $apiUrl = 'https://nolp.dhl.de/nextt-online-public/en/search?';

    protected $method = 'get';

    protected $maxCount = 1;

    public function __construct()
    {
        $this->client = new Client(['verify' => false, 'timeout' => 60, 'connect_timeout' => 60]);
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
    public function getTrackData($response = [], &$trackParams = [], callable $callback)
    {
        $trackData = [];
        foreach ($response as $track_code => $page) {
            $html = $page->getBody()->getContents();
            $reg  = [
                // 'package_info'   => ['.mm_shipmentReference .mm_shipment-number:eq(2)', 'html'],
                // 'package_status' => ['.mm_shipmentStatusInnerContainer .mm_flexNoGrow dd', 'text'],
                'track_data' => ['.mm_verfolgen-info dl', 'html'],
            ];
            $match = [];
            $regex = '/(?<=JSON.parse\(").*?(?="\))/';
            if (preg_match($regex, $html, $match)) {
                $json_str  = $match[0];
                $json_data = json_decode(stripslashes($json_str), true);
                if (!empty($json_data) && $json_data['sendungen'][0]['sendungsdetails']['sendungsverlauf'] ?? []) {
                    $track     = $json_data['sendungen'][0]['sendungsdetails']['sendungsverlauf'];
                    $track_log = [];
                    $is_valid  = false;
                    if (isset($track['events'])) {
                        foreach ($track['events'] as $item) {
                            $remark      = $item['datum'] ?? '' . $item['ort'] ?? '';
                            $event       = $item['status'] ?? '';
                            $track_log[] = [
                                'remark' => $remark,
                                'event'  => $event,
                            ];
                            $is_valid = $is_valid || ConfigUtils::checkStrExist($event, ConfigUtils::$carrierData[$this->carrierCode]['valid_str']);
                        }
                        krsort($track_log);
                        $track_log   = array_values($track_log);
                        $is_over     = ConfigUtils::checkStrExist($track['aktuellerStatus'], ConfigUtils::$carrierData[$this->carrierCode]['over_str']);
                        $trackData[] = [
                            'track_code'   => $track_code,
                            'carrier_code' => $this->carrierCode,
                            'is_valid'     => $is_over ? true : $is_valid,
                            'is_over'      => $is_over,
                            'current_info' => $track['aktuellerStatus'],
                            'track_log'    => $track_log,
                        ];
                        unset($trackParams[$track_code]);
                    }
                }
            } else {
                QueryList::html($html)->rules($reg)->query()->getData(function ($container) use (&$trackData, &$trackParams, $track_code) {
                    if (isset($container['track_data'])) {
                        $reg = [
                            'remark' => ['dt', 'text'],
                            'event'  => ['dd', 'text'],
                        ];
                        $is_valid                = false;
                        $container['track_data'] = QueryList::html($container['track_data'])->rules($reg)->query()->getData(function ($item) use (&$is_valid) {
                            $is_valid = $is_valid || ConfigUtils::checkStrExist($item['event'], ConfigUtils::$carrierData[$this->carrierCode]['valid_str']);
                            return $item;
                        })->toArray();
                        $current_track = current($container['track_data']);
                        $is_valid      = strpos($current_track['event'], 'sender to DHL electronically') ? false : $is_valid;
                        $is_over       = ConfigUtils::checkStrExist($current_track['event'], ConfigUtils::$carrierData[$this->carrierCode]['over_str']);
                        $trackData[]   = [
                            'track_code'   => $track_code,
                            'carrier_code' => $this->carrierCode,
                            'is_valid'     => $is_over ? true : $is_valid,
                            'is_over'      => $is_over,
                            'current_info' => $current_track['event'],
                            'track_log'    => $container['track_data'],
                        ];
                        unset($trackParams[$track_code]);
                    }
                });
            }
        }
        call_user_func($callback, $trackData) === false;
    }
}
