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

namespace bdk\Debug;

use bdk\Debug\ConfigurableInterface;
use bdk\ErrorHandler\AbstractComponent as BaseAbstractComponent;

/**
 * Base "component" methods
 */
abstract class AbstractComponent extends BaseAbstractComponent implements ConfigurableInterface
{
    protected $setCfgMergeCallable = 'array_merge';
}
