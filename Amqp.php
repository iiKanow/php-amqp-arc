<?php
/**
 * Created by PhpStorm.
 * User: kano
 * Date: 2019/6/21
 * Time: 下午3:28
 */
// namespace app\controller;
// use PhpAmqpLib\Connection\AMQPStreamConnection;
// use PhpAmqpLib\Message\AMQPMessage;

class Amqp
{

    const HOST = "localhost";
    const PORT = "5672";
    const USER = "guest";
    const PWD  = "guest";

    protected $channel = null;

    static $AmqpLibConnection = null;
    static $AmqpLibChannel = null;
    static $AmqpLibConsumerQueue = null;
    public $AmqpLibExchangeCurrentData = [];
    public $currentTopic = null;

    private $routings = [
        'consumer3' =>  'task1',
        'consumer4' =>  'task2'
    ];




    /**
     * Notes: 建立连接
     * User: kano
     * Date: 2019/6/25 12:09
     * Contact: wanggaofei1@58.com
     * @param string $routing
     * @return null|\PhpAmqpLib\Channel\AMQPChannel
     */
    public function amqpLibConnection($routing=false)
    {
        $this->currentTopic = $routing;

        if( !is_null(self::$AmqpLibConnection) )
        {
            if( is_null(self::$AmqpLibChannel) )
            {
                self::$AmqpLibConnection = self::$AmqpLibConnection->channel();
                return self::$AmqpLibChannel;
            }
            return self::$AmqpLibChannel;
        }

        require_once __DIR__.'/vendor/autoload.php';

        $connection = new PhpAmqpLib\Connection\AMQPStreamConnection(self::HOST, self::PORT, self::USER, self::PWD,
            '/');
        self::$AmqpLibConnection = $connection;
        self::$AmqpLibChannel = $connection->channel();
        //关闭心跳检测
        return self::$AmqpLibChannel;
    }


    /**
     * Notes: 发布消息
     * User: kano
     * Date: 2019/6/25 12:08
     * Contact: wanggaofei1@58.com
     * @param string $routing
     * @param array $data
     * @param bool $close
     */
    public function amqpLibPublish($routing='',$data=[],$close=true)
    {
        if (!$routing)
        {
            self::return_result([
                'state' =>  0 ,
                'msg'   =>  '发布消息失败了。路由名称为空',
                'say'   =>  'routing:'.$routing
            ]);
        }
        if( empty($data) || !is_array($data) )
        {
            self::return_result(array(
                'state' =>  0 ,
                'msg'   =>  '消息内容不能为空且只能为数组格式',
                'say'   =>  'routing:'.$routing.',data:',
            ));
        }
        $this->amqpLibConnection($routing);
        $this->AmqpLibExchangeCurrentData = $data;
        $this->logs('publish message... param:'.json_encode($data), 'info');
        $this->logs(json_encode($this->AmqpLibMessage()), 'info');
        self::$AmqpLibChannel->basic_publish( $this->AmqpLibMessage(),'',$this->currentTopic );

        if( $close )
        {
            $this->AmqpLibClose();
        }
    }


    /**
     * Notes: mq消息数据处理
     * User: kano
     * Date: 2019/6/25 12:08
     * Contact: wanggaofei1@58.com
     * @param array $init
     * @return AMQPMessage
     */
    public function amqpLibMessage($init=[])
    {
        //使消息持久化
        $default = array('content_type' => 'text/plain', 'delivery_mode' => PhpAmqpLib\Message\AMQPMessage::DELIVERY_MODE_PERSISTENT);
        $init = array_merge($default,$init);
        return new PhpAmqpLib\Message\AMQPMessage(json_encode(array(
            'DATA'  =>  $this->AmqpLibExchangeCurrentData
        )),$init);
    }



    /**
     * Notes: 关闭连接
     * User: kano
     * Date: 2019/6/25 12:08
     * Contact: wanggaofei1@58.com
     * @param int $channel
     * @param int $connection
     * @return bool
     */
    public function AmqpLibClose($channel=1,$connection=1)
    {
        if( $connection )
        {
            if( !is_null( self::$AmqpLibChannel ) )
            {
                self::$AmqpLibChannel->close();
                self::$AmqpLibChannel = null;
            }

            if( !is_null(self::$AmqpLibConnection) )
            {
                self::$AmqpLibConnection->close();
                self::$AmqpLibConnection = null;
            }
            return true;
        }

        if( $channel && !is_null( self::$AmqpLibChannel ) )
        {
            self::$AmqpLibChannel->close();
            self::$AmqpLibChannel = null;
        }
        return true;
    }


    /**
     * Notes: 注册消费者
     * User: kano
     * Date: 2019/6/25 12:07
     * Contact: wanggaofei1@58.com
     * @param string $func
     * @param string $routing
     * @param bool $debug
     */
    public function AmqpLibConsumerFactory($func='',$routing='',$debug=true)
    {
        if( empty($func) || empty($routing) )
        {
            self::return_result(array(
                'state' =>  0 ,
                'msg'   =>  '消费者实际调用的函数名不能为空且队列key必须存在',
            ));
        }

        $this->AmqpLibConnection($routing);
        //定义队列
        self::$AmqpLibChannel->queue_declare($routing, false, true, false, false, false);

        $this->AmqpLibConsumerCallback = $func;
        self::$AmqpLibConsumerQueue = $routing;
        $this->AmqpLibConsumerDebug = $debug;
        self::$AmqpLibChannel->basic_qos(null, 1, null); //我们可以使用basic.qos方法，并设置prefetch_count=1。这样是告诉RabbitMQ，再同一时刻，不要发送超过1条消息给一个工作者（worker），直到它已经处理了上一条消息并且作出了响应。这样，RabbitMQ就会把消息分发给下一个空闲的工作者（worker）。
        self::$AmqpLibChannel->basic_consume(self::$AmqpLibConsumerQueue, '', false, false, false, false, function($message) {
            echo '消费参数为：'.$message->body;
            $this->logs('消费参数：'.$message->body);
            $param = json_decode($message->body, true);

            if (!isset($param['DATA'])) {
                $param['DATA'] = array();
                $this->logs('当前消息为非法消息，不是程序发出的');
            }

//            $back = call_user_func($this->AmqpLibConsumerCallback, $param['DATA']);
            $back = self::posturl($this->AmqpLibConsumerCallback, $param['DATA']);
            echo '处理结果为：' . json_encode($back) . PHP_EOL;
            //一旦出错则会存储所有消息的信息。但是不应该阻塞当前消费者
            if (!isset($back['state']) || $back['state'] != 1) {

                echo '处理函数为：' . $this->AmqpLibConsumerCallback . PHP_EOL;
                echo '消费失败了,参数为:'.json_encode($param['DATA']);
                $this->logs('当前消费者，消费失败了,参数为:'.json_encode($param['DATA']));
                $this->logs('当前消费者，消费失败了,返回为:'.json_encode($back));
            }

            if (isset($back['must_stop'])) {
                die;
            }

            $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
        });


        register_shutdown_function(function ($a, $b) {
            $a->close();
            $b->close();
        }, self::$AmqpLibChannel, self::$AmqpLibConnection);
        //永久等待消息进行消费
        while (count(self::$AmqpLibChannel->callbacks)) {
            self::$AmqpLibChannel->wait();
        }
    }


    /**
     * Notes: 返回数据处理
     * User: kano
     * Date: 2019/6/25 12:09
     * Contact: wanggaofei1@58.com
     * @param $arr
     * @param bool $out
     * @return array
     */
    public function return_result($arr,$out=true)
    {
        static $result = array(
            'state' =>  0 ,
            'data'  =>  array(),
            'msg'   =>  '未知错误信息'
        );

        if($arr)
            $arr = array_merge($result,$arr);
        else
            $arr = $result;

        if( !$out )
        {
            return $arr;
        }
        echo json_encode($arr);
        die;
    }


    /**
     * Notes: post请求接口
     * User: kano
     * Date: 2019/6/25 12:05
     * Contact: wanggaofei1@58.com
     * @param $url
     * @param $data
     * @return mixed
     */
    public function posturl($url,$data){
        $data  = json_encode($data);
        $headerArray =array("Content-type:application/json;charset='utf-8'","Accept:application/json");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl,CURLOPT_HTTPHEADER,$headerArray);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        $this->logs('curl result:'.$output);
        return json_decode($output,true);
    }


    //初始化消费者和消息队列
    public function init()
    {
        self::amqpLibConnection();
        //启动消费者
        echo '=========================启动消费者如下=========================' . PHP_EOL;
        if ($this->routings) {
            foreach ($this->routings as $key=>$val) {
                //定义队列
                self::$AmqpLibChannel->queue_declare($key, false, true, false, false, false);
                echo '消费者'.$key.'已启动'.PHP_EOL;
            }
        }
        echo '消费者启动完毕'.PHP_EOL;

        // $amqp->AmqpLibClose();

        //启动消息队列
        exec('/bin/sh /users/kano/home/amqp/shell/consumers.sh yes', $res);
        echo '=========================启动消息队列如下=========================' . PHP_EOL;
        foreach ( $res as $value )
        {
            echo $value . PHP_EOL;
        }
        echo '消息队列启动完毕'.PHP_EOL;
    }

    public function logs($content)
    {
        $file = __DIR__.'/log.txt';
        $content = '['.date('Y-m-d H:i:s').'] '.$content.PHP_EOL;
        file_put_contents($file, $content,FILE_APPEND);
    }

    public function publish()
    {
        // $this->logs('hello log');
        $this->amqpLibPublish('consumer3', ['state'=>'1','data'=>'cute']);
    }

}
