<?php
declare(strict_types=1);

namespace ApolloService;


class ApolloInfo
{
    /**
     * @var string
     */
    private $host = '172.16.10.184';

    /**
     * @var int
     */
    private $port = 8080;

    /**
     * @var string
     */
    private $appId = 'jv_monitor';

    /**
     * @var string
     */
    private $clusterName = 'default';

    /**
     * Seconds
     *
     * @var int
     */
    private $pullTimeout = 6;

    /**
     * holdTimeout
     *
     * @var int
    */
    private $holdTimeout = 63;

    /**
     * timer
     *
     * @var int
    */
    private $timer = 120000;

    /**
     * clientip
     *
     * @var string
    */
    private $clientip="";

    /**
     * namespaces
     * @var array
    */
    private $namespace =["application"];

    /**
     * user callback
     * can use fastcgi ,http,any tcp protocal to send
     * the update config to user space
     *
     * @var callable
    */
    private $callback=null;

    /**
     * @return string
     */
    public function getClientip(): string
    {
        return $this->clientip;
    }

    /**
     * @return callable
     */
    public function getCallback(): ?callable
    {
        return $this->callback;
    }

    /**
     * @param callable $callback
     */
    public function setCallback(callable $callback): void
    {
        $this->callback = $callback;
    }

    /**
     * @param string $clientip
     */
    public function setClientip(string $clientip): void
    {
        $this->clientip = $clientip;
    }

    /**
     * @return array
     */
    public function getNamespace(): array
    {
        return $this->namespace;
    }

    /**
     * @param array $namespace
     */
    public function setNamespace(array $namespace): void
    {
        $this->namespace = $namespace;
    }

    /**
     * @return array
     */
    public function getNotifications(): array
    {
        return $this->notifications;
    }

    /**
     * @param array $notifications
     */
    public function setNotifications(array $notifications): void
    {
        $this->notifications = $notifications;
    }
    /**
     * notifications
     * @var array
    */
    private $notifications=[];

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @param string $host
     */
    public function setHost(string $host): void
    {
        $this->host = $host;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @param int $port
     */
    public function setPort(int $port): void
    {
        $this->port = $port;
    }

    /**
     * @return string
     */
    public function getAppId(): string
    {
        return $this->appId;
    }

    /**
     * @param string $appId
     */
    public function setAppId(string $appId): void
    {
        $this->appId = $appId;
    }

    /**
     * @return string
     */
    public function getClusterName(): string
    {
        return $this->clusterName;
    }

    /**
     * @param string $clusterName
     */
    public function setClusterName(string $clusterName): void
    {
        $this->clusterName = $clusterName;
    }

    /**
     * @return int
     */
    public function getPullTimeout(): int
    {
        return $this->pullTimeout;
    }

    /**
     * @param int $pullTimeout
     */
    public function setPullTimeout(int $pullTimeout): void
    {
        $this->pullTimeout = $pullTimeout;
    }

    /**
     * @return int
     */
    public function getHoldTimeout(): int
    {
        return $this->holdTimeout;
    }

    /**
     * @param int $holdTimeout
     */
    public function setHoldTimeout(int $holdTimeout): void
    {
        $this->holdTimeout = $holdTimeout;
    }

    /**
     * @return int
     */
    public function getTimer(): int
    {
        return $this->timer;
    }

    /**
     * @param int $timer
     */
    public function setTimer(int $timer): void
    {
        $this->timer = $timer;
    }
}