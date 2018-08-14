<?php
/**
 *
 * @authors Rei (eva51513@gmail.com)
 * @date    2018-04-20 17:17:04
 * @version $Id$
 * track_code 为数字
 */
namespace track\api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use Psr\Http\Message\ResponseInterface;
use track\ConfigUtils;
use track\request\TrackRequest;

class ParcelperformApi
{
    /*
    服务商id
     */
    protected $carrierData = [
        'Royalmail' => '245',
        'EMS'       => '44',
    ];

    protected $carrierCode = '';
    /*
    parcelperform get auth token url
     */
    protected $accessUrl = 'https://api.parcelperform.com/auth/oauth/token/';
    /*
    parcelperform add parcel url
     */
    protected $addUrl = 'https://api.parcelperform.com/parcel/v2/';
    /*
    parcelperform login url get protal access_token
     */
    protected $loginUrl = 'https://authenticator.parcelperform.com/api/authentication/authenticate-with-usn-pwd/';
    /*
    parcelperform parcel item details url
     */
    protected $parcelItemUrl = 'https://data.parcelperform.com/api/v1/parcel-perform/shipment/p547168c3f6/get/';
    /*
    parcelperform parcel list url
     */
    protected $parcelListUrl = 'https://data.parcelperform.com/api/v3/parcel-perform/shipment/p547168c3f6/';
    /*
    parcelperform parcel counts url
     */
    protected $countsUrl = 'https://analytics1.parcelperform.com/api/v1/dashboard/parcel-counts/';

    const KEY_SECRET = [
        'auth'  => [
            'client_id'     => 'fe1f8fc2df3e4e745e62857baba9df31',
            'client_secret' => 'ODNlNjI3ZmQtZWU3Zi00NDkxLTY4M2MtNDAxMGJiMDc5ODVj',
        ],
        'login' => [
            'client_id'     => 'bZQ20uQURlyXlI1BPd88U3noJpbkl9JeIVpZzjiZ',
            'client_secret' => 'qQ5SQlxQPWKghOCqWZeA94qlxz1S3VyaTjl6Ptw7QNzLrBdFrhugnWASmZNHQKy1R75w1j89gusbPUwDKtiXLpPG7HiFMWHZvF6Bzz52XTGkeFofxF824nVQa4q6sTWh',
        ],
    ];

    public function __construct()
    {
        $this->client = new Client(['verify' => false, 'timeout' => 60, 'connect_timeout' => 60]);
    }

    /**
     * [buildParams 创建请求参数]
     * @Author   Tinsy
     * @DateTime 2018-04-20T16:42:14+0800
     * @return   [type]                   [description]
     */
    public function buildParams($param = [])
    {
        $query              = false;
        $login_access_token = $this->getLoginToken();
        if ($login_access_token) {
            $query = [
                'query'   => [
                    'carrier_id' => $this->carrierData[$this->carrierCode] ?? '',
                    'parcel_pk'  => 1, //parcel_id 暂时作1处理
                    'parcel_id'  => $param['track_code'],
                ],
                'headers' => [
                    'Authorization' => "Bearer $login_access_token",
                ],
            ];
        }
        return $query;
    }
    /**
     * [getAddPacelData 获取新parcel]
     * @Author   Tinsy
     * @DateTime 2018-06-19T09:46:43+0800
     * @param    array                    $params [description]
     * @return   [type]                           [description]
     */
    public function getAddParcelData($params = [])
    {
        $parcels = [];
        foreach ($params as $param) {
            empty($param['status']) ? $parcels[] = ['parcel_id' => $param['track_code']] : true;
        }
        return $parcels;
    }
    /**
     * [request 接口请求]
     * @Author   Tinsy
     * @DateTime 2018-04-23T09:11:42+0800
     * @return   [type]                   [description]
     */
    public function request($params = [])
    {
        $results = [];
        $params  = array_values($params);
        $this->addParcel($this->getAddParcelData($params));
        $requests = function ($params) {
            $total = count($params);
            for ($i = 0; $i < $total; $i++) {
                $param = $params[$i];
                try {
                    $query = $this->buildParams($param);
                    if ($query) {
                        $api_url = $this->parcelItemUrl;
                        yield function () use ($api_url, $query) {
                            return $this->client->getAsync($api_url, $query);
                        };
                    }
                } catch (RequestException $e) {
                    ConfigUtils::log($param, $e->getMessage());
                }
            }
        };
        $pool = new Pool($this->client, $requests($params), [
            'concurrency' => TrackRequest::ASYNC_MAX_NUM,
            'fulfilled'   => function (ResponseInterface $response, $index) use (&$results) {
                $results[] = $response;
            },
            'rejected'    => function (RequestException $e, $index) {
                ConfigUtils::log([], '第' . $index . '个发生了错误');
                ConfigUtils::log($e->getMessage(), '####请求错误####');
            },
        ]);
        // 开始发送请求
        $promise = $pool->promise();
        $promise->wait();
        return $results;
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
        foreach ($response as $response_item) {
            $response_item = $response_item->getBody()->getContents();
            $response_item = json_decode($response_item, true);
            if ($response_item['status'] === 'success') {
                $response_data = $response_item['data'];
                $is_valid      = false;
                $track_log     = [];
                $is_over       = $response_data['status'] === 'delivered' ? true : false;
                if (isset($response_data['events'])) {
                    foreach ($response_data['events'] as $event) {
                        $track_log[] = [
                            'remark' => $event['event_time'] . ' ' . $event['event_location'],
                            'event'  => $event['event_type'],
                        ];
                        $is_valid = $is_valid || ConfigUtils::checkStrExist($event['event_type'], ConfigUtils::$carrierData[$this->carrierCode]['valid_str']);
                    }
                    $current_track = $response_data['last_event'];
                    $trackData[]   = [
                        'track_code'   => $response_data['parcel_id'],
                        'carrier_code' => $this->carrierCode,
                        'is_valid'     => $is_over ? true : $is_valid,
                        'is_over'      => $is_over,
                        'current_info' => $current_track['event_type'],
                        'track_log'    => $track_log,
                    ];
                    unset($trackParams[$response_data['parcel_id']]);
                }
            } else {
                ConfigUtils::log($response_item, '#####parcelperform 获取parcel item 失败#####');
            }
        }
        call_user_func($callback, $trackData) === false;
    }
    /**
     * [getAccessToken 获取parcelperform auth token]
     * @Author   Tinsy
     * @DateTime 2018-06-04T11:35:26+0800
     * @return   [type]                   [description]
     */
    private function getAccessToken($is_update = false)
    {
        $api_access_token = ConfigUtils::cache('parcelerform_api_access_token');
        if ($is_update || empty($api_access_token)) {
            $api_access_token = false;
            try {
                $auth   = base64_encode(self::KEY_SECRET['auth']['client_id'] . ':' . self::KEY_SECRET['auth']['client_secret']);
                $params = [
                    'form_params' => ['grant_type' => 'client_credentials'],
                    'verify'      => false,
                    'headers'     => [
                        'Content-Type'  => 'application/x-www-form-urlencoded',
                        'Authorization' => "Basic $auth",
                    ],
                ];
                $response = $this->client->post($this->accessUrl, $params);
                $response = $response->getBody()->getContents();
                $response = json_decode($response);
                if (isset($response->error)) {
                    ConfigUtils::log($response, '#####parcelperform api_access_token 失败 error_2#####');
                } else {
                    ConfigUtils::cache('parcelerform_api_access_token', $response->access_token, $response->expires_in);
                    $api_access_token = $response->access_token;
                }
            } catch (RequestException $e) {
                ConfigUtils::log($e->getMessage(), '#####parcelperform api_access_token 失败 error_1#####');
            }
        }
        return $api_access_token;
    }
    /**
     * [getLoginToken 获取parcelperform login token]
     * @Author   Tinsy
     * @DateTime 2018-06-04T11:50:58+0800
     * @param    boolean                  $is_update [description]
     * @return   [type]                              [description]
     */
    private function getLoginToken($is_update = false)
    {
        $login_access_token = ConfigUtils::cache('parcelerform_login_access_token');
        if ($is_update || empty($login_access_token)) {
            $login_access_token = false;
            try {
                $query = [
                    'username'      => 'eva51513@gmail.com',
                    'password'      => 'T51513148',
                    'client_id'     => self::KEY_SECRET['login']['client_id'],
                    'client_secret' => self::KEY_SECRET['login']['client_secret'],
                    // 'scope'=>''
                ];
                $params = [
                    'body'    => json_encode($query),
                    'verify'  => false,
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                ];
                $response = $this->client->post($this->loginUrl, $params);
                $response = $response->getBody()->getContents();
                $response = json_decode($response);
                if ($response->status === 'success') {
                    ConfigUtils::cache('parcelerform_login_access_token', $response->data->access_token, $response->data->expires_in);
                    $login_access_token = $response->data->access_token;
                } else {
                    ConfigUtils::log($response->message, '#####parcelperform login_access_token 失败 error_2#####');
                }
            } catch (RequestException $e) {
                ConfigUtils::log($e->getMessage(), '#####parcelperform login_access_token 失败 error_1#####');
            }
        }
        return $login_access_token;
    }
    /**
     * [addParcel 新增parcel]
     * @Author   Tinsy
     * @DateTime 2018-06-04T13:40:53+0800
     * @param    array                    $parcel_ids [处理好的parcel_id数据 [['parcel_id'=>'XXXXX'], ...]]
     */
    public static function addParcel($parcel_ids = [])
    {
        $_this            = new self;
        $api_access_token = $_this->getAccessToken();
        $response         = false;
        if ($api_access_token && !empty($parcel_ids)) {
            try {
                $params = [
                    'json'    => $parcel_ids,
                    'headers' => [
                        'Content-Type'  => 'application/json',
                        'Authorization' => "Bearer $api_access_token",
                    ],
                ];
                $response = $_this->client->post($_this->addUrl, $params);
                $response = $response->getBody()->getContents();
                $response = json_decode($response);
                $_this->getCounts(true);
            } catch (RequestException $e) {
                ConfigUtils::log($e->getMessage(), '#####parcelperform 新增parcel 失败#####');
                ConfigUtils::log($parcel_ids, '####parcel_ids数据####');
            }
        }
        return $response;
    }
    /**
     * [getCounts 获取parcelperform parcel counts]
     * @Author   Tinsy
     * @DateTime 2018-06-04T14:05:33+0800
     * @return   [type]                   [description]
     */
    public function getCounts($is_update = false)
    {
        $counts = ConfigUtils::cache('parcelerform_parcel_counts');
        if (empty($counts) || $is_update) {
            $counts             = 0;
            $login_access_token = $this->getLoginToken();
            try {
                if ($login_access_token) {
                    $params = [
                        'query'   => [
                            'organization_slug' => 'p547168c3f6',
                        ],
                        'headers' => [
                            'Content-Type'  => 'application/json',
                            'Authorization' => "Bearer $login_access_token",
                        ],
                    ];
                    $response = $this->client->get($this->countsUrl, $params);
                    $response = $response->getBody()->getContents();
                    $response = json_decode($response);
                    if ($response->status === 'success') {
                        $counts = $response->data->all;
                        ConfigUtils::cache('parcelerform_parcel_counts', $counts);
                    } else {
                        ConfigUtils::log($response->message, '#####parcelperform 获取parcel总数 失败 error_2#####');
                    }
                }
            } catch (RequestException $e) {
                ConfigUtils::log($e->getMessage(), '#####parcelperform 获取parcel总数 失败 error_1#####');
            }
        }
        return $counts;
    }
    /**
     * [getParcelList 获取parcelperform parcel list]
     * @Author   Tinsy
     * @DateTime 2018-06-04T13:49:11+0800
     * @return   [type]                   [description]
     */
    private function getParcelList()
    {
        $login_access_token = $this->getLoginToken();
        $response           = false;
        $counts             = $this->getCounts();
        $rows               = 100;
        try {
            if ($login_access_token) {
                $index = 100;
                while ($index <= $counts) {
                    $params = [
                        'query'   => [
                            'accept_pending_parcel' => true,
                            'field_name_sorting'    => 'imported_date',
                            'order_by'              => 'des',
                            'page'                  => $index / $rows,
                            'quantity'              => $rows,
                        ],
                        'headers' => [
                            'Content-Type'  => 'application/json',
                            'Authorization' => "Bearer $login_access_token",
                        ],
                    ];
                    $response = $this->client->post($this->parcelListUrl, $params);
                    $response = $response->getBody()->getContents();
                    $response = json_decode($response);
                    //TODO 暂时不用
                    $index += $rows;
                }
            }
        } catch (RequestException $e) {
            ConfigUtils::log($e->getMessage(), '#####parcelperform 获取parcel列表 失败#####');
        }
    }
    /**
     * [getParcel 跟踪详情]
     * @Author   Tinsy
     * @DateTime 2018-06-04T14:45:55+0800
     * @param    boolean                  $track_code [description]
     * @param    integer                  $parcel_id  [description]
     * @return   [type]                               [description]
     */
    private function getParcel($track_code = false, $parcel_id = 1)
    {
        $login_access_token = $this->getLoginToken();
        $response           = false;
        return $response;
    }
    /**
     * [setCarrierCode description]
     * @Author   Tinsy
     * @DateTime 2018-06-15T17:34:55+0800
     * @param    [type]                   $carrier_code [description]
     */
    public function setCarrierCode($carrier_code)
    {
        $this->carrierCode = $carrier_code;
    }
}
