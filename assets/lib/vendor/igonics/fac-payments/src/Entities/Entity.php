<?php

namespace FacPayments\Entities;

abstract class Entity {
    public function __construct(
        array $config = [],
        array $propertiesToExclude=[],
        $allowAdditionalProperties = false
    ){
        foreach($config as $name => $value){
            if(
                (
                    property_exists($this,$name) ||
                    property_exists($this,lcfirst($name)) ||
                    method_exists(
                        $this,
                        'set'.ucfirst($name)
                    )
                ) &&
                !in_array($name,$propertiesToExclude)
            ){
                if(
                    method_exists(
                        $this,
                        'set'.ucfirst($name)
                    )
                ){
                    $this->{'set'.ucfirst($name)}($value);
                }else{
                    if(property_exists($this,$name)){
                        $this->{$name}=$value;
                    }else{
                        $this->{lcfirst($name)}=$value;
                    }
                    
                }
                
            }else if(
                $allowAdditionalProperties && 
                !in_array($name,$propertiesToExclude)
            ){
                $this->{$name}=$value;
            }
        }
    }

    public function toArray($forcePascalCase=false){
        $data = [];
        
        foreach(get_object_vars($this) as $property => $value){
           
            if($value && is_a($value,Entity::class)){
                $value = $value->toArray($forcePascalCase);
            }
            if(!empty($value) || is_bool($value) || $value === 0){
                $data[ $forcePascalCase ? ucfirst($property) : $property] =  $value;
            }
        }
        return $data;
    }
    
}