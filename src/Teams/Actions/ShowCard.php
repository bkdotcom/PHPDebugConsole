<?php

/**
 * @package   bdk\teams
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Teams\Actions;

use bdk\Teams\Cards\AdaptiveCard;
use bdk\Teams\Elements\ElementInterface;

/**
 * ShowCard action
 */
class ShowCard extends AbstractAction
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct(array(
            'card' => new AdaptiveCard(),
        ), 'Action.ShowCard');
    }

    /**
     * {@inheritDoc}
     */
    public function getContent($version)
    {
        $content = parent::getContent($version);
        $content['card'] = $this->getCard();
        return self::normalizeContent($content, $version);
    }

    /**
     * Shortcut for adding element to card
     *
     * @param ElementInterface $element Card element
     *
     * @return static
     */
    public function withAddedElement(ElementInterface $element)
    {
        return $this->with('card', $this->getCard()->withAddedElement($element));
    }

    /**
     * Shortcut for adding action to card
     *
     * @param ActionInterface $action Card action
     *
     * @return static
     */
    public function withAddedAction(ActionInterface $action)
    {
        return $this->with('card', $this->getCard()->withAddedAction($action));
    }

    /**
     * Return new instance with specified card
     *
     * @param AdaptiveCard|null $card AdaptiveCard
     *
     * @return static
     */
    public function withCard($card = null)
    {
        self::assertType($card, 'bdk\Teams\Cards\AdaptiveCard');

        return $this->with('card', $card);
    }

    /**
     * Get the underlying card
     *
     * @return AdaptiveCard
     */
    protected function getCard()
    {
        /** @var AdaptiveCard */
        return $this->fields['card'];
    }
}
