<?php
require 'WmsApi.php';
$wmsApi = new WmsApi();
$wmsApi->apiRequest->setGetVar($_GET);
$wmsApi->readRequest();
$wmsApi->generateResponse();
$wmsApi->sendResponse();
