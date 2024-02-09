<?php

namespace FacPayments\External\Helpers;

class Arr {
    /**
     * Performs a nested assignment on an array
     * 
     * @param array $originalArray - Array to assign to
     * @param string $nestedKey - dot delimited key name
     * @param mixed $value - value to be assigned
     * @return array Returns original array
     */
    public static function nestedAssignment(&$originalArray,$nestedKey,$value){
        $nestedKeyParts = explode('.',$nestedKey);
        $noParts = count($nestedKeyParts);
        if(is_array($originalArray)){
            $currentArray = &$originalArray;
            for($i=0;$i<$noParts;$i++){

                if($i==$noParts-1){
                    $currentArray[$nestedKeyParts[$i]] = $value;
                    break;
                }
                //does this key already exist
                if(
                    !isset($currentArray[$nestedKeyParts[$i]]) ||
                    !is_array($currentArray[$nestedKeyParts[$i]])
                ){
                    $currentArray[$nestedKeyParts[$i]]=[];
                }
                $currentArray = &$currentArray[$nestedKeyParts[$i]];
            }
            
        }
        

        return $originalArray;
    }

    /**
     * Gets a nested assignment on an array
     * 
     * @param array $originalArray - Array to assign to
     * @param string $nestedKey - dot delimited key name
     */
    public static function getNestedAssignment(&$originalArray,$nestedKey,$defaultValue=null){
        $nestedKeyParts = explode('.',$nestedKey);
        $noParts = count($nestedKeyParts);
        if(is_array($originalArray)){
            $currentArray = &$originalArray;
            for($i=0;$i<$noParts;$i++){

                if($i==$noParts-1){
                    $defaultValue = isset($currentArray[$nestedKeyParts[$i]]) ? 
                                    $currentArray[$nestedKeyParts[$i]] : null;
                    break;
                }
                //does this key already exist
                if(
                    !isset($currentArray[$nestedKeyParts[$i]]) ||
                    !is_array($currentArray[$nestedKeyParts[$i]])
                ){
                    $currentArray[$nestedKeyParts[$i]]=[];
                }
                $currentArray = &$currentArray[$nestedKeyParts[$i]];
            }
        }
        

        return $defaultValue;
    }

    public static function get(&$originalArray,$nestedKey,$defaultValue=null){
        return static::getNestedAssignment($originalArray,$nestedKey,$defaultValue);
    }

    public static function withoutEmptyValues(array $originalArray=null){
        if(!$originalArray)return $originalArray;
        $newArray = [];
        foreach($originalArray as $key => $value){
            if(is_array($value)){
                $value = static::withoutEmptyValues($value);
            }
            if(!empty($value) || is_bool($value) || $value===0){
                $newArray[$key]=$value;
            }
        }
        return $newArray;

    }

    

    
}