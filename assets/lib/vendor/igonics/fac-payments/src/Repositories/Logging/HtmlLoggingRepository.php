<?php

namespace FacPayments\Repositories\Logging;


class HtmlLoggingRepository extends BaseLoggingRepository{
    protected function printFormattedHtml($type,$message){
        $encodedMessage = htmlspecialchars(
            json_encode($message,JSON_PRETTY_PRINT)
        );
        ?>
        <hr />
        <pre>
        <?php echo $encodedMessage ?>
        </pre>
        
        <hr />
        <?php
    }
    function trace($message){ 
        if($this->shouldTrace())
            $this->printFormattedHtml(debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'],$message);
    }
    function info($message){
        if($this->shouldInfo())
            $this->printFormattedHtml(debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'],$message);
    }
    function debug($message){
        if($this->shouldDebug())
            $this->printFormattedHtml(debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'],$message);
    }
    function warn($message){
        if($this->shouldWarn())
            $this->printFormattedHtml(debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'],$message);
    }
    function error($message,\Exception $e=null){
        if($this->shouldError()){
            $this->printFormattedHtml(debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'],$message);
            if($e){
                $this->printFormattedHtml(debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'],$e);
            }
        }
            
    }
}