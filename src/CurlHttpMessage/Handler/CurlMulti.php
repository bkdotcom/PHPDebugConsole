<?php

/**
 * @package   bdk\curlhttpmessage
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\CurlHttpMessage\Handler;

use bdk\CurlHttpMessage\CurlReqRes;
use bdk\Promise;
use CurlHandle;
use CurlMultiHandle;
use RuntimeException;

/**
 * Take a RequestInterface instance, return a Promise
 */
class CurlMulti extends Curl
{
    /** @var int Will be higher than 0 when `curl_multi_exec` is still running. */
    private $active = 0;

    /** @var resource[]|\CurlHandle[] Idle curl handles */
    private $idleHandles = [];

    /** @var CurlMultiHandle|resource|null|false */
    private $multiHandle;

    /** @var array options */
    protected $options = array();

    /** @var CurlReqRes[] Requests currently being executed by curl indexed by (int) curlHandle */
    private $processing = array();

    /** @var CurlReqRes[] Request Queue indexed by CurlReqRes hash*/
    private $queue = array();

    /**
     * Constructor
     *
     * @param array $options Options including
     *                          curl :  http://php.net/curl_setopt
     *                          curlMulti : http://php.net/curl_multi_setopt
     *                          selectTimeout : 1
     *
     * @throws RuntimeException
     */
    public function __construct($options = array())
    {
        $this->options = \array_replace_recursive(array(
            'curlMulti' => array(),
            'maxConcurrent' => 10,
            'maxIdleHandles' => 10,
            'selectTimeout' => 1,
        ), $options);
        $this->init();
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if (isset($this->multiHandle)) {
            \curl_multi_close($this->multiHandle);
            unset($this->multiHandle);
        }
    }

    /**
     * Invoke handler
     *
     * @param CurlReqRes $curlReqRes CurlReqRes instance
     *
     * @return Promise
     */
    public function __invoke(CurlReqRes $curlReqRes)
    {
        $hash = \spl_object_hash($curlReqRes);
        $this->queue[$hash] = $curlReqRes;
        $promise = new Promise(
            array($this, 'process'),
            function () use ($curlReqRes) {
                $this->cancel($curlReqRes);
            }
        );
        $curlReqRes->setPromise($promise);
        return $promise;
    }

    /**
     * Process the request queue
     *
     * @return void
     */
    public function process()
    {
        if ($this->active) {
            return;
        }
        while ($this->processing || $this->queue) {
            $this->tick();
        }
    }

    /**
     * Cancels a handle from sending and removes references to it.
     *
     * @param CurlReqRes $curlReqRes cURL / request / response
     *
     * @return void
     */
    private function cancel(CurlReqRes $curlReqRes)
    {
        $curlHandle = $curlReqRes->getCurlHandle();
        if ($curlHandle) {
            $id = (int) $curlHandle;
            unset($this->processing[$id]);
            \curl_multi_remove_handle($this->multiHandle, $curlHandle);
            $this->releaseHandle($curlReqRes);
        }
        $hash = \spl_object_hash($curlReqRes);
        if (isset($this->queue[$hash])) {
            unset($this->queue[$hash]);
        }
    }

    /**
     * Reuse existing curl handle or init a new one
     *
     * @return resource|CurlHandle
     */
    private function getCurlHandle()
    {
        return $this->idleHandles
            ? \array_pop($this->idleHandles)
            : \curl_init();
    }

    /**
     * Return delay (in milliseconds)
     *
     * @return int
     */
    private function getDelay()
    {
        $currentTime = \microtime(true);
        $nextTime = \PHP_INT_MAX;
        foreach ($this->queue as $curlReqRes) {
            $time = $curlReqRes->getOption('noEarlierThan');
            if ($time !== null && $time < $nextTime) {
                $nextTime = $time;
            }
        }
        return $nextTime !== PHP_INT_MAX
            ? \ceil(\max(0, $nextTime - $currentTime) * 1000)
            : 0;
    }

    /**
     * Initialize cURL multi handler
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    protected function init()
    {
        $this->multiHandle = \curl_multi_init();
        if ($this->multiHandle === false) {
            throw new RuntimeException('Can not initialize curl multi handle.');
        }
        foreach ($this->options['curlMulti'] as $option => $value) {
            // A warning is raised in case of a wrong option.
            // phpcs:ignore SlevomatCodingStandard.Namespaces.FullyQualifiedGlobalFunctions.NonFullyQualified
            curl_multi_setopt($this->multiHandle, $option, $value);
        }
    }

    /**
     * Take CurlReqRes from the queue and fill the processing bucket
     *
     * @return void
     */
    protected function manageQueue()
    {
        $currentTime = \microtime(true);
        foreach ($this->queue as $hash => $curlReqRes) {
            if (\count($this->processing) >= $this->options['maxConcurrent']) {
                break;
            }
            if ($curlReqRes->getOption('noEarlierThan') > $currentTime) {
                continue;
            }
            unset($this->queue[$hash]);
            $curlHandle = $this->getCurlHandle();
            $curlReqRes->setCurlHandle($curlHandle);
            \curl_multi_add_handle($this->multiHandle, $curlHandle);
            $id = (int) $curlHandle;
            $this->processing[$id] = $curlReqRes;
        }
    }

    /**
     * Release and recycle curl handle
     *
     * @param CurlReqRes $curlReqRes Curl Request/Response
     *
     * @return void
     */
    private function releaseHandle(CurlReqRes $curlReqRes)
    {
        $curlHandle = $curlReqRes->getCurlHandle();
        $curlReqRes->setCurlHandle(null);
        if (\count($this->idleHandles) >= $this->options['maxIdleHandles']) {
            \curl_close($curlHandle);
            return;
        }
        if (\function_exists('curl_reset')) {
            \curl_reset($curlHandle);
            $this->idleHandles[] = $curlHandle;
        }
    }

    /**
     * Ticks the curl event loop.
     *
     * @return void
     */
    protected function tick()
    {
        $this->manageQueue();

        // If there are no transfers, then sleep for the next delay
        $delay = !$this->active
            ? $this->getDelay()
            : 0;
        if ($delay) {
            $microSec = $delay * 1000;
            \usleep((int) $microSec);
        }

        // Step through the task queue which may add additional requests.
        Promise::queue()->run();

        if ($this->active && \curl_multi_select($this->multiHandle, $this->options['selectTimeout']) === -1) {
            // Perform a usleep if a select returns -1.
            // See: https://bugs.php.net/bug.php?id=61141
            \usleep(250);
        }

        while (\curl_multi_exec($this->multiHandle, $this->active) === \CURLM_CALL_MULTI_PERFORM);

        $this->curlMultiInfoRead();
    }

    /**
     * Get results from requests curl in processing
     *
     * @return void
     */
    private function curlMultiInfoRead()
    {
        while ($done = \curl_multi_info_read($this->multiHandle)) {
            if ($done['msg'] !== CURLMSG_DONE) {
                // if it's not done, then it would be premature to remove the handle.
                continue;
            }
            $id = (int) $done['handle'];
            \curl_multi_remove_handle($this->multiHandle, $done['handle']);

            if (!isset($this->processing[$id])) {
                // Probably was cancelled.
                continue;
            }

            $curlReqRes = $this->processing[$id];
            unset($this->processing[$id]);
            $response = $curlReqRes->finish();
            $this->releaseHandle($curlReqRes);
            $curlReqRes->getPromise()->resolve(
                // Promise::promiseFor()
                $response
            );
        }
    }
}
