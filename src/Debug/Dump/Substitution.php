<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @since     3.0b1
 */

namespace bdk\Debug\Dump;

use bdk\Debug;
use bdk\Debug\Dump\Base as Dumper;
use bdk\Debug\LogEntry;

/**
 * handle formatting / substitution
 *
 * @see https://console.spec.whatwg.org/#formatter
 */
class Substitution
{
    /** @var Debug */
    protected $debug;

    /** @var Dumper */
    protected $dumper;

    /** @var array<string,mixed> */
    private $subInfo = array();

    /** @var string */
    private $subRegex;

    /**
     * Constructor
     *
     * @param Dumper $dumper Dump instance
     */
    public function __construct(Dumper $dumper)
    {
        $this->dumper = $dumper;
        $this->debug = $dumper->debug;
        // create a temporary logEntry to obtain subRegex
        $logEntry = new LogEntry($this->debug, 'null');
        $this->subRegex = $logEntry->subRegex;
    }

    /**
     * Handle the not-well documented substitutions
     *
     * @param array $args    arguments
     * @param array $options options
     *
     * @return array updated args
     *
     * @see https://console.spec.whatwg.org/#formatter
     * @see https://developer.mozilla.org/en-US/docs/Web/API/console#Using_string_substitutions
     */
    public function process($args, $options = array())
    {
        if (\is_string($args[0]) === false) {
            return $args;
        }
        $this->subInfo = array(
            'args' => $args,
            'index' => 0,
            'options' => \array_merge(array(
                'addQuotes' => false,
                'replace' => false, // perform substitution, or just prep?
                'sanitize' => true,
                'style' => false,   // ie support %c
            ), $options),
            'typeCounts' => \array_fill_keys(\str_split('coOdifs'), 0),
        );
        return $this->processArgs();
    }

    /**
     * Update arguments
     *
     * @return array updated args
     */
    private function processArgs()
    {
        $args = $this->subInfo['args'];
        $string = \preg_replace_callback($this->subRegex, array($this, 'processSubsCallback'), $args[0]);
        $args = $this->subInfo['args'];
        if (!$this->subInfo['options']['style']) {
            $this->subInfo['typeCounts']['c'] = 0;
        }
        $hasSubs = \array_sum($this->subInfo['typeCounts']);
        if ($hasSubs && $this->subInfo['options']['replace']) {
            if ($this->subInfo['typeCounts']['c'] > 0) {
                $string .= '</span>';
            }
            $args = \array_values($args);
        }
        $args[0] = $string;
        return $args;
    }

    /**
     * Process string substitution regex callback
     *
     * @param string[] $matches regex matches array
     *
     * @return string|mixed
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function processSubsCallback($matches)
    {
        $index = ++$this->subInfo['index'];
        $format = $matches[0];
        $type = \substr($format, -1);
        if (\array_key_exists($index, $this->subInfo['args']) === false) {
            return $format;
        }
        $arg = $this->subInfo['args'][$index];
        if ($type === 's') {
            $arg = $this->dumper->substitutionAsString($arg, $this->subInfo['options']);
        }
        $replacement = $this->replacement($arg, $format);
        $this->subInfo['typeCounts'][$type]++;
        if ($this->subInfo['options']['replace']) {
            unset($this->subInfo['args'][$index]);
            return $replacement;
        }
        $this->subInfo['args'][$index] = $arg;
        return $format;
    }

    /**
     * Get replacement value for given arg and format
     *
     * @param mixed  $arg    the argument we're getting string representation of
     * @param string $format the string we're replacing (replacement format)
     *
     * @return string
     */
    private function replacement($arg, $format)
    {
        $type = \substr($format, -1);
        if (\preg_match('/[difs]/', $type)) {
            return $this->subReplacementDifs($arg, $format);
        }
        if ($type === 'c' && $this->subInfo['options']['style']) {
            return $this->subReplacementC($arg);
        }
        if (\preg_match('/[oO]/', $type)) {
            return $this->dumper->valDumper->dump($arg);
        }
        return '';
    }

    /**
     * c (css) arg replacement
     *
     * @param string $arg css string
     *
     * @return string
     */
    private function subReplacementC($arg)
    {
        $replacement = '';
        if ($this->subInfo['typeCounts']['c']) {
            // close prev
            $replacement = '</span>';
        }
        return $replacement . '<span' . $this->debug->html->buildAttribString(array(
            'style' => $arg,
        )) . '>';
    }

    /**
     * d,i,f,s arg replacement
     *
     * @param array|string $arg    replacement value
     * @param string       $format format (what's being replaced)
     *
     * @return array|string
     */
    private function subReplacementDifs($arg, $format)
    {
        $type = \substr($format, -1);
        if ($type === 'i') {
            $format = \substr_replace($format, 'd', -1, 1);
        }
        return \is_array($arg)
            ? $arg
            : \sprintf($format, $arg);
    }
}
