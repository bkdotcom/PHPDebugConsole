<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Framework\Laravel;

use bdk\Debug\Abstraction\Type;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

/**
 * Log views
 */
class LogViews
{
    public $debug;

    protected $app;
    protected $serviceProvider;
    protected $viewChannel;

    /**
     * Constructor
     *
     * @param ServiceProvider $serviceProvider ServiceProvider instance
     * @param Application     $app             Laravel Application
     */
    public function __construct(ServiceProvider $serviceProvider, Application $app)
    {
    	$this->serviceProvider = $serviceProvider;
        $this->app = $app;
        $this->debug = $serviceProvider->debug;
    }

    /**
     * Log views
     *
     * @return void
     */
    public function log()
    {
        if (!$this->serviceProvider->shouldCollect('laravel', true)) {
            return;
        }
        $this->viewChannel = $this->debug->getChannel('Views', array(
            'channelIcon' => ':template:',
        ));
        $this->app['events']->listen(
            'composing:*',
            function ($view, $data = []) {
                if ($data) {
                    $view = $data[0]; // For Laravel >= 5.4
                }
                $this->logView($view);
            }
        );
    }

    /**
     * Log view information
     *
     * @param View $view View instance
     *
     * @return void
     */
    protected function logView(View $view)
    {
        $name = $view->getName();
        $path = $view->getPath();
        $pathStr = \is_object($path)
            ? null
            : \realpath($path);

        $info = \array_filter(array(
            'name' => $name,
            'params' => \call_user_func([$this, 'logViewParams'], $view),
            'path' => $pathStr
                ? $this->debug->abstracter->crateWithVals(
                    \ltrim(\str_replace(\base_path(), '', $pathStr), '/'),
                    array(
                        'attribs' => array(
                            'data-file' => $path,
                        ),
                    )
                )
                : null,
            'type' => \is_object($path)
                ? \get_class($view)
                : (\substr($path, -10) === '.blade.php'
                    ? 'blade'
                    : \pathinfo($path, PATHINFO_EXTENSION)),
        ));
        $this->viewChannel->log('view', $info, $this->viewChannel->meta('detectFiles'));
    }

    /**
     * Get view params (view data)
     *
     * @param View $view View instance
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function logViewParams(View $view)
    {
        $data = $view->getData();
        /** @var bool|'type' */
        $collectValues = $this->app['config']->get('phpDebugConsole.options.views.data');
        if ($collectValues === true) {
            \ksort($data);
            return $data;
        }
        if ($collectValues !== 'type') {
            $data = \array_keys($data);
            \sort($data);
            return $data;
        }
        foreach ($data as $k => $v) {
            $type = $this->debug->abstracter->type->getType($v)[0];
            $data[$k] = $type === 'object'
                ? $this->debug->abstracter->crateWithVals(\get_class($v), array(
                    'type' => Type::TYPE_IDENTIFIER,
                    'typeMore' => Type::TYPE_IDENTIFIER_CLASSNAME,
                ))
                : $type;
        }
        \ksort($data);
        return $data;
    }
}
