<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
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
    /** @var string */
    protected $icon = ':template:';

    /** @var Debug */
    private $debug;

    /**
     * Constructor
     *
     * @param Debug|null   $debug   (optional) Debug instance
     * @param Profile|null $profile (optional) Profile instance
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct($debug = null, $profile = null)
    {
        \bdk\Debug\Utility::assertType($debug, 'bdk\Debug');
        \bdk\Debug\Utility::assertType($profile, 'Twig\Profiler\Profile');

        if (!$debug) {
            $debug = Debug::getChannel('Twig', array('channelIcon' => $this->icon));
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
