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

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\PubSub\Manager as EventManager;
use SplObjectStorage;
use Swift_Events_CommandEvent;
use Swift_Events_CommandListener;
use Swift_Events_ResponseEvent;
use Swift_Events_ResponseListener;
use Swift_Events_SendEvent;
use Swift_Events_SendListener;
use Swift_Events_TransportChangeEvent;
use Swift_Events_TransportChangeListener;
use Swift_Events_TransportExceptionEvent;
use Swift_Events_TransportExceptionListener;
use Swift_Plugins_Logger;
use Swift_TransportException;

/**
 * A SwiftMailer adapter
 */
class SwiftMailerLogger implements Swift_Events_CommandListener, Swift_Events_ResponseListener, Swift_Events_SendListener, Swift_Events_TransportChangeListener, Swift_Events_TransportExceptionListener, Swift_Plugins_Logger
{
    private $debug;
    protected $messages = array();
    protected $icon = 'fa fa-envelope-o';
    protected $iconMeta;
    protected $useIcon = true;
    protected $transports;  // splObjectStorage

    /**
     * Constructor
     *
     * @param Debug $debug (optional) Specify PHPDebugConsole instance
     *                         if not passed, will create Slim channel on singleton instance
     *                         if root channel is specified, will create a SwiftMailer channel
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct(Debug $debug = null)
    {
        if (!$debug) {
            $debug = Debug::_getChannel('SwiftMailer', array('channelIcon' => $this->icon));
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('SwiftMailer', array('channelIcon' => $this->icon));
        }
        $debug->rootInstance->eventManager->subscribe(EventManager::EVENT_PHP_SHUTDOWN, array($this, 'onShutdown'), PHP_INT_MAX * -1 + 1);
        $this->debug = $debug;
        $this->iconMeta = $this->debug->meta('icon', $this->icon);
        $this->transports = new SplObjectStorage();
    }

    /**
     * Invoked immediately before the Message is sent.
     *
     * Implements Swift_Events_SendListener
     *
     * @param Swift_Events_SendEvent $event Swift SendEvent instance
     *
     * @return void
     */
    public function beforeSendPerformed(Swift_Events_SendEvent $event)
    {
        $msg = $event->getMessage();
        $this->debug->groupCollapsed(
            'sending email',
            $this->formatEmailAddrs($msg->getTo()),
            $msg->getSubject(),
            $this->iconMeta
        );
        $this->debug->log('headers', $msg->getHeaders()->toString());
        $this->useIcon = false; // don't use icon within group;
    }

    /**
     * Invoked immediately after the Message is sent.
     *
     * Implements Swift_Events_SendListener
     *
     * @param Swift_Events_SendEvent $event Swift SendEvent Instance
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function sendPerformed(Swift_Events_SendEvent $event)
    {
        array($event);
        $this->debug->groupEnd();
        $this->clear();
        $this->useIcon = true;
    }

    /**
     * Add a log entry.
     *
     * Implements Swift_Plugins_Logger
     *
     * @param string $entry message
     *
     * @return void
     */
    public function add($entry)
    {
        $this->messages[] = $entry;
        $debugArgs = array($entry);
        $matches = array();
        if (\preg_match('#^(([-+><])\2) (.+)$#s', $entry, $matches)) {
            $debugArgs = array($matches[1] . ':', $matches[3]);
        }
        if ($this->useIcon) {
            $debugArgs[] = $this->iconMeta;
        }
        \call_user_func_array(array($this->debug, 'log'), $debugArgs);
    }

    /**
     * Clear the log contents.
     *
     * Implements Swift_Plugins_Logger
     *
     * @return void
     */
    public function clear()
    {
        $this->messages = array();
    }

    /**
     * Get this log as a string.
     *
     * Implements Swift_Plugins_Logger
     *
     * @return string
     */
    public function dump()
    {
        return \implode(PHP_EOL, $this->messages);
    }

    /**
     * Invoked immediately following a command being sent.
     *
     * Implements Swift_Events_CommandListener
     *
     * @param Swift_Events_CommandEvent $evt Swift Event
     *
     * @return void
     */
    public function commandSent(Swift_Events_CommandEvent $evt)
    {
        $command = $evt->getCommand();
        $this->add(\sprintf('>> %s', $command));
    }

    /**
     * Invoked immediately following a response coming back.
     *
     * Implements Swift_Events_ResponseListener
     *
     * @param Swift_Events_ResponseEvent $evt Swift Event
     *
     * @return void
     */
    public function responseReceived(Swift_Events_ResponseEvent $evt)
    {
        $response = $evt->getResponse();
        $this->add(\sprintf('<< %s', $response));
    }

    /**
     * Invoked just before a Transport is started.
     *
     * Implements Swift_Events_TransportChangeListener
     *
     * @param Swift_Events_TransportChangeEvent $evt Swift Event
     *
     * @return void
     */
    public function beforeTransportStarted(Swift_Events_TransportChangeEvent $evt)
    {
        $transportName = \get_class($evt->getSource());
        $this->add(\sprintf('++ Starting %s', $transportName));
    }

    /**
     * Invoked just before a Transport is stopped.
     *
     * Implements Swift_Events_TransportChangeListener
     *
     * @param Swift_Events_TransportChangeEvent $evt Swift Event
     *
     * @return void
     */
    public function beforeTransportStopped(Swift_Events_TransportChangeEvent $evt)
    {
        $transportName = \get_class($evt->getSource());
        $this->add(\sprintf('-- Stopping %s', $transportName));
    }

    /**
     * EventManager::EVENT_PHP_SHUTDOWN listener
     *
     * "preemptively" stop transports rather than wait for transport's __destruct
     *
     * @return void
     */
    public function onShutdown()
    {
        foreach ($this->transports as $transport) {
            $transport->stop();
        }
    }

    /**
     * Invoked immediately after the Transport is started.
     *
     * Implements Swift_Events_TransportChangeListener
     *
     * @param Swift_Events_TransportChangeEvent $evt Swift Event
     *
     * @return void
     */
    public function transportStarted(Swift_Events_TransportChangeEvent $evt)
    {
        $transportName = \get_class($evt->getSource());
        $this->add(\sprintf('++ %s started', $transportName));
        $this->clear();
        $this->transports->attach($evt->getSource());
    }

    /**
     * Invoked immediately after the Transport is stopped.
     *
     * Implements Swift_Events_TransportChangeListener
     *
     * @param Swift_Events_TransportChangeEvent $evt Swift Event
     *
     * @return void
     */
    public function transportStopped(Swift_Events_TransportChangeEvent $evt)
    {
        $transportName = \get_class($evt->getSource());
        $this->add(\sprintf('-- %s stopped', $transportName));
        $this->transports->detach($evt->getSource());
    }

    /**
     * Invoked as a TransportException is thrown in the Transport system.
     *
     * Implements Swift_Events_TransportExceptionListener
     *
     * @param Swift_Events_TransportExceptionEvent $evt Swift Event
     *
     * @return void
     * @throws Swift_TransportException
     */
    public function exceptionThrown(Swift_Events_TransportExceptionEvent $evt)
    {
        $exception = $evt->getException();
        $message = $exception->getMessage();
        $code = (int) $exception->getCode();
        $this->debug->warn($code . ':', $message);
        $message .= PHP_EOL;
        $message .= 'Log data:' . PHP_EOL;
        $message .= $this->dump();
        $evt->cancelBubble();
        /** @psalm-suppress ArgumentTypeCoercion ignore 3rd argument expects Exception, but Throwable passed */
        throw new Swift_TransportException($message, $code, $exception->getPrevious());
    }

    /**
     * Convert array of email addresses/names to string
     *
     * @param array $addrs emailAddr->name pairs
     *
     * @return string
     */
    protected function formatEmailAddrs($addrs)
    {
        $return = array();
        foreach ($addrs as $addr => $name) {
            $return[] = ($name ? $name . ' ' : '') . '<' . $addr . '>';
        }
        return \implode(', ', $return);
    }
}
