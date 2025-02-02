<?php

/**
 * @package   bdk\teams
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Teams\Elements;

use bdk\Teams\Actions\ActionInterface;
use InvalidArgumentException;

/**
 * Displays a set of actions.
 */
class ActionSet extends AbstractElement
{
    /**
     * Constructor
     *
     * @param ActionInterface[] $actions The array of Action elements to show.
     */
    public function __construct(array $actions = array())
    {
        self::assertActions($actions);
        parent::__construct(array(
            'actions' => $actions,
        ), 'ActionSet');
    }

    /**
     * {@inheritDoc}
     */
    public function getContent($version)
    {
        $element = parent::getContent($version);
        /** @var ActionInterface[] */
        $element['actions'] = $this->fields['actions'];
        return self::normalizeContent($element, $version);
    }

    /**
     * Return new instance with specified actions
     *
     * @param ActionInterface[] $actions The array of Action elements to show.
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withActions(array $actions)
    {
        if ($actions === array()) {
            throw new InvalidArgumentException(\sprintf(
                '%s - Actions must be non-empty',
                __METHOD__
            ));
        }
        self::assertActions($actions);
        return $this->with('actions', $actions);
    }

    /**
     * Return new instance with action appended
     *
     * @param ActionInterface $action The action to append
     *
     * @return static
     */
    public function withAddedAction(ActionInterface $action)
    {
        return $this->withAdded('actions', $action);
    }

    /**
     * Assert that array consists of Actions
     *
     * @param ActionInterface[] $actions List to test
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private static function assertActions($actions)
    {
        foreach ($actions as $i => $action) {
            if ($action instanceof ActionInterface) {
                continue;
            }
            throw new InvalidArgumentException(\sprintf(
                'Invalid action found at index %s',
                $i
            ));
        }
    }
}
