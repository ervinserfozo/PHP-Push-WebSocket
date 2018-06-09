<?php
/**
 * Created by PhpStorm.
 * User: ervin
 * Date: 6/9/18
 * Time: 11:02 AM
 */

namespace PushWebSocket;


class Response
{
    private $receiver;

    private $connectToReceiver;

    private $data;

    /**
     * Response constructor.
     * @param $receiver
     * @param $connectToReceiver
     * @param $data
     */
    public function __construct($receiver, $connectToReceiver, $data)
    {
        $this->receiver = $receiver;
        $this->connectToReceiver = $connectToReceiver;
        $this->data = $data;
    }


    /**
     * @return mixed
     */
    public function getReceiver()
    {
        return $this->receiver;
    }

    /**
     * @return mixed
     */
    public function getConnectToReceiver()
    {
        return $this->connectToReceiver;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }


}