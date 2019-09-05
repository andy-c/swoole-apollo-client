<?php

namespace ApolloService;

class PHPApolloClient
{   
	private static $logfile = "/tmp/apollo/apollo-client.log"; //log file

	private static $pidfile = "/tmp/apollo/apollo.pid"; //pid file

	private static $run_status;//running status
    
    private static $masterPid;//master pid

	//process type
	private static $processList = [
		"Timer"=>[
	        "process_name" => "apollo-timer-process",
	        "process_type" => "timer",
	    ],
	    "Listen"=>[
	    	"process_name" => "apollo-listen-process",
	    	"process_type"=>"listen"
	    ]
   ];
   //daemonise
   private static $daemonise = false;

   //child process pid
   private static $childPid = [];

   //initial
   const APL_STARTING_STATUS = 0;
   //stop & restart
   const APL_STOP_STATUS = 1;
   //running
   const APL_RUNNING_STATUS = 2;
   
   //event loop
   private static $eventLoop =null;
   
   //timer event
   const TIMER_EVENT=256;
   //signal event
   const SIGNAL_EVENT=1024;
    
    //check env
   public static function init(){
        if(php_sapi_name()!="cli"){
        	exit("the apollo-client only run in the cli mode!");
        }else if(!extension_loaded("pcntl")&&!extension_loaded("posix")){
        	exit("pcntl & posix must be required");
        }else if(!version_compare(PHP_VERSION, '7.0.0',"ge")){
        	exit("php version must greater than 7.0.0");
        }
	}
    
    //handle command
	public static function handleCommand(){
         global $argv;
         //handle argvs
         $start_file = $argv[0];

         $commands = [
         	"start",
         	"stop",
         ];

         $usage = "Usage: php startfile <commands>\nCommands: \nstart:\t\tstart apollo-client in daemon mode\nstop:\t\tstop the apollo-client\n\n";
        if(!isset($argv[1])|| !in_array($argv[1],$commands)){
        	if(isset($argv[1])){
                exit("unknown command ".$argv[1]);
        	}
        	exit($usage);
        }

        switch ($argv[1]) {
        	case 'start':
        	    self::checkIsRunning();
        	    self::writeLog("php-apollo-client is starting");
        	    self::$run_status = self::APL_STARTING_STATUS;
        		break;
        	case 'stop':
        	    self::stopAll();
        	    break;
        	default:
        		break;
        }
	}

	//deamonise
	public static function deamonise(){
		umask(0);
        
        $pid = pcntl_fork();
        if($pid===-1){
        	exit("fork failed");
        }else if($pid>0){
             exit(0);//结束父进程
        }

        if(posix_setsid() === -1){
        	exit("setsid failed");
        }
        
        //fork again
        $pid = pcntl_fork();
        if($pid === -1){
        	exit("fork again failed");
        }else if($pid >0){
        	exit(0);
        }

        chdir("/");

        //master process
        if(!cli_set_process_title("apollo-master-process")){
        	throw new \RuntimeException("set master process name failed !",100003);
        }

	}

	//reset stdin stdout stderr
	public static function resetStd(){
		fclose(STDIN);
		fclose(STDOUT);
		fclose(STDERR);
	}

	//save master pid to file
	public static function savePidToFile(){
		self::$masterPid = posix_getpid();
        if (false === file_put_contents(self::$pidfile, self::$masterPid,LOCK_EX)) {
            self::writeLog("save pid failed");
        }
	}

	//handle the signal
	public static function loadSignal(){
   	   pcntl_signal(SIGINT, [self::class,"handleSignal"],false);
   	   pcntl_signal(SIGTERM,[self::class,"handleSignal"],false);
   	   pcntl_signal(SIGQUIT,[self::class,"handleSignal"],false);
   	   pcntl_signal(SIGUSR1,[self::class,"handleSignal"],false);
   	   pcntl_signal(SIGUSR2,[self::class,"handleSignal"],false);
	}

	//add event loop
	public static function initEventLoop(){
		self::$eventLoop = new Ev();
	}

	//handle the child signal
	public static function loadChildSignal(){
	   pcntl_signal(SIGINT,SIG_IGN,false);
   	   pcntl_signal(SIGTERM,SIG_IGN,false);
   	   pcntl_signal(SIGQUIT,SIG_IGN,false);
   	   pcntl_signal(SIGUSR1,SIG_IGN,false);
   	   pcntl_signal(SIGUSR2,SIG_IGN,false);
   	   self::$eventLoop->addEvents(1024,["callback" =>[self::class,"handleSignal"],"signum"=>SIGINT]);
   	   self::$eventLoop->addEvents(1024,["callback" =>[self::class,"handleSignal"],"signum"=>SIGTERM]);
   	   self::$eventLoop->addEvents(1024,["callback" =>[self::class,"handleSignal"],"signum"=>SIGQUIT]);
   	   self::$eventLoop->addEvents(1024,["callback" =>[self::class,"handleSignal"],"signum"=>SIGUSR1]);
   	   self::$eventLoop->addEvents(1024,["callback" =>[self::class,"handleSignal"],"signum"=>SIGUSR2]);
	}

	//handle the signal
	public static function handleSignal($signo){
		  $signo = $signo instanceof \EvSignal ? $signo->signum:$signo;
          switch ($signo) {
          	case SIGUSR1:
          	case SIGUSR2:
          	case SIGINT:
          	case SIGQUIT:
          	case SIGTERM:
          		if(self::$masterPid == posix_getpid()){
          			self::$run_status = self::APL_STOP_STATUS;
          			foreach(self::$childPid as $key => $val){
          				posix_kill($key, $signo);
          			}
          		}else{
          			self::processStop();
          		}
          		break;
          }
	}

	//create process
	public static function createProcess(){
		foreach(self::$processList as $val){
            self::forkOneProcess($val);
		}
	}

	//fork one process
	public static function forkOneProcess($process){
		$cid = pcntl_fork();
		if(-1===$cid){
			throw new \Exception("create child process failed!",100001);
		}else if($cid > 0){
			//record the pid
			self::$childPid[$cid] = $process;
		}else if($cid ===0){
			if(!cli_set_process_title($process['process_name'])){
				throw new \Exception("set child process name failed!",100002);
			}
			//record
			self::$childPid[posix_getpid()] = $process;
			//记录log
			register_shutdown_function([self::class,"recordErrorLog"]);
		
		    try{
				if($process['process_type'] == 'timer'){
					self::runTimer();
				}else if($process['process_type'] == "listen"){
					self::runListen();
				}
			}catch(\Throwable $ex){
				self::writeLog($ex->getMessage().'in'.$ex->getFile()."on".$ex->getLine());
			}
			//退出事件循环
			exit("quit eventloop");
		}else{
			throw new \Exception("fork failed !",100004);
		}
	}

	//monitor
	public static function monitor(){
		 self::$run_status = self::APL_RUNNING_STATUS;
		 self::writeLog("php-apollo-client has been started");
		 while(1){
		 	pcntl_signal_dispatch();
		 	$status = 0;
		 	$pid= pcntl_wait($status, WUNTRACED);
		 	pcntl_signal_dispatch();
		 	if($pid >0){
		 		if(count(self::$childPid)>0){
                   unset(self::$childPid[$pid]);
		 		}
		 	}

		 	//if all child is out and status is shunting down then exit master
		 	if(empty(self::$childPid)&&self::$run_status == self::APL_STOP_STATUS){
		 		self::writeLog("apollo-client shuts down");
		 		@unlink(self::$pidfile);
		 		exit(0);
		 	}
		 }
		 
	}

	//workStop
	public static function processStop(){
		$pid = posix_getpid();
		//timer process needs to delete the timer loop
		if(self::$childPid[$pid]['process_type'] == "timer"){
            self::$eventLoop->delEvents(self::TIMER_EVENT);
		}
		self::$eventLoop->delEvents(self::SIGNAL_EVENT);
		self::writelog(self::$childPid[$pid]['process_name']." has been stoped");
		exit(0);
	}

	//stop all process
	public static function stopAll(){
		$masterPid = is_file(self::$pidfile) ? file_get_contents(self::$pidfile):0;
		if(!$masterPid){
			throw new \Exception("apollo-client is not running,please see the command list");
		}
		self::writeLog("send kill signal to master");
		$killMaster = posix_kill($masterPid,SIGTERM);
		self::writeLog("kill master result is ".var_export($killMaster,true));
		$timeout = 10;
		$start_time = time();
		while(1){
			$master_status = posix_kill($masterPid, 0);
            if($master_status){
                 if(time()- $start_time >=$timeout){
                 	 self::writeLog("child stop failed");
                 	 exit(-1);
                 }
                //sleep well
                usleep(10000);
                continue;
            }
		    break;
		}
		//exit
		exit(0);
	}

	//check status
	public static function status(){
		$str = "php-apollo-client is running\n";
		$str.= "listen process|timer process\n";
	    exit($str);
	}

	//writelog
	public static function writeLog($msg){
         if(isset($msg)){
         	$datetime = date("Y-m-d H:i:s");
         	file_put_contents((string)self::$logfile,"occur time:".$datetime."| msg content: ".$msg."\n",FILE_APPEND|LOCK_EX);
         }
	}

	//record child process error
	public static function recordErrorLog(){
       //记录error log
        $error_last = error_get_last();
        if(!$error_last) return "";
        switch ($error_last["type"]) {
        	case E_ERROR:
        	case E_CORE_ERROR:
        	case E_PARSE:
        	case E_COMPILE_ERROR:
        	case E_CORE_WARNING:
        	case E_COMPILE_WARNING:
        	     $mess = $error_last['message'];
                 $file = $error_last['file'];
                 $line = $error_last['line'];
                 $errMsg = "error occured :".$error_last["type"].":".$mess."in ".$file."on the ".$line;
                 break;
                 self::writeLog($errMsg);
        }
	}

	//check is running
	public static function checkIsRunning(){
		$pid = file_get_contents(self::$pidfile);
		if($pid){
			exit("php-apollo-client has already started\n");
		}

		return true;
	}

	//run timer process
	public static function runTimer(){
		self::loadChildSignal();
		self::$eventLoop->addEvents(self::TIMER_EVENT,['after'=>1,'repeat'=>120,"callback"=>function(){
             ApolloEntity::init()->getApolloInfoInTimer();
		}]);

		self::$eventLoop->loop();
	}
    
    //run listen process
    public static function runListen(){
    	self::loadSignal();
    	while(1){
    		pcntl_signal_dispatch();
    		ApolloEntity::init()->listenChange();
    	}
    	
    }

	//run php-apollo-client
	public static function run(){
		self::init();
		self::handleCommand();
		self::deamonise();
		self::initEventLoop();
		self::savePidToFile();
		self::loadSignal();
		self::createProcess();
		self::resetStd();
		self::monitor();
	}
}