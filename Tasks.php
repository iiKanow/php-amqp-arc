<?php
/**
 * Created by PhpStorm.
 * User: kano
 * Date: 2019/6/25
 * Time: 15:10
 */

require_once __DIR__.'/Amqp.php';

class Tasks extends Amqp
{
    private $param;

    public function __construct()
    {
        $postdata = file_get_contents("php://input");
        $data = stripcslashes($postdata);
        $this->logs('received msg.'.$data);
        $this->param = json_decode($data, true);
    }

    //消息队列1
    public function task1()
    {
        $this->return_result($this->param);
    }


    //消息队列2
    public function task2($param = array())
    {
        $this->logs('this is task2 received msg.'.json_encode($param));
        // $base = new Base();
        return $this->return_result([
            'state' => 1,
            'msg'   =>  'this is task2'
        ]);
    }
}
