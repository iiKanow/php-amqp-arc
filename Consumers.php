<?php
/**
 * Created by PhpStorm.
 * User: kano
 * Date: 2019/6/25
 * Time: 14:46
 */
require_once __DIR__.'/Amqp.php';

class Consumers extends Amqp
{
    private $self_url = 'http://test/amqp/index.php?class=tasks&func=';
    private $amqpObj;
    private $routings = [
        'consumer3' =>  'task1',
        'consumer4' =>  'task2'
    ];
    public function __construct()
    {
        $this->amqpLibConnection();
    }

    //消费者1
    public function consumer3()
    {
    	$this->logs('this is consumer3 received msg.');
        $this->AmqpLibConsumerFactory($this->self_url.$this->routings['consumer3'], 'consumer3');
    }


    //消费者2
    public function consumer4()
    {
    	$this->logs('this is consumer4 received msg.');
        $this->AmqpLibConsumerFactory($this->self_url.$this->routings['consumer4'], 'consumer4');
    }

}
