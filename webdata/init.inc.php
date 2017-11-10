<?php

error_reporting(E_ALL ^ E_STRICT ^ E_NOTICE);

include(__DIR__ . '/stdlibs/pixframework/Pix/Loader.php');
set_include_path(__DIR__ . '/stdlibs/pixframework/'
    . PATH_SEPARATOR . __DIR__ . '/models'
);

Pix_Loader::registerAutoLoad();

if (file_exists(__DIR__ . '/config.php')) {
    include(__DIR__ . '/config.php');
}

Pix_Table::setLongQueryTime(3);
// TODO: 之後要搭配 geoip
date_default_timezone_set('Asia/Taipei');

Pix_Table_Db::addDbFromURI(getenv('DATABASE_URL'));
Pix_Table::addStaticResultSetHelper('Pix_Array_Volume');

