<?php

namespace FacPayments\Entities\Responses;

use FacPayments\Entities\Entity;

class Response extends Entity {
    protected $success=false;
    protected $message;
    protected $data;

    public function setSuccess($value){
        $this->success = !!$value;
        return $this;
    }

    public function getSuccess(){
        return $this->success;
    }

    public function isSuccessful(){
        return $this->success;
    }

    public function setMessage($value){
        $this->message = $value;
        return $this;
    }

    public function getMessage(){
        return $this->message;
    }

    public function setData($value){
        $this->data = $value;
        return $this;
    }

    public function getData(){
        return $this->data;
    }

    
}