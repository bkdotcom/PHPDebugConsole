<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Collector;

use bdk\Debug;
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
use Swift_Mailer;
use Swift_Plugins_Logger;
use Swift_TransportException;

/**
 * A SwiftMailer adapter
 */
class SwiftMailerLogger implements Swift_Events_CommandListener, Swift_Events_ResponseListener, Swift_Events_SendListener, Swift_Events_TransportChangeListener, Swift_Events_TransportExceptionListener, Swift_Plugins_Logger
{

    private $debug;
    protected $messages = array();

    /**
     * Constructor
     *
     * @param Debug $debug (optional) Specify PHPDebugConsole instance
     *                         if not passed, will create Slim channnel on singleton instance
     *                         if root channel is specified, will create a SwiftMailer channel
     */
    public function __construct(Debug $debug = null)
    {
        if (!$debug) {
            $debug = \bdk\Debug::_getChannel('SwiftMailer');
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('SwiftMailer');
        }
        $this->debug = $debug;
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
        $this->debug->log(
            'sending email',
            $msg->getHeaders()->toString(),
            /*
            array(
                'to' => $this->formatEmailAddrs($msg->getTo()),
                'subject' => $msg->getSubject(),
                'headers' => $msg->getHeaders()->toString()
            ),
            */
            $this->debug->meta('icon', 'fa fa-envelope-o')
        );
    }

    /**
     * Invoked immediately after the Message is sent.
     *
     * Implements Swift_Events_SendListener
     *
     * @param Swift_Events_SendEvent $event Swift SendEvent Instance
     *
     * @return void
     */
    public function sendPerformed(Swift_Events_SendEvent $event)
    {
        return;
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
        $this->debug->log($entry, $this->debug->meta('icon', 'fa fa-envelope-o'));
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
        $this->add(\sprintf('++ Stopping %s', $transportName));
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
        $this->add(\sprintf('++ %s stopped', $transportName));
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
        $e = $evt->getException();
        $message = $e->getMessage();
        $code = $e->getCode();
        $this->debug->warn($code.':', $message);
        $message .= PHP_EOL;
        $message .= 'Log data:'.PHP_EOL;
        $message .= $this->dump();
        $evt->cancelBubble();
        throw new Swift_TransportException($message, $code, $e->getPrevious());
    }

    /**
     * Convert array of email addresses/names to string
     *
     * @param array $addrs emailAddr->name pairs
     *
     * @return string
     */
    /*
    protected function formatEmailAddrs($addrs)
    {
        $return = array();
        foreach ($addrs as $addr => $name) {
            $return[] = ($name ? $name.' ' : '') . '<'.$addr.'>';
        }
        return \implode(', ', $return);
    }
    */
}
