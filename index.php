<?php
declare(strict_types=1);

use ApolloService\ApolloClient;
use ApolloService\ApolloInfo;
use ApolloService\ApolloRequest;
use ApolloService\ApolloConfig;

require_once './vendor/autoload.php';

define("LOG_DIR","/opt/apollo");
define("FILE_DIR","/opt/apollo");

//instance apolloInfo and request
$apolloRequest = new ApolloRequest();
$apollo = new ApolloInfo();
//inject
ApolloClient::getInstance()->start(new ApolloConfig($apollo,$apolloRequest));

