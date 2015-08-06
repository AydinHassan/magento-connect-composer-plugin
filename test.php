<?php

require __DIR__ . '/vendor/autoload.php';

$plugin = new \AydinHassan\MagentoConnectPlugin\Plugin();
var_dump($plugin->getVersionsForPackage('nosto_tagging'));


