<?php

// Usage:
// Cache::setEnv('production', BRIDGE_ENV_PROD);
// Cache::setCacheDirectory('/path/to/the/direcotry');
// Cache::use($time, [$api, 'endpoint', 'id' => 12], 'MyDataId');

namespace SouthCoast\Components;

class Cache
{
    protected static $CACHE_DIRECOTRY = null;
    protected static $ENV_DEV = true;
    protected static $ENV_PROD = false;
    protected static $force_update = false;

    const DEFAULT_EXPIRATION_TIME = self::EXPIRES_WEEK * 4;

    const EXPIRES_DAY = 86400;
    const EXPIRES_HOUR = 3600;
    const EXPIRES_WEEK = self::EXPIRES_DAY * 7;

    const FILENAME_SEPERATOR = ':';
    const FILE_EXTENTION = 'cache';

    /**
     * Caches the provided resource for developemnt 
     *
     * @param mixed $var            The refrence variable
     * @param mixed $provider       The to be cached data
     * @param string|int $id        The unique resource ID
     * @param int $expires          In expiration of the resource in Seconds
     * @return void
     */
    public static function use(&$var, $provider, $id, bool $force = null, int $expires = null)
    {        
        if (isset($expires)) {
            $id = $id . ':' . (time() + $expires);
        } else {
            $id = $id . ':' . (time() + self::DEFAULT_EXPIRATION_TIME);
        }

        if(!isset($force)) {
            $force = self::$force_update;
        }

        if (self::$ENV_DEV) {
            if (self::isCached($id, $location) && !$force) {
                $var = self::getCached('location', $location);
            } else {
                if (self::is_callable($provider)) {
                    extract(self::prepairCallableProvider($provider));
                    $resource = call_user_func_array(array($object, $method), $paramaters);
                } else {
                    $resource = $provider;
                }
                self::clearById($id);
                self::cache($resource, $id);
                $var = $resource;
            }
        } else {
            if (self::is_callable($provider)) {
                extract(self::prepairCallableProvider($provider));
                $var = call_user_func_array(array($object, $method), $paramaters);
            } else {
                $var = $provider;
            }
        }

        return $var;
    }

    public static function assign($provider, $id, bool $force = null, int $expires = self::DEFAULT_EXPIRATION_TIME)
    {
        $resource;
        self::use($resource, $provider, $id, $force, $expires);
        return $resource;
    }

    public static function useFunction(&$var,  $function, $id, bool $force = null, int $expires = self::DEFAULT_EXPIRATION_TIME)
    {
        /* TODO: BUILD THIS METHOD */

        if(!is_callable($function)) {
            throw new CacheError(CacheError::UNCALLABLE_FUNCTION);
        }

        if(self::$ENV_PROD) {
            return $function();
        }

        if(self::isCached($id, $location) && !$force) {
            
        }

    }

    public static function assignFunction($function, $id, bool $force = null, int $expires = self::DEFAULT_EXPIRATION_TIME)
    {
        $resource;
        self::useFunction($resource, $function, $id, $force, $expires);
        return $resource;
    }

    public static function prepairCallableProvider(array $provider)
    {
        return [
            'object' => array_shift($provider),
            'method' => array_shift($provider),
            'paramaters' => $provider
        ];
    }

    public static function is_callable($resource)
    {
        if(!is_array($resource)) {
            return false;
        }

        if(!is_object($resource[0]) || !is_string($resource[1])) {
            return false;
        }

        return (method_exists($resource[0], $resource[1])) ? true : false;
    }

    public static function clearById($id)
    {
        $cached = glob(self::path() . '*.' . self::FILE_EXTENTION);

        foreach ($cached as $file) {
            $string = str_replace(self::path() . '/', '', $file);
            if(substr($string, 0, strlen($id)) == $id) {
                if(!unlink($file)) {
                    return true;
                }
            }
        }

        return true;
    }

    public static function setEnv(string $type, bool $env)
    {
        if($type == 'devlopment') {
            self::$ENV_DEV = $env;
            self::$ENV_PROD = ($env) ? false : true;
            // $env = ($env) ? false : true;
        } elseif($type == 'production') {
            /* All good... no idea why im doing this.... */
            self::$ENV_PROD = $env;
            self::$ENV_DEV = ($env) ? false : true;
        } else {
            throw new CacheError(CacheError::UNKOWN_ENV_TYPE);
        }

        self::$ENV_DEV = ($env) ? false : true;
        self::$ENV_PROD = ($env) ? true : false;
    }

    public static function setDirectory(string $path)
    {
        if($path[strlen($path) -1] == '/')  {
            $path = rtrim($path, '/');
        }

        self::$CACHE_DIRECOTRY = $path;
    }

    public static function forceUpdate(bool $yes = true)
    {
        self::$force_update = $yes;
    }

    public static function isCached($id, &$location)
    {
        $cached = glob(self::path() . '/*.' . self::FILE_EXTENTION);

        foreach ($cached as $file) {
            $string = str_replace(self::path() . '/', '', $file);
            if(substr($string, 0, strlen($id)) == $id) {
                
                $str_array = explode(self::FILENAME_SEPERATOR, $string);

                /* Check if it's expired */
                if($str_array[1] >= time()) {
                    unlink($file);
                    return false;
                }
                
                $location = $file;
                return true;
            }
        }

        return false;
    }

    
    public static function getCached($type, $value)
    {
        switch ($type) {
            case 'location':
                $serialized = file_get_contents($value);
                $data = unserialize($serialized);
                break;
            
            case 'id':
                $cached = glob(self::path());
                foreach ($cached as $file) {
                    $string = str_replace(self::path() . '/', '', $file);
                    if(substr($string, 0, strlen($value)) == $value) {
                        $serialized = file_get_contents($file);
                        $data = unserialize($serialized);
                    }
                }
                break;
        }

        return (isset($data)) ? $data : null;
    }


    /**
     * Stores the 'to be cahced' data
     * 
     * TODO: MORE USEFULL ERRORS WHEN FAILED 
     *
     * @param mixed $resource
     * @param mixed $id
     * @param int $expires
     * @return boolean
     */
    public static function cache($resource, $id, $expires) : bool
    {
        /* Serialize the resource */
        $serialized = serialize($resource);
        /* Ensure the cache dir exists */
        self::ensureDir();
        /* Store the serialized contents  */
        $result = file_put_contents(self::path($id . '_+_' . (time() + $expires) . '.cache'), $serialized);
        /* Return a boolean based on if it was successfull */
        return ($result !== false) ? true : false;
    }

    public static function ensureDir() 
    {
        if(is_null(self::$CACHE_DIRECOTRY)) {
            throw new CacheError(CacheError::NO_DIRECTORY);
        }

        if(!file_exists(self::path())) {
            return mkdir(self::path(), 0777, true);
        }
    }

    public static function path(string $suffix = null) : string
    {
        if(is_null(self::$CACHE_DIRECOTRY)) {
            throw new CacheError(CacheError::NO_DIRECTORY);
        }

        return self::$CACHE_DIRECOTRY . (isset($suffix) ? '/' . $suffix : '');
    }

    public static function Flush()
    {
        self::rrmdir(self::$CACHE_DIRECOTRY);
    }

    public static function Empty() 
    {
        self::Flush();
    }

    protected static function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object))
                        self::rrmdir($dir . "/" . $object);
                    else
                        unlink($dir . "/" . $object);
                }
            }
            self::rmdir($dir);
        }
    }
}
