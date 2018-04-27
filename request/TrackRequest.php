<?php
/**
 *
 * @authors Rei (eva51513@gmail.com)
 * @date    2018-04-20 16:31:14
 * @version $Id$
 * 物流请求接口
 */
namespace track\request;

interface TrackRequest
{
    /**
     * [request 接口请求]
     * @Author   Tinsy
     * @DateTime 2018-04-20T18:44:58+0800
     * @return   [type]                   [description]
     */
    public function request();
    /**
     * [buildParams 创建请求参数]
     * @Author   Tinsy
     * @DateTime 2018-04-20T16:42:14+0800
     * @return   [type]                   [description]
     */
    public function buildParams($params = []);
    /**
     * [getTrackData 获取物流信息]
     * @Author   Tinsy
     * @DateTime 2018-04-20T16:44:02+0800
     * @param    array                    $response [description]
     * @return   [type]                             [description]
     */
    public function getTrackData($response = [], &$trackData = [], &$trackParams = []);

}
