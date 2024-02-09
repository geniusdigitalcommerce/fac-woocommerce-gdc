<?php

namespace FacPayments\Repositories\Cache;

use FacPayments\Contracts\Repositories\ICacheRepository;

class JsonFileCacheRepository implements ICacheRepository{

    protected $cacheDir;
    protected $cacheMetaFile;
    protected $meta = [];
    protected $inMemoryCache = [];

    public function __construct(
        string $cacheDir = './__fc-cache'
    ){
        $this->cacheDir = $cacheDir;
        $this->cacheMetaFile = $cacheDir.'/meta.json';
        $this->ensureCacheDirExists();
        $this->meta = json_decode(file_get_contents($this->cacheMetaFile),true); 
        $this->clearExpired();  
    }

    protected function ensureCacheDirExists(){
        if(file_exists($this->cacheMetaFile))return true;
        @mkdir($this->cacheDir,0700,true);
        file_put_contents($this->cacheMetaFile,json_encode([]));
    }

    protected function getCacheKeyPath($key){
        $path = $this->cacheDir.'/'.sha1($key).'.cache';
        return $path;
    }

    protected function clearExpired(){
        $expiredCacheKeys = [];
        $currentTime = time();
        foreach($this->meta as $cacheKey => $expiredTime){
            if($expiredTime < $currentTime){
                $expiredCacheKeys[]=$cacheKey;
            }
        }
        foreach($expiredCacheKeys as $cacheKey){
            unset( $this->meta[$cacheKey]);
            file_put_contents($this->cacheMetaFile,json_encode($this->meta));
            if(file_exists($cacheKey))@unlink($cacheKey);
        }
    }

    public function put($key,$value,$timeInSeconds=10){
        $cacheKey = $this->getCacheKeyPath($key);

        if($timeInSeconds <=0){
            $this->forget($key);
        }else{
            $this->meta[$cacheKey]=time()+$timeInSeconds;
            file_put_contents($cacheKey,serialize($value));
            file_put_contents($this->cacheMetaFile,json_encode($this->meta));
        }   
        $this->clearExpired();
    }

    public function get($key,$defaultValue=null){
        $cacheKey = $this->getCacheKeyPath($key);
        if(
            isset($this->meta[$cacheKey]) && $this->meta[$cacheKey] > time() && file_exists($cacheKey)
        ){
            return @unserialize(file_get_contents($cacheKey));
        }
        return $defaultValue;
    }
    public function forget($key){
        $cacheKey = $this->getCacheKeyPath($key);
        unset( $this->meta[$cacheKey]);
        file_put_contents($this->cacheMetaFile,json_encode($this->meta));
        if(file_exists($cacheKey))@unlink($cacheKey);
    }

    public function clear(){
        if(file_exists($this->cacheDir)){
            array_map('unlink', glob($this->cacheDir."/*.*"));
            rmdir($this->cacheDir);
        }
    }
}