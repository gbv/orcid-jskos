<?php

include 'vendor/autoload.php';

$client_id     = getenv('ORCID_CLIENT_ID');
$client_secret = getenv('ORCID_CLIENT_SECRET');

if (file_exists('credentials.php')) {
    include 'credentials.php';
    $client_id     = constant('ORCID_CLIENT_ID');
    $client_secret = constant('ORCID_CLIENT_SECRET');
}

$service = new ORCIDService($client_id, $client_secret);
\JSKOS\Server::runService($service);
