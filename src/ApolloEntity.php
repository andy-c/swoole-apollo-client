<?php

namespace ApolloService;

class ApolloEntity
{
   private  $config_server_url="http://yourapolloaddress.com";
   private  $appId="apolloclient";
   private  $clusterName="default";
   private  $namespaceName="application";
   private  $ip=null;
   private  $notifications = ["application"=>["namespaceName"=>"application","notificationId"=>-1]];
   private  $pullTimeout = 10;
   private  $intervalTimeout = 60;
   private  $cacheDir = "/tmp/apollo/";
   private  $userCallBack=null;
   private  $logfile = "/tmp/apollo/apollo-applicaiton.log";

   private static $instance = null;

   private function __construct(){}

   public static function init():self{
   	  if(!self::$instance){
   	  	  self::$instance = new self();
   	  }

   	  return self::$instance;
   }
   
   public function __set(string $property,string $val){
   	$this->{$property} = $val;
   }

   public function __get(string $property):string{
   	 return $this->{$property};
   }
   
   /**
    * 查询单个namespace的数据(无缓存)
    * @author andy
    * @since  2019/7/29
    */
   public function getSingleNameSpaceInfo(string $namespace="application"):array{
       $url = rtrim($this->config_server_url,"/")."/configs/".$this->appId."/".$this->clusterName."/".$namespace;
       $params = [];
       //ip config
       if($this->ip){
       	  $params['ip'] = $this->ip;
       }
       //release key config
       $releaseKey = $this->getReleaseKey($namespace);
       if(!empty($releaseKey)){
       	  $params['releaseKey'] = $releaseKey['releaseKey']; 
       }

       $url .= "?".http_build_query($params);
       //do http request
       $result = HttpRequest::HttpDoGet(['url'=>$url,'timeout'=>$this->pullTimeout]);
   	   $httpCode = $result['httpCode'];
   	   $res = [];
   	   if($httpCode==200){
   	   	   $res = json_decode($result['content'],true);
   	   	   $filename = rtrim($this->cacheDir,"/")."/".$this->appId."/apollo_cache_".$namespace.".json";
   	   	   if(!file_exists($filename)){
   	   	   	  $dir = dirname($filename);
   	   	   	  if(!is_dir($dir)){
   	   	   	  	  mkdir($dir,0777,true);
   	   	   	  }
   	   	   }

   	   	   $flag = file_put_contents($filename,$result['content'],LOCK_EX);
   	   	   if(!$flag){
               $this->saveLog("save content failed!");
   	   	   }
   	   }

   	   return $res;
   }
   //获取对应配置的filename
   private function getFileNameForNameSpace(string $namespace):string{
   	 if($namespace){
   	 	$filename = rtrim($this->cacheDir,"/")."/".$this->appId."/apollo_cache_".$namespace.".json";
   	 	return $filename;
   	 }
   	 return "";
   }
   //获取releaseKey
   private function getReleaseKey(string $namespace):array{
   	  $filename = $this->getFileNameForNameSpace($namespace);
   	  if(file_exists($filename)){
          $info = json_decode(file_get_contents($filename),true);
          return $info;
   	  }

   	  return [];
   }

   /**
    * 批量获取apollo配置
    */
   public function getAllNameSpaceInfo(array $configs) : array{
       if(!$configs) return [];
       $response_list = HttpRequest::HttpMultiDoGet($configs);
       if(!empty($response_list)){
       	  foreach($response_list as $namespace => $resp){
       	  	  if($resp['httpCode'] == 200){
                  $result = json_decode($resp['content'],true);
                  $cache_file = $this->getFileNameForNameSpace($namespace); 
                  if(!file_exists($cache_file)){
                  	  $dir = dirname($cache_file);
                  	  if(!is_dir($dir)){
                  	  	  mkdir($dir,0777,true);
                  	  }
                  }
                  $flag = file_put_contents($cache_file,$resp['content'],LOCK_EX);
                  if(!$flag){
                  	  $this->saveLog("save content failed!");
                  }
       	  	  }else if($resp['httpCode'] == 304){
       	  	  	//nothing to change
       	  	  }else{
       	  	  	 $this->saveLog("pull failed!");
       	  	  }
       	  }
       }
       return $response_list;
   }

   /**
    * 定时拉取更新数据
    */
   public function listenChange(){
      $url = rtrim($this->config_server_url,"/")."/notifications/v2?";
      $params['appId'] = $this->appId;
      $params['cluster'] = $this->clusterName;
      $params['notifications'] = json_encode(array_values($this->notifications));
      $url.=http_build_query($params);
      $response = HttpRequest::HttpDoGet(['url'=>$url,"timeout"=>$this->intervalTimeout]);
      if($response['httpCode'] == 200){
           $result = json_decode($response['content'],true);
           $changelist = [];
           foreach($result as $k => $v){
              if($v['notificationId']!= $this->notifications[$v['namespaceName']]['notificationId']){
                  $changelist[$v['namespaceName']] = ['namespace'=>$v['namespaceName'],'url'=>rtrim($this->config_server_url,"/")."/configs/".$this->appId."/".$this->clusterName."/".$v['namespaceName'],"notificationId" => $v['notificationId']];
              }
           }
           //獲取最新的更新明細
           $response = $this->getAllNameSpaceInfo($changelist);
           if(!empty($response)){
              foreach($response as $key => $val){
                 if($val){
                  $this->notifications[$key]['notificationId'] =$changelist[$key]['notificationId']; 
                 }
              }
           }
           //是否回调用户空间
           ($this->userCallBack instanceof \Closure) && call_user_func($this->userCallBack);

      }else{
          $this->saveLog("pull the notifications failed! run again","listen-process");
      }
   }

   /**
    * 定时拉取全量的数据
    */
   public function getApolloInfoInTimer(){
      if(!empty($this->notifications)){
           $allNameSpaceInfo = [];
           foreach($this->notifications as $k => $v){
                $allNameSpaceInfo[$k]['url'] = rtrim($this->config_server_url,"/")."/configs/".$this->appId."/".$this->clusterName."/".$v['namespaceName'];
                $allNameSpaceInfo[$k]['timeout'] = 10;
                $allNameSpaceInfo[$k]['namespace'] = $v['namespaceName'];
           }

           $response = $this->getAllNameSpaceInfo($allNameSpaceInfo);
           //如果设置回调，则调用
           if($response){
              ($this->userCallBack instanceof \Closure) && call_user_func($this->userCallBack);
           }else{
              $this->saveLog("pull the apollo info failed!","timer-process");
           }
      }
   }

   /**
    * record log
    */
   public function saveLog($msg,$process_name="none"){
     if(isset($msg)){
          $datetime = date("Y-m-d H:i:s");
          file_put_contents((string)$this->logfile,"occur time:".$datetime."|process name:".$process_name."| msg content: ".$msg."\n",FILE_APPEND|LOCK_EX);
         }
   }
}
