<?php

namespace InitPHP\Redis;

use InvalidArgumentException;
use function extension_loaded;
use function is_int;
use function serialize;
use function time;
use function gettype;
use function unserialize;
use function array_merge;

class Redis
{
    protected array $_Options = [
        'prefix'        => 'cache_',
        'host'          => '127.0.0.1',
        'password'      => null,
        'port'          => 6379,
        'timeout'       => 0,
        'database'      => 0,
    ];

    protected \Redis $redis;

    public function __construct(array $options = [])
    {
        if(!extension_loaded('redis')){
            throw new \RuntimeException();
        }
        $this->_Options = array_merge($this->_Options, $options);
    }

    public function __destruct()
    {
        if(isset($this->redis)){
            unset($this->redis);
        }
    }

    /**
     * @return \Redis
     */
    public function getRedis(): \Redis
    {
        if(isset($this->redis)){
            return $this->redis;
        }
        $this->redis = new \Redis();
        try {
            if(!$this->redis->connect($this->getOption('host'), $this->getOption('port'), $this->getOption('timeout'))){
                throw new \Exception('Redis Cache connection failed.');
            }
            $password = $this->getOption('password', null);
            if($password !== null && !$this->redis->auth($password)){
                throw new \Exception('Redis Cache authentication failed.');
            }
            $database = $this->getOption('database', null);
            if($database !== null && !$this->redis->select($database)){
                throw new \Exception('Redis Cache : The database could not be selected.');
            }
        }catch (\RedisException $e) {
            $error = 'A redis exception is caught : ' . $e->getMessage();
            throw new \RuntimeException($error);
        }catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage());
        }
        return $this->redis;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed|null
     * @throws \RedisException
     */
    public function get(string $key, $default = null)
    {
        $name = $this->getOption('prefix') . $key;
        $this->validationName($name);
        if(($data = $this->getRedis()->get($name)) !== FALSE){
            $data = unserialize($data);
            if(isset($data['__cache_type'], $data['__cache_value'])){
                return $data['__cache_value'];
            }
        }
        return $this->reDefault($default);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int|null|\DateInterval $ttl
     * @return bool
     * @throws \RedisException
     */
    public function set(string $key, $value, $ttl = null): bool
    {
        if(($ttl = $this->ttlCalc($ttl)) === FALSE){
            return false;
        }
        $name = $this->getOption('prefix') . $key;
        $this->validationName($name);
        $type = gettype($value);
        switch($type){
            case 'array':
            case 'object':
            case 'boolean':
            case 'integer':
            case 'double':
            case 'string':
            case 'NULL':
                break;
            case 'resource':
            default:
                return false;
        }
        if(!($this->getRedis()->set($name, serialize(['__cache_type' => $type, '__cache_value' => $value])))){
            return false;
        }
        if($ttl !== null){
            $ttl = time() + $ttl;
            $this->getRedis()->expireAt($name, $ttl);
        }
        return true;
    }

    /**
     * @param string $key
     * @return bool
     * @throws \RedisException
     */
    public function delete(string $key): bool
    {
        $name = $this->getOption('prefix') . $key;
        $this->validationName($name);
        return $this->getRedis()->del($name) === 1;
    }


    /**
     * @return bool|\Redis
     * @throws \RedisException
     */
    public function clear()
    {
        return $this->getRedis()->flushDB();
    }

    /**
     * @param string $key
     * @return bool
     * @throws \RedisException
     */
    public function has(string $key): bool
    {
        $name = $this->getOption('prefix') . $key;
        $this->validationName($name);
        return ($this->getRedis()->exists($name) !== FALSE);
    }

    /**
     * @param string $name
     * @param int $offset
     * @return int|\Redis
     * @throws \RedisException
     */
    public function increment(string $name, int $offset = 1)
    {
        $name = $this->getOption('prefix') . $name;
        $this->validationName($name);
        return $this->getRedis()->incrBy($name, $offset);
    }

    /**
     * @param string $name
     * @param int $offset
     * @return int|\Redis
     * @throws \RedisException
     */
    public function decrement(string $name, int $offset = 1)
    {
        $name = $this->getOption('prefix') . $name;
        $this->validationName($name);
        return $this->getRedis()->incrBy($name, -($offset));
    }


    /**
     * @param string $name
     * @param string $chars
     * @return void
     * @throws InvalidArgumentException
     */
    protected function validationName($name, $chars = '{}()/\\@:')
    {
        if(strpbrk($name, $chars) !== FALSE){
            throw new InvalidArgumentException('Cache name cannot contain "' . $chars . '" characters.');
        }
    }

    /**
     * @param null|int|\DateInterval $ttl
     * @return null|int|false
     */
    protected function ttlCalc($ttl = null)
    {
        if($ttl === null){
            return $ttl;
        }
        if($ttl instanceof \DateInterval){
            $ttl = $ttl->format('U') - time();
        }
        if(!is_int($ttl)){
            throw new InvalidArgumentException("\$ttl can be an integer, NULL, or a \DateInterval object.");
        }
        if($ttl < 0){
            return false;
        }
        return $ttl;
    }

    protected function reDefault($default = null)
    {
        return is_callable($default) ? call_user_func_array($default, []) : $default;
    }

    private function getOption(string $key, $default = null)
    {
        return $this->_Options[$key] ?? $default;
    }

}
