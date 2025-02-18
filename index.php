<?php
namespace App;

require_once __DIR__ . '/vendor/autoload.php';

use App\handleConfig;
use App\imageResize;
use Exception;

try {
    $config = new handleConfig('xml/myConfig.xml');
    echo $config->get('group.innergroup.value1');
    //echo $config->get('thumbnail.filters')[0];

    $imageResizer = new imageResize($config);
    $pathImage = $imageResizer->resize('gargantua.jpg', 'thumbnail');

    echo $pathImage;

} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}