#!/usr/bin/env php
<?php

use oat\oatbox\service\ServiceManager;
use oat\oatbox\action\ActionService;
use oat\taoAdvancedSearch\model\Search\Action\InitAdvancedSearch;

$params = $argv;
array_shift($params);

if (count($params) < 1) {
    echo 'Usage: ' . __FILE__ . ' TAOROOT [HOST] [PORT] [LOGIN] [PASSWORD]' . PHP_EOL;
    die(1);
}

require_once dirname(__FILE__) . '/../../tao/includes/raw_start.php';

$params[] = require dirname(__FILE__) . '/../config/index.conf.php';

$actionService = ServiceManager::getServiceManager()->get(ActionService::SERVICE_ID);
$factory = $actionService->resolve(InitAdvancedSearch::class);
$report = $factory->__invoke($params);
echo tao_helpers_report_Rendering::renderToCommandline($report);
