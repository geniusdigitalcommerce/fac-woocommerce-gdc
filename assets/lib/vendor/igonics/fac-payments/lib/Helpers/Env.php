<?php

namespace FacPayments\External\Helpers;

class Env {

    protected static $envBasePath = __DIR__ ;

    public static function get($key,$defaultValue = null){
        $value = static::getFromDotFile($key);
        if(!$value){
            $value = static::getFromEnvVar($key);
        }
        return $value ?? $defaultValue;
    }

    public static function setEnvBasePath($path){
        static::$envBasePath = $path;
    }

    public static function getFromDotFile($key){
        $value = null;
        $dotFilePath = static::$envBasePath.'/.env';
        if(
            file_exists($dotFilePath)
        ){
            
            foreach(
                array_reverse(explode("\n",file_get_contents($dotFilePath))) as $line
            ){
                $linePieces = explode("=",$line);
                $noPieces = count($linePieces);
                if($noPieces>1){
                    if(strtolower($key) == strtolower(trim($linePieces[0]))){
                        $value = $linePieces[1];
                        for($i=2;$i<$noPieces;$i++)$value.=$linePieces[$i];
                        $value = trim(trim(trim($value),"\""),"'");
                        break;
                    }
                }
            }

        }
        return $value;
    }

    public static function getFromEnvVar($key){
        $value = null;
        if(isset($_ENV[$key])){
            $value = $_ENV[$key];
        }else if(isset($_SERVER[$key])){
            $value = $_SERVER[$key]; 
        }
        return $value;
    }
}