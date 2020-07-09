<?php

use ApolloService\ApolloClient;
use ApolloService\ApolloInfo;
use ApolloService\ApolloRequest;
use ApolloService\ApolloConfig;

require_once '../vendor/autoload.php';

define("LOG_DIR","/opt/apollo");
define("FILE_DIR","/opt/apollo");
define("SWOOLE_LOG_FILE","/opt/apollo/swoole_log_file.log");

//isntance apolloInfo and apolloRequset
$apollo = new ApolloInfo();
$apollo->setHost("apollo.test.com");
$apollo->setPort("8090");
$apolloRequest = new ApolloRequest();
$apolloConfig = new ApolloConfig($apollo,$apolloRequest);
ApolloClient::getInstance()->start($apolloConfig);