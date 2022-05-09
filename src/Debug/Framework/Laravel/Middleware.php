<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Framework\Laravel;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use Closure;
use Error;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Auth\User;
use Illuminate\Routing\Router;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Symfony\Component\HttpFoundation\Request;

/**
 * Laravel Middleware
 */
class Middleware
{
    /**
     * The App container
     *
     * @var Container
     */
    protected $container;

    /**
     * The Debug instance
     *
     * @var Debug
     */
    protected $debug;

    /**
     * Temporary info built when logging auth
     *
     * @var array
     */
    protected $authData = array();

    /**
     * Constructor
     *
     * @param Container $container Container
     * @param Debug     $debug     Debug instance
     */
    public function __construct(Container $container, Debug $debug)
    {
        $this->container = $container;
        $this->debug = $debug;
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request Request instance
     * @param Closure $next    Next middleare handler
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            $response = $next($request);
        } catch (Exception $e) {
            $response = $this->handleException($request, $e);
        } catch (Error $error) {
            $e = new FatalThrowableError($error);
            $response = $this->handleException($request, $e);
        }

        $this->logAuth();
        $this->logRoute();
        $this->logSession();

        try {
            $this->debug->writeToResponse($response);
        } catch (\Exception $e) {
            $this->container['log']->error('PHPDebugConsole exception: ' . $e->getMessage());
        }

        return $response;
    }

    /**
     * Get displayed user information
     *
     * @param User $user user interface
     *
     * @return array
     */
    protected function getUserInformation(User $user = null)
    {
        // Defaults
        if ($user === null) {
            return array(
                'name' => 'Guest',
                'user' => array('guest' => true),
            );
        }

        // The default auth identifer is the ID number, which isn't all that
        // useful. Try username and email.
        $identifier = $user instanceof Authenticatable
            ? $user->getAuthIdentifier()
            : $user->id;
        if (\is_numeric($identifier)) {
            if (isset($user->username)) {
                $identifier = $user->username;
            } elseif (isset($user->email)) {
                $identifier = $user->email;
            }
        }
        return [
            'name' => $identifier,
            'user' => $user instanceof Arrayable
                ? $user->toArray()
                : $user,
        ];
    }

    /**
     * Handle the given exception.
     *
     * (Copy from Illuminate\Routing\Pipeline by Taylor Otwell)
     *
     * @param Request   $request Request
     * @param Exception $e       Exception
     *
     * @return mixed
     * @throws Exception
     */
    protected function handleException(Request $request, Exception $e)
    {
        if (!$this->container->bound(ExceptionHandler::class)) {
            throw $e;
        }
        $handler = $this->container->make(ExceptionHandler::class);
        $handler->report($e);
        return $handler->render($request, $e);
    }

    /**
     * Test if we have a user
     *
     * @param Guard $guard Guard instance
     *
     * @return bool
     */
    private function hasUser(Guard $guard)
    {
        if (\method_exists($guard, 'hasUser')) {
            return $guard->hasUser();
        }

        // For Laravel 5.5
        if (\method_exists($guard, 'alreadyAuthenticated')) {
            return $guard->alreadyAuthenticated();
        }

        return false;
    }

    /**
     * log auth info
     *
     * @return void
     */
    protected function logAuth()
    {
        if (!$this->shouldCollect('auth', true)) {
            return;
        }
        $this->authData = array(
            'guards' => array(),
            'names' => array(),
        );
        $guards = $this->container['config']->get('auth.guards', array());
        foreach (\array_keys($guards) as $guardName) {
            try {
                $this->logAuthGuard($guardName);
            } catch (\Exception $e) {
                continue;
            }
        }
        $debug = $this->debug->rootInstance->getChannel(
            'User',
            array(
                'channelIcon' => 'fa fa-user-o',
                'nested' => false,
            )
        );
        $debug->log('Laravel auth', $this->authData);
        $this->authData = array();
    }

    /**
     * Set guart info
     *
     * @param string $guardName Guard name
     *
     * @return void
     */
    private function logAuthGuard($guardName)
    {
        $guard = $this->container['auth']->guard($guardName);
        if (!$this->hasUser($guard)) {
            $this->authData['guards'][$guardName] = null;
            return;
        }
        $user = $guard->user();
        if ($user !== null) {
            $userInfo = $this->getUserInformation($user);
            $this->authData['guards'][$guardName] = $userInfo;
            $this->authData['names'][] = $guardName . ': ' . $userInfo['name'];
        }
    }

    /**
     * Log route information
     *
     * @return void
     */
    protected function logRoute()
    {
        if (!$this->shouldCollect('laravel', true)) {
            return;
        }
        $router = $this->container->make(Router::class);
        $route = $router->current();
        $methods = $route->methods();
        $uri = \reset($methods) . ' ' . $route->uri();

        $info = \array_merge(array(
            'uri' => $uri ?: null,
            'middleware' => $route->middleware(),
        ), $route->getAction());
        $info['middleware'] = $this->debug->abstracter->crateWithVals($info['middleware'], array(
            'options' => array(
                'expand' => true,
            ),
        ));

        $info = $this->logRouteSetFile($info);

        $this->debug->groupSummary();
        $this->debug->log('Route info', $info, $this->debug->meta('detectFiles'));
        $this->debug->groupEnd();
    }

    /**
     * Add file info to route info
     *
     * @param array $info Route info
     *
     * @return route onfo
     */
    private function logRouteSetFile(array $info)
    {
        $reflector = null;
        switch ($this->routeInfoUse($info)) {
            case 'controllerMethod':
                list($controller, $method) = \explode('@', $info['controller']);
                if (\class_exists($controller) && \method_exists($controller, $method)) {
                    $reflector = new \ReflectionMethod($controller, $method);
                }
                unset($info['uses']);
                break;
            case 'closure':
                $reflector = new \ReflectionFunction($info['uses']);
        }
        if ($reflector) {
            $relFilename = \ltrim(\str_replace(\base_path(), '', $reflector->getFileName()), '/');
            $info['file'] = $this->debug->abstracter->crateWithVals(
                $relFilename . ':' . $reflector->getStartLine() . '-' . $reflector->getEndLine(),
                array(
                    'attribs' => array(
                        'data-file' => $reflector->getFileName(),
                        'data-line' => $reflector->getStartLine(),
                    ),
                )
            );
        }
        return $info;
    }

    /**
     * Check if route info has the given data
     *
     * @param array $info Route info
     *
     * @return string|null 'controllerMethod' or 'closure'
     */
    private function routeInfoUse($info)
    {
        if (isset($info['controller']) && \is_string($info['controller']) && \strpos($info['controller'], '@') !== false) {
            return 'controllerMethod';
        } elseif (isset($info['uses']) && $info['uses'] instanceof \Closure) {
            return 'closure';
        }
        return null;
    }

    /**
     * Log session information
     *
     * @return void
     */
    protected function logSession()
    {
        if (!$this->shouldCollect('session', false)) {
            return;
        }
        $debug = $this->debug->rootInstance->getChannel(
            'Session',
            array(
                'channelIcon' => 'fa fa-suitcase',
                'nested' => false,
            )
        );
        $debug->log(
            $this->debug->abstracter->crateWithVals(
                \get_class($this->container['session']),
                array('typeMore' => Abstracter::TYPE_STRING_CLASSNAME)
            )
        );
        $debug->log(
            $this->container['session']->all()
        );
    }

    /**
     * Config get wrapper
     *
     * @param string $name    option name
     * @param mixed  $default default vale
     *
     * @return bool
     */
    protected function shouldCollect($name, $default = false)
    {
        return $this->container['config']->get('phpDebugConsole.collect.' . $name, $default);
    }
}
