<?php

namespace bdk\Test\Debug\Mock;

use Psr\SimpleCache\CacheInterface;

/**
 * "Basic" in-memory cache implementation
 */
class SimpleCache implements CacheInterface
{
    /**
     * @var array
     */
    protected $items = array();

    /**
     * @var int memory limit in bytes
     */
    protected $limit = 0;

    /**
     * @var int
     */
    protected $size = 0;

    protected $lastGetInfo;

    /**
     * Constructor
     *
     * @param int|string $limit Memory limit in bytes (defaults to 10% of memory_limit)
     */
    public function __construct($limit = null)
    {
        if ($limit === null) {
            $phpLimit = \ini_get('memory_limit');
            $this->limit = $phpLimit <= 0
                ? PHP_INT_MAX
                : (int) ($this->shorthandToBytes($phpLimit) / 10);
            return;
        }
        $this->limit = $this->shorthandToBytes($limit);
    }

    public function nonInterfaceMethod($arg1 = null)
    {
        if ($arg1 === 'throw') {
            throw new \RuntimeException('something went wrong');
        }
        return null;
    }

    public function get($key, $default = null)
    {
        $this->resetLastGetInfo($key);
        if (!isset($this->items[$key])) {
            return false;
        }
        $item = $this->items[$key];
        $rand = \mt_rand() / \mt_getrandmax();    // random float between 0 and 1 inclusive
        $isExpired = $item['e'] && $item['e'] < \microtime(true) - $item['ct'] / 1000000 * \log($rand);
        $this->lastGetInfo = \array_merge($this->lastGetInfo, array(
            'calcTime' => $item['ct'],
            'code' => 'hit',
            'expiry' => $item['e'],
            'token' => \md5($item['v']),
        ));
        if ($isExpired) {
            $this->lastGetInfo['code'] = 'expired';
            $this->lastGetInfo['expiredValue'] = \unserialize($item['v']);
            return false;
        }
        return \unserialize($item['v']);
    }

    public function set($key, $value, $ttl = null)
    {
        $expire = $this->expiry($ttl);
        $this->size -= isset($this->items[$key])
            ? \strlen($this->items[$key]['v'])
            : 0;
        $this->items[$key] = array(
            'v' => \serialize($value),
            'e' => $expire,
            'ct' => $this->lastGetInfo['key'] === $key
                ? (\microtime(true) - $this->lastGetInfo['microtime']) * 1000000
                : null,
        );
        $this->size += \strlen($this->items[$key]['v']);
        $this->lru($key);
        $this->evict();
        return true;
    }

    public function delete($key)
    {
        $exists = $this->has($key);
        if ($exists) {
            $this->size -= \strlen($this->items[$key]['v']);
            unset($this->items[$key]);
        }
        return $exists;
    }

    public function clear()
    {
        $this->items = array();
        $this->size = 0;
        return true;
    }

    public function getMultiple($keys, $default = null)
    {
        $values = array();
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }
        return $values;
    }

    public function setMultiple($values, $ttl = null)
    {
        $success = array();
        foreach ($values as $key => $value) {
            $success[$key] = $this->set($key, $value, $ttl);
        }
        return $success;
    }

    public function deleteMultiple($keys)
    {
        $success = array();
        foreach ($keys as $key) {
            $success[$key] = $this->delete($key);
        }
        return $success;
    }

    public function has($key)
    {
        if (!\array_key_exists($key, $this->items)) {
            // key not in cache
            return false;
        }
        $expire = $this->items[$key]['e'];
        if ($expire !== 0 && $expire < \time()) {
            // not permanent & expired
            $this->size -= \strlen($this->items[$key]['v']);
            unset($this->items[$key]);
            return false;
        }
        $this->lru($key);
        return true;
    }

    /**
     * Remove least recently used cache values until total store within limit
     *
     * @return void
     */
    protected function evict()
    {
        while ($this->size > $this->limit && !empty($this->items)) {
            $item = \array_shift($this->items);
            $this->size -= \strlen($item['v']);
        }
    }

    /**
     * Convert expiry to unix timestamp
     *
     * @param mixed $expire null: no expiration (0 is returned)
     *                      integer: relative/absolute time in seconds
     *                          0 : no expiration (0 is returned)
     *                          <= 30days : relative time
     *                          > 30days : absolute time
     *                      string ("YYYY-MM-DD HH:MM:SS") provide in UTC time
     *                      DateTime object
     *                      DateInterval object
     *
     * @return int unix timestamp (0 if no expiration)
     */
    protected function expiry($expire)
    {
        if ($expire === 0 || $expire === null) {
            return 0;
        }
        if (\is_numeric($expire)) {
            if ($expire <= 30 * 24 * 60 * 60) {
                // relative time in seconds, <=30 days
                $expire += \time();
            }
            return (int) \round($expire);
        }
        if ($expire instanceof DateTime) {
            return (int) $expire->format('U');
        }
        if ($expire instanceof DateInterval) {
            // convert DateInterval to integer by adding it to a 0 DateTime
            $datetime = new DateTime();
            $datetime->add($expire);
            return (int) $datetime->format('U');
        }
        if (\is_string($expire) && \preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $expire)) {
            // ASSUME UTC
            // return $expire;
            $expire = new DateTime($expire, new DateTimeZone('UTC'));
            return (int) $expire->format('U');
        }
    }

    /**
     * Move key to last position
     *
     * @param sttring $key key
     *
     * @return void
     */
    protected function lru($key)
    {
        $data = $this->items[$key];
        unset($this->items[$key]);
        $this->items[$key] = $data;
    }

    /**
     * Reset lastGetInfo array
     *
     * @param string $key key value
     *
     * @return void
     */
    protected function resetLastGetInfo($key = null)
    {
        $this->lastGetInfo = array(
            'calcTime'          => null,
            'code'              => 'notExist',
            'expiredValue'      => null,
            'expiry'            => null,
            'key'               => $key,
            'microtime'         => \microtime(true), // time of get.. so we can calc computation time
            'token'             => null,
        );
    }

    /**
     * Understands shorthand byte values (as used in e.g. memory_limit ini
     * setting) and converts them into bytes.
     *
     * @param string|int $shorthand Amount of bytes (int) or shorthand value (e.g. 512M)
     *
     * @see http://php.net/manual/en/faq.using.php#faq.using.shorthandbytes
     *
     * @return int
     */
    protected function shorthandToBytes($shorthand)
    {
        if (\is_numeric($shorthand)) {
            // make sure that when float(1.234E17) is passed in, it doesn't get
            // cast to string('1.234E17'), then to int(1)
            return $shorthand;
        }
        $units = array('B' => 1024, 'M' => \pow(1024, 2), 'G' => \pow(1024, 3));
        $regex = '/^([0-9]+)(' . \implode('|', \array_keys($units)) . ')$/';
        return (int) \preg_replace_callback($regex, function ($match) use ($units) {
            return $match[1] * $units[$match[2]];
        }, $shorthand);
    }
}
