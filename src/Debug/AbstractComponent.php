<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
 */

namespace bdk\Debug;

use bdk\Debug\ConfigurableInterface;
use bdk\ErrorHandler\AbstractComponent as BaseAbstractComponent;

/**
 * Base "component" methods
 */
abstract class AbstractComponent extends BaseAbstractComponent implements ConfigurableInterface
{
    /** @var callable */
    protected $setCfgMergeCallable = 'array_merge';
}
