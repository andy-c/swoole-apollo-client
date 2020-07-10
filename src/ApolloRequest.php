<?php


namespace ApolloService;


use ApolloService\Exceptions\ApolloException;
use ApolloService\Contract\RequestInterface;
use ApolloService\Helper\Helper;
use Swoole\Coroutine\Http\Client;
use Swoole\Coroutine\WaitGroup;
use Swoole\Coroutine\System;
use function apc_store;
use function apcu_fetch;


class ApolloRequest implements RequestInterface
{
    /**
     * @var const string
    */
    const HOST_TO_IP="IP_STORE";

    /**
     * @inheritDoc
     */
    public function request(string $uri, array $options, int $timeout,string $host,int $port): array
    {
        try {
            $query = $options['query'] ?? [];
            if (!empty($query)) {
                $query = http_build_query($query);
                $uri   = sprintf('%s?%s', $uri, $query);
            }
            // Request
            $client = new Client($host, $port);
            $client->set(['timeout' => $timeout]);
            $client->get($uri);
            $body   = $client->body;
            $status = $client->statusCode;
            $client->close();

            // Not update empty body
            if (!empty($body)) {
                $body = json_decode($body, true);
            }

            if ($status == -1 || $status == -2 || $status == -3) {
                throw new ApolloException(
                    sprintf(
                        'Request timeout!(host=%s, port=%d timeout=%d)',
                        $host,
                        $port,
                        $timeout
                    )
                );
            }

            if ($status != 200 && $status != 304) {
                $message = $body['message'] ?? '';
                throw new ApolloException(sprintf('Apollo server error is %s', $message));
            }
        } catch (Throwable $e) {
            throw new ApolloException(sprintf('Apollo(%s) pull fail!(%s)', $uri, $e->getMessage()));
        }

        // Not update return empty
        if ($status == 304) {
            return [];
        }
        return $body;
    }

    /**
     * @inheritDoc
     */
    public function requestBatch(array $requests): array
    {
       $result = [];
       if(empty($requests)) return $result;
       $wg = new  WaitGroup();
       foreach($requests as $key => $req){
          $wg->add();
          go(function() use ($wg,&$result,$req) {
              try {
                 $body = $req();
                 $result[] = $body;
              } catch (ApolloException $ex) {
                  $result[] = false;
              }
              $wg->done();
          });
      }
      $wg->wait();
      return $result;
    }
}