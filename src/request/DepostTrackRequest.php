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

class DepostTrackRequest implements TrackRequest
{

    protected $carrierId = '';

    protected $carrierCode = 'DePost';

    protected $apiUrl = 'https://www.deutschepost.de/sendung/simpleQueryResult.html';

    protected $method = 'post';

    protected $maxCount = 1;

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
    public function buildParams($param = [])
    {
        $query = [
            'form.sendungsnummer'           => $param['track_code'],
            'form.einlieferungsdatum_tag'   => $param['date_day'] ?? '1',
            'form.einlieferungsdatum_monat' => $param['date_month'] ?? '1',
            'form.einlieferungsdatum_jahr'  => $param['date_year'] ?? '2018',
        ];
        return ['form_params' => $query];
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
            $current_track = QueryList::html($html)->find('td.grey')->text();
            if ($current_track) {
                $is_valid    = true;
                $is_over     = strpos($current_track, ConfigUtils::$carrierData[$this->carrierCode]['over_str']) !== false;
                $trackData[] = [
                    'track_code'   => $track_code,
                    'carrier_code' => $this->carrierCode,
                    'is_valid'     => $is_valid,
                    'is_over'      => $is_over,
                    'current_info' => $current_track,
                    'track_log'    => ['remark' => '', 'event' => $current_track],
                ];
                unset($trackParams[$track_code]);
            }
        }
    }
}
