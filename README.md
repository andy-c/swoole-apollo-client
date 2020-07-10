# swoole-apollo-client
 
## 简介

swoole-apollo-client 是一款基于swoole process是实现的携程apollo客户端，继承了swoole协程的高性能特征
采用两个进程，一个实时监听，一个定时更新，互不影响，实现配置更新的稳定运行
默认采用守护进程的方式，可以结合swoole_server & systemd & supervisor 来实现长期运行，自动重启


## 功能

基本实现java版本的全部功能
- 内存存贮配置
- 文件存贮配置
- 配置更新实时变更到内存和文件

## 运行环境

- [PHP 7.1+](https://github.com/php/php-src/releases)
- [Swoole 4.4+](https://github.com/swoole/swoole-src/releases)
- [Composer](https://getcomposer.org/)
- [apcu](https://github.com/krakjoe/apcu)

## 运行
```
cd ./swoole-apollo-client/src && php index.php
```


## License

php-apollo-client is an open-source software licensed under the MIT
