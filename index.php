<?php

require_once __DIR__ . '/vendor/autoload.php';

use happybt\coop\IndexResource;
use rtens\domin\delivery\web\WebApplication;
use watoki\curir\WebDelivery;

WebDelivery::quickResponse(IndexResource::class,
    WebDelivery::init(null,
        WebApplication::init(function (WebApplication $app) {
        })));