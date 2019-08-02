<?php

namespace ApolloService;

class HttpRequest
{
	private static $instance = null;

	public static function init(){
		if(!self::$instance){
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function HttpDoGet($options){
       $ch = curl_init();
       curl_setopt_array($ch, [
       	  CURLOPT_TIMEOUT=>isset($options['timeout'])?$options['timeout']:10,
       	  CURLOPT_HEADER=>false,
       	  CURLOPT_RETURNTRANSFER=>1,
       	  CURLOPT_URL=>$options['url']
       ]);

       $body = curl_exec($ch);
       $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
       $error = curl_error($ch);
       curl_close($ch);
       $result = [
       	  'httpCode' => $httpCode,
       	  'content'  => $body
       ];

       return $result;
	}

	//multi http request
	public function HttpMultiDoGet($options){
		if(empty($options)) return [];
		$multi_ch = curl_multi_init();
        //request list
        $request_list = [];
		foreach($options as $key => $val){
			$ch = curl_init();
			curl_setopt_array($ch,[
				CURLOPT_URL=>$val['url'],
				CURLOPT_TIMEOUT=>isset($val['timeout'])?$val['timeou']:10,
				CURLOPT_RETURNTRANSFER=>1,
				CURLOPT_HEADER=>false
			]);
            $request_list[$val['namespace']] =[];
            $request_list[$val['namespace']]['ch'] = $ch;
            curl_multi_add_handle($multi_ch, $ch);
		}

		//do request
		$active =  null;
		do{
           $mrc = curl_multi_exec($multi_ch, $active);
		}while($mrc == CURLM_CALL_MULTI_PERFORM);
        //if ok
        while($active && $mrc == CURLM_OK){
        	//阻塞在select系统调用
        	if(curl_multi_select($multi_ch)==-1){
        		usleep(100);
        	}
        	do{
        		$mrc = curl_multi_exec($multi_ch, $active);
        	}while($mrc == CURLM_CALL_MULTI_PERFORM);
        	
        }
 
        //do response
        $reponse_list = [];
        foreach($request_list as $namespace => $req){
            $result = curl_multi_getcontent($req['ch']);
            $httpCode = curl_getinfo($req['ch'],CURLINFO_HTTP_CODE);
            $error = curl_error($req['ch']);
            $response_list[$namespace]['content'] = $result;
            $response_list[$namespace]['httpCode'] = $httpCode;
            curl_close($req['ch']);
	    }
        //close fd
	    curl_multi_close($multi_ch);
	    return $response_list;
    }

}