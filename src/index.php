<?php

use ApolloService\ApolloClient;
use ApolloService\ApolloInfo;
use ApolloService\ApolloRequest;
use ApolloService\ApolloConfig;

require_once '../vendor/autoload.php';

define("LOG_DIR","/opt/apollo");
define("FILE_DIR","/opt/apollo");

//isntance apolloInfo and apolloRequset
$apollo = new ApolloInfo();
$apollo->setHost("127.0.0.1");
$apollo->setPort("8090");
$apolloRequest = new ApolloRequest();
$apolloConfig = new ApolloConfig($apollo,$apolloRequest);
ApolloClient::getInstance()->start($apolloConfig);