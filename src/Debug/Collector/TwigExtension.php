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
use Twig\Extension\ProfilerExtension;
use Twig\Profiler\Profile;

/**
 * Profile / log Twig Template
 */
class TwigExtension extends ProfilerExtension
{
    private $debug;
    protected $icon = 'fa fa-file-text-o';

    /**
     * Constructor
     *
     * @param Debug   $debug   (optional) Debug instance
     * @param Profile $profile (optional) Profile instance
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct(Debug $debug = null, Profile $profile = null)
    {
        if (!$debug) {
            $debug = Debug::_getChannel('Twig', array('channelIcon' => $this->icon));
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('Twig', array('channelIcon' => $this->icon));
        }
        if (!$profile) {
            $profile = new Profile();
        }
        $this->debug = $debug;
        parent::__construct($profile);
    }

    /**
     * Used by ProfilerNodeVisitor / Profiler\Node\EnterProfileNode
     *
     * @param Profile $profile Profile instance
     *
     * @return void
     */
    public function enter(Profile $profile)
    {
        parent::enter($profile);
        $this->debug->groupCollapsed(
            'Twig: ' . $profile->getType(),
            $profile->getName(),
            $this->debug->meta('ungroup')
        );
    }

    /**
     * Used by ProfilerNodeVisitor / Profiler\Node\LeaveProfileNode
     *
     * @param Profile $profile Profile instance
     *
     * @return void
     */
    public function leave(Profile $profile)
    {
        parent::leave($profile);
        $haveChildren = \count($profile->getProfiles()) > 0;
        $msg = $haveChildren
            ? 'Twig: end ' . $profile->getType() . ': ' . $profile->getName()
            : 'Twig: ' . $profile->getType() . ': ' . $profile->getName();
        $this->debug->time($msg, $profile->getDuration());
        $this->debug->groupEnd();
    }
}
