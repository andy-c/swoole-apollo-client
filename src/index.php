<?php

use ApolloService\ApolloClient;
use ApolloService\ApolloInfo;
use ApolloService\ApolloRequest;
use ApolloService\ApolloConfig;

require_once '../vendor/autoload.php';

define("LOG_DIR","/opt/apollo");
define("FILE_DIR","/opt/apollo");

//instance apolloInfo and request
$apolloRequest = new ApolloRequest();
$apollo = new ApolloInfo();
$apollo->setHost("apollo.com");
$apollo->setPort("8090");
//inject
ApolloClient::getInstance()->start(new ApolloConfig($apollo,$apolloRequest));
