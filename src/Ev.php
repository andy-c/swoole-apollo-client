<?php

namespace ApolloService;

class Ev
{
	const TIMER = 256;
	const SIGNAL = 1024;
    const IDLE = 8192;
    
    private $timerEvents = [];
    private $signalEvents = [];
    private $idleEvents = [];

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
            case self::IDLE:
                $idle = new \EvIdle($params['callback'],null,\Ev::MAXPRI);
                $this->idleEvents[] = $idle;
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
            case self::IDLE:
                foreach($this->idleEvents as $vl){
                    $vl->stop();
                }

        	default:
        		break;
        }
	}

	public function loop(){
        \Ev::run();
	}
}