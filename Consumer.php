<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

define('HOST', 'localhost');
define('PORT', 5672);
define('USER', 'guest');
define('PASS', 'guest');
define('VHOST', '/');

class Consumer
{
    protected $conn;
    protected $ch;

    protected $exchange;
    protected $queue;
    protected $consumer_tag;

    protected $processing = false;
    protected $forceShutdown = false;

    public function __construct()
    {
        $exchange = 'router';
        $queue = 'msgs';
        $consumer_tag = 'consumer';

        $this->conn = new AMQPConnection(HOST, PORT, USER, PASS, VHOST);
        $this->ch = $this->conn->channel();
        $this->ch->queue_declare($queue, false, true, false, false);
        $this->ch->exchange_declare($exchange, 'direct', false, true, false);
        $this->ch->queue_bind($queue, $exchange);
        $this->ch->basic_consume($queue, $consumer_tag, false, false, false, false, [$this, 'processMessage']);

        pcntl_signal(SIGINT, [$this, 'handleSignal']);
    }

    function processMessage(AMQPMessage $msg)
    {
        echo 'Message received, processing for 10 seconds... ';

        // dodgy wait loop - sleep is interrupted by signals
        $endTime = microtime(true) + 10;
        while (microtime(true) < $endTime) {
            sleep(1);
        }

        echo "Done\n";
        $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);

        // also handle signals between messages
        pcntl_signal_dispatch();
    }

    public function consume()
    {
        while (count($this->ch->callbacks)) {
            $this->ch->wait();
        }
    }

    public function handleSignal($sig)
    {
        echo "\nCaught signal " . $sig . "\n";
        if (SIGINT == $sig) {
            $this->ch->basic_cancel($this->queue);
            $this->ch->close();
            $this->conn->close();
            exit();
        }
    }
}

$consumer = new Consumer();
$consumer->consume();
