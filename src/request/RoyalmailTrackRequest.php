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

class RoyalmailTrackRequest implements TrackRequest
{

    protected $carrierId = '';

    protected $carrierCode = 'Royalmail';

    protected $preUrl = 'https://www.royalmail.com';

    protected $apiUrl = 'https://api.royalmail.net/mailpieces/v2/%s/events';

    protected $method = 'get';

    protected $maxCount = 1;

    public function __construct()
    {
        $this->client = new Client(['verify' => false, 'timeout' => 60]);
    }

    /**
     * [buildParams 创建请求参数]
     * @Author   Tinsy
     * @DateTime 2018-04-20T16:42:14+0800
     * @return   [type]                   [description]
     */
    public function buildParams($param = [])
    {
    }
    /**
     * [request 接口请求]
     * @Author   Tinsy
     * @DateTime 2018-04-23T09:11:42+0800
     * @return   [type]                   [description]
     */
    public function request($params = [])
    {
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
        call_user_func($callback, $trackData) === false;
    }
}
