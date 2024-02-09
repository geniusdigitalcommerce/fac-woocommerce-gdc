<?php

namespace FacPayments\External\Helpers;

use SimpleXMLElement;

class Xml {
    /**
     * Updates xml element with data within array
     */
    public static function updateXMLWithArray(SimpleXMLElement &$xmlParent,array $data) {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (!is_numeric($key)) {
                    $subNode = $xmlParent->addChild("$key");
                    static::updateXMLWithArray($subNode,$value );
                } else {
                    $subNode = $xmlParent->addChild("item$key");
                    static::updateXMLWithArray($subNode,$value);
                }
            } else {
                $xmlParent->addChild("$key", htmlspecialchars("$value"));
            }
        }
        return $xmlParent;
    }
}