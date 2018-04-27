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
use QL\QueryList;
use Track\ConfigUtils;

class ParcelforceTrackRequest implements TrackRequest
{

    protected $carrierId = 100004;

    protected $carrierCode = 'Parcelforce';

    protected $resultUrl = 'http://tracking.parcelforce.net/pod/SNP_POD_pos.php?DIVA_STAT=Pfw&NAME=szParam&NORIGHTBOX=1';

    protected $detailsUrl = 'http://tracking.parcelforce.net/pod/SNP_POD_det.php';

    protected $trackingDetailsUrl = 'http://tracking.parcelforce.net/pod/SNP_POD_detevt.php';

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
        $basic_param = [
            'NAME'             => 'szShippingNumber=Consignment or parcel number:;',
            'szShippingNumber' => $param['track_code'],
            'PASSWORD'         => 'anonymous',
            'LOGIN'            => '253a56592b2f5c3d4210253a56592b2f5c3d427e273b54',
            'MMITYPE'          => '2',
            'CAR'              => '068',
        ];
        $basic_query = [
            'form_params' => $basic_param,
        ];
        $body_result              = $this->client->post($this->resultUrl, $basic_query)->getBody()->getContents();
        $form_result              = QueryList::html($body_result)->find('form')->serializeArray();
        $form_result              = $form_result ?? [];
        $result_param             = array_column($form_result, 'value', 'name');
        $result_param             = array_merge($result_param, $basic_param);
        $dwkeynum                 = QueryList::html($body_result)->find('.SNP_POD_pos_data_sort_ba:first a')->attr('href');
        $reg                      = "@'\d{1,}'@";
        $result_param['DWKEYNUM'] = preg_match($reg, $dwkeynum, $match) ? trim($match[0], "'") : '';
        $result_param['EVT']      = 'EXP';
        return ['form_params' => $result_param];
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
            try {
                $query                          = $this->buildParams($param);
                $promises[$param['track_code']] = $this->client->postAsync($this->trackingDetailsUrl, $query)->then(
                    function (ResponseInterface $response) use (&$results, $param) {
                        $results[$param['track_code']] = $response;
                    },
                    function (RequestException $e) use ($param) {
                        ConfigUtils::log($param, $e->getMessage());
                    }
                );
            } catch (RequestException $e) {
                ConfigUtils::log($param, $e->getMessage());
            }
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
            $html = $response_item->getBody()->getContents();
            $reg  = [
                'track_item' => ['.SNP_SETPOINTER', 'html'],
            ];
            $is_valid  = false;
            $track_log = QueryList::html($html)->rules($reg)->query()->getData(function ($node) use (&$trackData, &$trackParams, &$is_valid) {
                $item           = [];
                $query          = QueryList::html($node['track_item']);
                $item['remark'] = $query->find('td:eq(0)')->text() . ' ' . $query->find('td:eq(1)')->text();
                $item['event']  = $query->find('td:eq(2)')->text();
                $is_valid       = $is_valid || in_array($item['event'], ConfigUtils::$carrierData['Parcelforce']['valid_str']);
                return $item;
            })->toArray();
            if (!empty($track_log)) {
                $current_track = current($track_log);
                $is_over       = strpos($current_track['event'], ConfigUtils::$carrierData['Parcelforce']['over_str']) !== false;
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
