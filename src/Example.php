<?php
/**
 *
 * @authors Rei (eva51513@gmail.com)
 * @date    2018-04-25 17:12:41
 * @version $Id$
 */
ini_set('max_execution_time', 0);
$service = new \track\PackageTrack;
$params  = [
    ['track_code' => 'PBID0629621001', 'carrier_code' => 'Parcelforce'],
    ['track_code' => 'EK430114492GB', 'carrier_code' => 'Parcelforce'],
    ['track_code' => 'EK430114489GB', 'carrier_code' => 'Parcelforce'],
    ['track_code' => 'CK182631281GB', 'carrier_code' => 'Parcelforce'],
    ['track_code' => 'EK430114206GB', 'carrier_code' => 'Parcelforce'],
    ['track_code' => 'EK430114529GB', 'carrier_code' => 'Parcelforce'],
    ['track_code' => 'EK430114515GB', 'carrier_code' => 'Parcelforce'],
    ['track_code' => 'EK430114501GB', 'carrier_code' => 'Parcelforce'],
    ['track_code' => 'S7F724218801000935105', 'carrier_code' => 'AuPost'],
    ['track_code' => '09445473208426', 'carrier_code' => 'DPD'],
    ['track_code' => '09445475175186', 'carrier_code' => 'DPD'],
    ['track_code' => '09445475175189', 'carrier_code' => 'DPD'],
    ['track_code' => 'CD840072254DE', 'carrier_code' => 'DHL'],
    ['track_code' => '61292700245241536831', 'carrier_code' => 'FEDEX'],
    ['track_code' => 'WIN0011GB40007580501', 'carrier_code' => 'FEDEX'],
    ['track_code' => '780654333389', 'carrier_code' => 'FEDEX'],
    ['track_code' => '589545128016', 'carrier_code' => 'AuPost'],
    ['track_code' => 'WIN0013GB400074792013', 'carrier_code' => 'P2P'],
    ['track_code' => 'WIN0015GB40007524101', 'carrier_code' => 'P2P'],
    ['track_code' => 'WIN0013GB40008235601', 'carrier_code' => 'P2P'],
    ['track_code' => 'S7F705762501000935109', 'carrier_code' => 'AuPost'],
    ['track_code' => 'S7F723952601000935101', 'carrier_code' => 'AuPost'],
    ['track_code' => '6435500131', 'carrier_code' => 'DHL'],
    ['track_code' => '7431718792', 'carrier_code' => 'DHL'],
    ['track_code' => '6345650135', 'carrier_code' => 'DHL'],
    ['track_code' => '6435497224', 'carrier_code' => 'DHL'],
    ['track_code' => 'CD840196280DE', 'carrier_code' => 'DHL(DE)'],
    ['track_code' => 'CD840057359DE', 'carrier_code' => 'DHL(DE)'],
    ['track_code' => 'CD840197784DE', 'carrier_code' => 'DHL(DE)'],
    ['track_code' => '15501720967246', 'carrier_code' => 'DPD(UK)'],
    ['track_code' => '15502117345661', 'carrier_code' => 'DPD(UK)'],
    ['track_code' => 'GD707686675WW', 'carrier_code' => 'TNT'],
    ['track_code' => 'GD705398827WW', 'carrier_code' => 'TNT'],
    ['track_code' => '8784310315987', 'carrier_code' => 'TOLL'],
    ['track_code' => '8784310326794', 'carrier_code' => 'TOLL'],
    ['track_code' => '8784310326331', 'carrier_code' => 'TOLL'],
    ['track_code' => '1ZE356F80319295946', 'carrier_code' => 'UPS'],
    ['track_code' => '1ZE356F80309893934', 'carrier_code' => 'UPS'],
    ['track_code' => '1ZE356F80331321447', 'carrier_code' => 'UPS'],
    ['track_code' => '1ZE335A70304620916', 'carrier_code' => 'UPS'],
    ['track_code' => '9102932508025307406837', 'carrier_code' => 'USPS'],
    ['track_code' => '9102932508025306856992', 'carrier_code' => 'USPS'],
    ['track_code' => '9400110895343094713407', 'carrier_code' => 'USPS'],
    ['track_code' => '9400110895343094663061', 'carrier_code' => 'USPS'],
    ['track_code' => '9400110895343088431362', 'carrier_code' => 'USPS'],
    ['track_code' => 'JJD0002246829183480', 'carrier_code' => 'Yodel'],
    ['track_code' => 'JJD0002246829182572', 'carrier_code' => 'Yodel'],
    ['track_code' => 'JJD0002246829185876', 'carrier_code' => 'Yodel'],
    ['track_code' => 'RC000883266DE', 'carrier_code' => 'DePost'],
];
$data = $service->execute($params);
var_dump($data);exit;
