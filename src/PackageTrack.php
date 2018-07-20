<?php
/**
 *
 * @authors Rei (eva51513@gmail.com)
 * @date    2018-04-20 11:41:19
 * 物流跟踪
 * @version $Id$
 */
namespace track;

use track\ConfigUtils;

class PackageTrack
{

    /**
     * [$trackParams 查询参数]
     * @var array
     */
    protected $trackParams = [];
    /**
     * [$trackData 跟踪信息]
     * @var array
     */
    protected $trackData = [];
    /**
     * [$maxQueryRetry 最大重试次数]
     * @var integer
     */
    protected $maxQueryRetry = 3;
    /**
     * [$QueryDuration 查询间隔时间]
     * @var integer
     */
    protected $QueryDuration = 5;
    /**
     * [$trackRequest 接口]
     * @var [type]
     */
    protected $trackRequest;

    public function __construct()
    {
        $this->trackRequest = new request\DefaultTrackRequest;
    }
    /**
     * [setTrackParams 设置查询参数]
     * @Author   Tinsy
     * @DateTime 2018-04-20T14:11:30+0800
     * @param    array                    $params [需求数组 key:track_code-跟踪号  value:carrier_code-运输商代号]
     * eg.[['33'=>'yadel'], ['dbd'=>'dd']...]
     */
    public function setTrackParams($params = [])
    {
        $this->trackParams = array_column($params, null, 'track_code'); //array_combine(array_column($params, 'track_code'), $params);
    }
    /**
     * [execute 执行入口]
     * @Author   Tinsy
     * @DateTime 2018-04-20T15:01:10+0800
     * @return   [type]                   [description]
     */
    public function execute($params = [], callable $callback)
    {
        $this->setTrackParams($params);
        //1.17track 初查
        $retry_times = 0;
        while ($this->trackParams) {
            if ($retry_times === $this->maxQueryRetry) {
                break;
            }
            $response = $this->trackRequest->request($this->trackParams);
            $this->trackRequest->getTrackData($response, $this->trackParams, $callback);
            $retry_times++;
            $this->trackParams ? sleep($this->QueryDuration) : true;
        }
        //2.运输商官网 复查
        $retry_times = 0;
        while (!empty($this->trackParams)) {
            if ($retry_times === $this->maxQueryRetry) {
                break;
            }
            $carrier_params = [];
            foreach ($this->trackParams as $param) {
                $carrier_params[$param['carrier_code']][$param['track_code']] = $param;
            }
            $this->trackParams = [];
            foreach ($carrier_params as $carrier_code => $carrier) {
                if (isset(ConfigUtils::$carrierData[$carrier_code])) {
                    $request_name = '\\track\\request\\' . ConfigUtils::$carrierData[$carrier_code]['api'];
                    $trackRequest = new $request_name;
                    $response     = $trackRequest->request($carrier);
                    $trackRequest->getTrackData($response, $carrier, $callback);
                }
                $this->trackParams = array_merge($this->trackParams, $carrier);
            }
            $retry_times++;
            $this->trackParams ? sleep($this->QueryDuration) : true;
        }
        return ['track_fail_params' => $this->trackParams];
        //return ['track_fail_params' => $this->trackParams, 'track_success_data' => $track_data];
    }

    /**
     * [setMaxQueryRetry description]
     * @Author   Tinsy
     * @DateTime 2018-07-20T18:26:20+0800
     * @param    integer                  $times [description]
     */
    public function setMaxQueryRetry($times = 5){
        $this->maxQueryRetry = $times;
    }
    /**
     * [setQueryDuration description]
     * @Author   Tinsy
     * @DateTime 2018-07-20T18:27:43+0800
     * @param    integer                  $second [description]
     */
    public function setQueryDuration($second = 5){
        $this->queryDuration = $second
    }
}
