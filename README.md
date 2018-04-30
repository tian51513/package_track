# package_track

eg.
 /**
 * [packgeTrackSpider 根据数据库数据抓取]
 * @return [type] [description]
 */
function packgeTrackSpider()
{
    $map                = []; //['carrier_code'=>'FEDEX'];
    $package_data       = db('order_package')->where($map)->field(true)->select();
    $package_data_chunk = array_chunk($package_data, 500);
    $spider_service     = new \track\PackageTrack;
    foreach ($package_data_chunk as $package_data) {
        $track_data = [];
        $spider_service->execute($package_data, function ($results) use (&$track_data) {
            $data = [];
            $now  = date('Y-m-d H:i:s');
            foreach ($results as $result) {
                $data[] = [
                    'track_code'    => $result['track_code'],
                    'carrier_code'  => $result['carrier_code'],
                    'current_event' => $result['current_info'],
                    'track_events'  => json_encode($result['track_log'], JSON_UNESCAPED_UNICODE),
                    'status'        => $result['is_valid'] ? $result['is_over'] ? 2 : 1 : 0,
                    'create_time'   => $now,
                ];
            }
            $track_data = array_merge($track_data, $data);
            if (count($track_data) >= 50) {
                db('package_tracking')->insertAll($track_data);
                $track_data = [];
            }
        });
        !empty($track_data) ? db('package_tracking')->insertAll($track_data) : true;
        usleep(1000 * 30);
    }
}