<?php

include __DIR__.'/../vendor/autoload.php';

$client_id     = getenv('ORCID_CLIENT_ID');
$client_secret = getenv('ORCID_CLIENT_SECRET');

if (file_exists('credentials.php')) {
    include 'credentials.php';
    $client_id     = constant('ORCID_CLIENT_ID');
    $client_secret = constant('ORCID_CLIENT_SECRET');
}

$service = new ORCIDService($client_id, $client_secret);
$server = new JSKOS\Server($service);
$response = $server->queryService($_GET, $_SERVER['PATH_INFO'] ?? '');
JSKOS\Server::sendResponse($response);
