<?php
declare(strict_types=1);

namespace ApolloService;

use ApolloService\Exceptions\ApolloException;
use ApolloService\Helper\Helper;
use Swoole\Process\Pool;
use Swoole\Coroutine;
use Swoole\Process;
use Throwable;
use Swoole\Server;
use function register_shutdown_function;
use function error_get_last;

class ApolloClient
{

    /**
     * version
    */
    const VERSION = "0.1";

    /**
     *  workerNum
    */
    private $workerNum =2;

    /**
     * long poll name
    */
    const LONG_PULL_PROCESS_NAME="pull_process";

    /**
     * cron process name
    */
    const CRON_PROCESS_NAME="cron_process";

    /**
     * instance
    */
    private static $instance;

    /**
     * pool instance
    */
    private $pool;

    /**
     * @var $apolloconfig
    */
    private $apolloConfig;

    /**
     * callable
    */

    public static function getInstance() : ApolloClient{
        if(!self::$instance){
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * worker start
    */
    private function onWorkerStart($pool,$workerId):void {
        //register shutdown handler and signal handler
        $this->handleShutdownAndSignal($workerId);
        if($workerId==0){
            //start listen process
            swoole_set_process_name(self::LONG_PULL_PROCESS_NAME);
            $this->apolloConfig->Listen(null);
        }else{
            //start timer process
            swoole_set_process_name(self::CRON_PROCESS_NAME);
            $this->apolloConfig->Timer(null);
        }
    }


    /**
     * worker stop
    */
    private function onWorkerStop($pool,$workerId) :void{
        if($workerId==0){
            Helper::getLogger()->info("long_pull process has exited ");
        }else{
            Helper::getLogger()->info( "cron_process has exited ");
        }
    }
    /**
     * start
    */
    public function start(ApolloConfig $apolloConfig):void{
        try{
            //init pool
            $this->pool = new Pool($this->workerNum,0,0,true);
            //set config
            Coroutine::set([
                'hook_flags' => SWOOLE_HOOK_FILE,
                'max_coroutine' =>3000,
                'stack_size' => 4096,
                'log_level'  =>SWOOLE_LOG_ERROR,
                'socket_connect_timeout' => 10,
                'socket_timeout' =>10,
                'dns_server' =>'8.8.8.8',
                'exit_condition' => function(){
                     return Coroutine::stats()['coroutine_num'] === 0;
                }
            ]);
            //inject apollo config
            $this->apolloConfig = $apolloConfig;
            //set master name
            swoole_set_process_name("apollo-client-master");
            //worker start
            $this->pool->on("WorkerStart",function($pool,$workerId){
                $this->onWorkerStart($pool,$workerId);
            });
            //worker stop
            $this->pool->on("WorkerStop",function($pool,$workerId){
                $this->onWorkerStop($pool,$workerId);
            });
            //start pool
            $this->pool->start();

        }catch(ApolloException $ex){
           Helper::getLogger()->error("pool start error is ".$ex->getMessage());
        }catch(Throwable $ev){
           Helper::getLogger()->error("pool start error is ".$ev->getMessage());
        }
    }

    /**
     * long pull process
     *
    */
    private function handleShutdownAndSignal($workerId){
        //register shutdown handler
        register_shutdown_function(function() use ($workerId){
            $errors = error_get_last();
              if($errors && (
                      $errors['type'] === \E_ERROR ||
                      $errors['type'] === \E_PARSE ||
                      $errors['type'] === \E_CORE_ERROR ||
                      $errors['type'] === \E_COMPILE_ERROR ||
                      $errors['type'] === \E_RECOVERABLE_ERROR
                  )){
                $mess = $errors['message'];
                $file = $errors['file'];
                $line = $errors['line'];
                $errMsg = "error occured :".$errors["type"].":".$mess."in ".$file."on the ".$line." in workerid ".$workerId;
                Helper::getLogger()->error($errMsg);
            }
        });
        //handler signal
        process::signal(\SIGTERM,function($signo){
            Helper::getLogger()->error("process has been killed by signo ".$signo);
            exit(0);
        });
    }
}