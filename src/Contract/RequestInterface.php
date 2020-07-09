<?php
declare(strict_types=1);

namespace ApolloService\Contract;

use ApolloService\Exceptions\ApolloException;

/**
 * Interface RequestInterface
*/
interface RequestInterface
{
   /**
    * single request
    *
    * @param string $uri
    * @param array $options
    * @param int $timeout
    * @param string $host
    * @param int $port
    *
    * @return array
    * @throws Exception
   */
   public function request(string $uri,array $options,int $timeout,string $host,int $port):array;

   /**
    * batch request
    *
    * @param array $requests
    * @param int $timeout
    *
    * @return array
    * @throws Exception
   */
   public function requestBatch(array $requests):array;

   /**
    * resolve host
    *
    * @param string $host
    * @param int $timeout
    *
    * @return string
    * @throws ApolloException
   */
   public function resolveHost(string $host,int $timeout):string;
}