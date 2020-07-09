<?php
declare(strict_types=1);

namespace ApolloService\Contract;

use ApolloService\Exceptions\ApolloException;

/**
 * Interface ConfigInterface
*/
Interface ConfigCenterInterface
{

    /**
     * pull with cache
     *
     * @param string $namespace
     * @param string $clientip
     *
     * @return array
     * @throws ApolloException
    */
    public function pullWithCache(string $namespace,string $clientip):array;


    /**
     * pull without cache
     *
     * @param string $namespace
     * @param string $clientip
     * @param string $releaseKey;
     *
     * @return array
     * @throws ApolloException
     */
    public function pullWithOutCache(string $namespace,string $releaseKey = '',string $clientip):array;

    /**
     * pull batch
     *
     * @param array $namespaces
     * @param string $clientip
     *
     * @return array
     * @throws ApolloException
    */
    public function pullBatch(array $namespaces,string $clientip):array;

    /**
     * listen
     *
     * @param callable $callback
     *
     * @return void
     * @throws ApolloException
    */
    public function Listen():void;

    /**
     * timer
     *
     * @param callable $callback
     *
     * @return void
     * @throws ApolloException
    */
    public function Timer():void;
}