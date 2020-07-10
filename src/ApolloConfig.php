<?php
declare(strict_types=1);

namespace ApolloService;

use ApolloService\Contract\ConfigCenterInterface;
use ApolloService\Helper\Helper;
use Swoole\Timer;
use function sprintf;
use function apcu_store;


class ApolloConfig implements ConfigCenterInterface
{


    /**
     * @var apollo info
    */
    private $apolloInfo;

    /**
     * @var apollo request
    */
    private $request;

    /**
     * tickId
     * @var int
    */
    private $tickId;

    /**
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * @param bool $running
     */
    public function setRunning(bool $running): void
    {
        $this->running = $running;
    }

    /**
     * @var bool
    */
    private $running = false;


    public function __construct(ApolloInfo $apolloInfo,ApolloRequest $request)
    {
        $this->apolloInfo = $apolloInfo;
        $this->request = $request;
    }

    /**
     * @inheritDoc
     */
    public function pullWithCache(string $namespace, string $clientip): array
    {
        $appid       = $this->apolloInfo->getAppId();
        $clusterName = $this->apolloInfo->getClusterName();
        $timeout     = $this->apolloInfo->getPullTimeout();
        $host = $this->apolloInfo->getHost();
        $port = $this->apolloInfo->getPort();

        if (empty($clientIp)) {
            $clientIp = $this->getServerIp();
        }

        $options = [
            'query' => [
                'clientIp' => $clientIp
            ]
        ];

        $uri = sprintf('/configfiles/json/%s/%s/%s', $appid, $clusterName, $namespace);
        return $this->request->request($uri, $options, $timeout,$host,$port);

    }

    /**
     * @inheritDoc
     */
    public function pullWithOutCache(string $namespace, string $releaseKey = '', string $clientip): array
    {
        $appid       = $this->apolloInfo->getAppId();
        $clusterName = $this->apolloInfo->getClusterName();
        $timeout     = $this->apolloInfo->getPullTimeout();
        $host = $this->apolloInfo->getHost();
        $port = $this->apolloInfo->getPort();

        if (empty($clientIp)) {
            $clientIp = $this->getServerIp();
        }

        // Client ip and release key
        $query['clientIp'] = $clientIp;
        if (!empty($releaseKey)) {
            $query['releaseKey'] = $this->getReleaseKey($namespace);
        }

        $options = [
            'query' => $query
        ];

        $uri = sprintf('/configs/%s/%s/%s', $appid, $clusterName, $namespace);
        return $this->request->request($uri, $options, $timeout,$host,$port);
    }

    /**
     * @inheritDoc
     */
    public function pullBatch(array $namespaces, string $clientip): array
    {
        $requests = [];
        foreach ($namespaces as $namespace) {
            $requests[$namespace] = function () use ($namespace, $clientip) {
                return $this->pullWithOutCache($namespace, '', $clientip);
            };
        }
        return $this->request->requestBatch($requests, $this->apolloInfo->getPullTimeout());
    }

    /**
     * @inheritDoc
     */
    public function Listen(): void
    {
        $appid       = $this->apolloInfo->getAppId();
        $clusterName = $this->apolloInfo->getClusterName();
        $host = $this->apolloInfo->getHost();
        $port = $this->apolloInfo->getPort();
        $namespaces = $this->apolloInfo->getNamespace();
        $notifications = $this->apolloInfo->getNotifications();
        $clientip = $this->apolloInfo->getClientip();
        $timeout = $this->apolloInfo->getHoldTimeout();
        $callback = $this->apolloInfo->getCallback();
        // Client ip and release key
        $query['appId']   = $appid;
        $query['cluster'] = $clusterName;
        $this->running = true;//start to run

        // Init $notifications
        if (empty($notifications)) {
            foreach ($namespaces as $namespace) {
                $notifications[$namespace] = [
                    'namespaceName'  => $namespace,
                    'notificationId' => -1
                ];
            }
        }

        // start Long poll
        while ($this->running) {
            $updateNamespaceNames   = [];
            $query['notifications'] = json_encode(array_values($notifications));

            $options = [
                'query' => $query
            ];

            $result = $this->request->request('/notifications/v2', $options, $timeout,$host,$port);

            if (empty($result)) {
                continue;
            }

            foreach ($result as $nsNotification) {
                $namespaceName  = $nsNotification['namespaceName'];
                $notificationId = $nsNotification['notificationId'];

                // Update notifications
                $notifications[$namespaceName] = [
                    'namespaceName'  => $namespaceName,
                    'notificationId' => $notificationId
                ];
                $this->apolloInfo->setNotifications($notifications);
                $updateNamespaceNames[] = $namespaceName;
            }
            $updateConfigs = $this->pullBatch($updateNamespaceNames, $clientip);
            //update file and cache
            $this->updateFileAndCache($updateConfigs);
            //user callback
            if($callback) {$callback($updateConfigs);}
        }
        return;
    }

    /**
     * @inheritDoc
    */
    public function Timer():void {
        $callback = $this->apolloInfo->getCallback();
        $this->tickId = Timer::tick($this->apolloInfo->getTimer(),function() use ($callback){
            if(!$this->running){
                Timer::clear($this->tickId);
                return;
            }
            $appid       = $this->apolloInfo->getAppId();
            $clusterName = $this->apolloInfo->getClusterName();
            $host = $this->apolloInfo->getHost();
            $port = $this->apolloInfo->getPort();
            $namespaces = $this->apolloInfo->getNamespace();
            $notifications = $this->apolloInfo->getNotifications();
            $clientip = $this->apolloInfo->getClientip();
            $timeout = $this->apolloInfo->getHoldTimeout();
            $this->running = true;//start to run

            // Client ip and release key
            $query['appId']   = $appid;
            $query['cluster'] = $clusterName;

            // Init $notifications
            if (empty($notifications)) {
                foreach ($namespaces as $namespace) {
                    $notifications[$namespace] = [
                        'namespaceName'  => $namespace,
                        'notificationId' => -1
                    ];
                }
            }
            $updateNamespaceNames   = [];
            $query['notifications'] = json_encode(array_values($notifications));

            $options = [
                'query' => $query
            ];
            $result = $this->request->request('/notifications/v2', $options, $timeout,$host,$port);

            if (empty($result)) {
                return ;
            }

            foreach ($result as $nsNotification) {
                $namespaceName  = $nsNotification['namespaceName'];
                $notificationId = $nsNotification['notificationId'];

                // Update notifications
                $notifications[$namespaceName] = [
                    'namespaceName'  => $namespaceName,
                    'notificationId' => $notificationId
                ];
                $this->apolloInfo->setNotifications($notifications);

                $updateNamespaceNames[] = $namespaceName;
            }
            $updateConfigs = $this->pullBatch($updateNamespaceNames, $clientip);
            //update file and cache
            $this->updateFileAndCache($updateConfigs);
            //user callback
            if($callback) {$callback($updateConfigs);}
        });
    }

    /**
     * get server ip
    */
    private function getServerIp(){
        $list = swoole_get_local_ip();
        return $list['eth0'] ?? '127.0.0.1';
    }

    /**
     * set config to file
    */
    private function setConfigToFile(string $namespaceName,string $content):bool{
        $configFile = $this->getConfigPath($namespaceName);
        $dir = dirname($configFile);
        if(!is_dir($dir)){
            mkdir($dir,0777,true);
        }
        $length = file_put_contents($configFile.'.tmp',$content,LOCK_EX);
        if(strlen($content) == $length){
            return copy($configFile.'.tmp',$configFile) && unlink($configFile.'.tmp');
        }else{
            return false;
        }
    }

    /**
     * get config path
    */
    private function getConfigPath(string $namespaceName):string{
        return FILE_DIR.DIRECTORY_SEPARATOR.$this->apolloInfo->getAppId().'_'.
            'apollo_cache_'.$namespaceName.'.json';
    }

    /**
     * get config from file
    */
    private function getConfigFromFile(string $namespaceName):array{
        $file = $this->getConfigPath($namespaceName);
        $config = [];
        if(file_exists($file)){
            $content = file_get_contents($file);
            $config = $content ? json_decode($content,true) :[];
        }
        return $config;
    }

    /**
     * get releasekey
    */
    private function getReleaseKey(string $namespace):string{
        $releaseKey = '';
        $config = $this->getConfigFromFile($namespace);
        if(is_array($config) && isset($config['releaseKey'])){
            $releaseKey = $config['releaseKey'];
        }
        return $releaseKey;
    }

    /**
     * update file and cache
     * for cache pls use apcu store
    */
    private function updateFileAndCache(array $updateConfigs):void{
       $res =[];
       foreach($updateConfigs as $key => $val){
           $fileRes = $this->setConfigToFile($val['namespaceName'],@json_encode($val['configurations']));
           $cacheRes = apcu_store($val['namespaceName'],$val['configurations']);
           $res[$val['namespaceName']]['file'] = $fileRes;
           $res[$val['namespaceName']]['cache'] = $cacheRes;
       }
       Helper::getLogger()->info("update file and cache result ".json_encode($res).' at time '.date('Y-m-d H:i:s'));
    }

    /**
     * destruct
    */
    public function __destruct()
    {
        $this->apolloInfo = null;
        $this->request = null;
        $this->tickId = null;
    }
}