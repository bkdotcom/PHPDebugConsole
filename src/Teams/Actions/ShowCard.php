<?php

declare(strict_types=1);

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
        parent::__construct();
        $this->type = 'Action.ShowCard';
        $this->fields = \array_merge($this->fields, array(
            'card' => new AdaptiveCard(),
        ));
    }

    /**
     * Returns content of card action
     *
     * @param float $version Card version
     *
     * @return array
     */
    public function getContent($version)
    {
        $content = parent::getContent($version);
        $content['card'] = $this->fields['card'];
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
        return $this->with('card', $this->fields['card']->withAddedElement($element));
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
        return $this->with('card', $this->fields['card']->withAddedAction($action));
    }

    /**
     * Return new instance with specified card
     *
     * @param AdaptiveCard $card AdaptiveCard
     *
     * @return static
     */
    public function withCard(AdaptiveCard $card = null)
    {
        return $this->with('card', $card);
    }
}
