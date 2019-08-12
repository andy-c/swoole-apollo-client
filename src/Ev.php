<?php

namespace ApolloService;

class Ev
{
	const TIMER = 256;
	const SIGNAL = 1024;
    
    private $timerEvents = [];
    private $signalEvents = [];

	public function addEvents(int $flag, array $params){
        switch ($flag) {
        	case self::TIMER:
                $timer = new \EvTimer($params['after'],$params['repeat'],$params['callback']);
                $this->timerEvents[]  = $timer;
        		break;
        	case self::SIGNAL:
                $signal = new \EvSignal($params['signum'],$params['callback']);
                $this->signalEvents[] = $signal;
        	    break;
        	default:
        		break;
        }
	}

	public function delEvents($flag){
        switch ($flag) {
        	case self::TIMER:
        	    foreach($this->timerEvents as $v){
        	    	$v->stop();
        	    }
        		break;
        	case self::SIGNAL:
        	    foreach($this->signalEvents as $val){
        	    	$val->stop();
        	    }
        	    break;
        	default:
        		break;
        }
	}

	public function loop(){
        \Ev::run();
	}
}