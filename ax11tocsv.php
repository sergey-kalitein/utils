#!/usr/bin/env php
<?php

ini_set('memory_limit', '2G');

$nameInputFile = __DIR__ . "/WRCS.xml";
echo 'Loading the input source data from: ' . $nameInputFile . ' ...';
$xml = simplexml_load_file($nameInputFile, 'SimpleXMLElement', LIBXML_PARSEHUGE | LIBXML_NOBLANKS);
$json = json_decode(json_encode($xml), JSON_OBJECT_AS_ARRAY);
$vehiclesDetailsArray = $json['VehicleDetails'];
echo "Ok\n";

echo "Converting data..";
$handleCsv = fopen(__DIR__ . "/WRCS.csv", 'w+');

// put header
$arrayHeader = array_keys(reset($vehiclesDetailsArray));
$headerLength = count($arrayHeader);
fputs($handleCsv, '"' . join('","', $arrayHeader) . '"' . "\r\n");


// processing vehicle data
$vehicleCountProcessed = 0;
foreach ($vehiclesDetailsArray as $_v) {
    // fixing other fields
    if (is_array($_v['VehicleImages']) === true && array_key_exists('ImageUrl', $_v['VehicleImages']) === true) {
        if (is_array($_v['VehicleImages']['ImageUrl']) === true) {
            $_v['VehicleImages'] = join(';', $_v['VehicleImages']['ImageUrl']);
        }
    } else {
        $_v['VehicleImages'] = '';
    }
    if (is_array($_v['Equipment']) === true && array_key_exists('EquipmentName', $_v['Equipment']) === true) {
        if (is_array($_v['Equipment']['EquipmentName']) === true) {
            // print_r($_v['Equipment']['EquipmentName']);
            $_v['Equipment'] = join(',', array_map(function($equipmentValue) {
                if (is_array($equipmentValue)) {
                    return join(',', $equipmentValue);
                } else {
                    return $equipmentValue;
                }
            }, $_v['Equipment']['EquipmentName']));
        }
    } else {
        $_v['Equipment'] = '';
    }
    $_v = array_map(
        function($valueOfAttribute) {
            if (is_array($valueOfAttribute)) {
                return trim(strtr(reset($valueOfAttribute), ["\r" => '', "\n" => '', "\0" => '', '"' => '']));
            } else {
                return trim(strtr($valueOfAttribute, ["\r" => '', "\n" => '', "\0" => '', '"' => '']));
            }
        }, $_v
    );

    // filtering bad VINs
    $_v['Vin'] = trim(mb_strtoupper($_v['Vin']));
    if (strlen($_v['Vin']) !== 17) {
        echo "\n\033[0;31m[warning] skipping bad VIN: {$_v['Vin']}\e[0m\n";
        continue;
    }

    $lengthRecord = count($_v);
    if ($headerLength != $lengthRecord) {
        echo "\n\033[0;31mLine {$vehicleCountProcessed}: record length is {$lengthRecord} vs header's $headerLength:\n";
        print_r($_v);
        echo "\033[0m\n";
    } else {
        fputs($handleCsv, '"' . join('","',$_v) . '"' . "\r\n");
    }

    echo '►';
    $vehicleCountProcessed++;
}
fclose($handleCsv);

echo "\n__________________________\n\nDONE ("
    . $vehicleCountProcessed
    . ' records converted)';
