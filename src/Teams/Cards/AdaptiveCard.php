<?php

/**
 * @package   bdk\teams
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Teams\Cards;

use bdk\Teams\Actions\ActionInterface;
use bdk\Teams\Elements\ElementInterface;
use bdk\Teams\Enums;
use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

/**
 * Custom adaptive card
 *
 * "Action.Submit" is currently not supported
 *
 * @see https://docs.microsoft.com/en-us/microsoftteams/platform/webhooks-and-connectors/how-to/connectors-using#send-adaptive-cards-using-an-incoming-webhook
 */
class AdaptiveCard extends AbstractCard
{
    /**
     * @var array{
     *    $schema: string,
     *    actions: ActionInterface[],
     *    backgroundImage: string|null,
     *    body: ElementInterface[],
     *    fallbackText: string|null,
     *    lang: string|null,
     *    minHeight: string|null,
     *    rtl: bool|null,
     *    selectAction: ActionInterface|null,
     *    speak: null,
     *    version: float,
     *    verticalContentAlignment: Enums::VERTICAL_ALIGNMENT_*|null,
     * }
     *
     * @psalm-suppress NonInvariantDocblockPropertyType
     * @psalm-suppress InvalidPropertyAssignmentValue
     */
    protected $fields = array();

    /** @var float[] */
    private $supportedVersions = [1.0, 1.1, 1.2, 1.3, 1.4, 1.5];

    /**
     * Constructor
     *
     * @param float $version Card version
     *
     * @throws InvalidArgumentException
     */
    public function __construct($version = 1.5)
    {
        if (\in_array($version, $this->supportedVersions, true) === false) {
            throw new InvalidArgumentException('Invalid version');
        }
        parent::__construct(array(
            '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
            'actions' => array(),
            'backgroundImage' => null,
            'body' => array(),
            'fallbackText' => null,
            'lang' => null,
            'minHeight' => null,
            'rtl' => null,
            'selectAction' => null,
            'speak' => null,
            'version' => $version,
            'verticalContentAlignment' => null,
        ), 'AdaptiveCard');
    }

    /**
     * {@inheritDoc}
     */
    public function getContent($version)
    {
        $attrVersions = array(
            '$schema' => 1.0,
            'actions' => 1.0,
            'backgroundImage' => 1.0,
            'body' => 1.0,
            'fallbackText' => 1.0,
            'lang' => 1.0,
            'minHeight' => 1.2,
            'rtl' => 1.5,
            'selectAction' => 1.1,
            'speak' => 1.0,
            'version' => 1.0,
            'verticalContentAlignment' => 1.1,
        );

        $content = array(
            'type' => $this->type,
        );
        foreach ($attrVersions as $name => $ver) {
            if ($version >= $ver) {
                /** @var mixed */
                $content[$name] = $this->fields[$name];
            }
        }

        return self::normalizeContent($content, $version);
    }

    /**
     * {@inheritDoc}
     */
    public function getMessage()
    {
        // phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        return array(
            'type' => 'message',
            'attachments' => array(
                array(
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'contentUrl' => null,
                    'content' => $this->getContent($this->fields['version']),
                ),
            ),
        );
        // @phpcs:enable
    }

    /**
     * Adds single element to card body
     *
     * @param ElementInterface $element Adaptive Card Element
     *
     * @return static
     */
    public function withAddedElement(ElementInterface $element)
    {
        return $this->withAdded('body', $element);
    }

    /**
     * Adds single action to card actions
     *
     * @param ActionInterface $action Adaptive Card Action
     *
     * @return static
     */
    public function withAddedAction(ActionInterface $action)
    {
        return $this->withAdded('actions', $action);
    }

    /**
     * Return new instance with specified actions
     *
     * @param ActionInterface[] $actions New actions
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withActions(array $actions)
    {
        foreach ($actions as $i => $action) {
            if ($action instanceof ActionInterface) {
                continue;
            }
            throw new InvalidArgumentException(\sprintf(
                '%s: Invalid action found at index %s',
                __METHOD__,
                $i
            ));
        }
        return $this->with('actions', $actions);
    }

    /**
     * Return new instance with specified body elements
     *
     * @param ElementInterface[] $body New body elements
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withBody(array $body)
    {
        foreach ($body as $i => $element) {
            if ($element instanceof ElementInterface) {
                continue;
            }
            throw new InvalidArgumentException(\sprintf(
                '%s: Invalid element found at index %s',
                __METHOD__,
                $i
            ));
        }
        return $this->with('body', $body);
    }

    /**
     * Return new instance with given backgroundImage
     *
     * @param string|UriInterface           $url                 Image url
     * @param Enums::FILLMODE_*             $fillmode            fill mode
     * @param Enums::HORIZONTAL_ALIGNMENT_* $horizontalAlignment horizontal alignment
     * @param Enums::VERTICAL_ALIGNMENT_*   $verticalAlignment   Vertical alignment
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withBackgroundImage($url, $fillmode = null, $horizontalAlignment = null, $verticalAlignment = null)
    {
        if ($url !== null) {
            self::assertUrl($url);
        }
        self::assertEnumValue($fillmode, 'FILLMODE_', 'fillmode');
        self::assertEnumValue($horizontalAlignment, 'HORIZONTAL_ALIGNMENT_', 'horizontalAlignment');
        self::assertEnumValue($verticalAlignment, 'VERTICAL_ALIGNMENT_', 'verticalAlignment');
        $backgroundImage = self::normalizeContent(array(
            'fillmode' => $fillmode,
            'horizontalAlignment' => $horizontalAlignment,
            'url' => $url ? (string) $url : null,
            'verticalContentAlignment' => $verticalAlignment,
        ));
        if (\count($backgroundImage) > 1 && $this->fields['version'] < 1.2) {
            throw new InvalidArgumentException('backgroundImage fillmode, horizontalAlignment, & verticalAlignment values required card version 1.2 or greater');
        }
        return \count($backgroundImage) > 1
            ? $this->with('backgroundImage', $backgroundImage)
            : $this->with('backgroundImage', $url);
    }

    /**
     * Return new instance with given fallbackText
     *
     * @param string $text Fallback text
     *
     * @return static
     */
    public function withFallbackText($text)
    {
        $text = self::asString($text, true, __METHOD__);
        return $this->with('fallbackText', $text);
    }

    /**
     * Return new instance with given lang
     *
     * @param string $lang The 2-letter ISO-639-1 language used in the card.
     *                       Used to localize any date/time functions
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withLang($lang)
    {
        $lang = self::asString($lang, true, __METHOD__);
        if (\is_string($lang) && \strlen($lang) !== 2) {
            throw new InvalidArgumentException('Lang must be a 2-letter string');
        }
        return $this->with('lang', $lang);
    }

    /**
     * Return new instance with given min height
     *
     * @param string $minHeight Min height
     *
     * @return static
     */
    public function withMinHeight($minHeight)
    {
        self::assertPx($minHeight, __METHOD__);
        return $this->with('minHeight', $minHeight);
    }

    /**
     * Return new instance with specified RTL
     *
     * When true content in this Adaptive Card should be presented right to left.
     * When ‘false’ content in this Adaptive Card should be presented left to right.
     * If unset, the default platform behavior will apply.
     *
     * @param bool $rtl RTL?
     *
     * @return static
     */
    public function withRtl($rtl = true)
    {
        self::assertBool($rtl, 'rtl');
        return $this->with('rtl', $rtl);
    }

    /**
     * Return new instance with specified select action
     *
     * An Action that will be invoked when the Container is tapped or
     * selected.
     *
     * Action.ShowCard is not supported.
     *
     * @param ActionInterface|null $action select action
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withSelectAction($action = null)
    {
        self::assertType($action, 'bdk\Teams\Actions\ActionInterface');

        if ($action && $action->get('type') === 'Action.ShowCard') {
            throw new InvalidArgumentException('AdaptiveCard selectAction does not support ShowCard');
        }
        return $this->with('selectAction', $action);
    }

    /**
     * Return new instance with specified speak value
     *
     * @param string|null $speak what should be spoken for this entire card. This is simple text or SSML fragment.
     *
     * @return static
     */
    public function withSpeak($speak = null)
    {
        return $this->with('speak', $speak);
    }

    /**
     * Return new instance with specified version
     *
     * NOTE: Version is not required for cards within an `Action.ShowCard`. However, it is required for the top-level card.
     *
     * @param float $version Card version
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withVersion($version)
    {
        if ($version !== null && \in_array($version, $this->supportedVersions, true) === false) {
            throw new InvalidArgumentException('Invalid version');
        }
        return $this->with('version', $version);
    }

    /**
     * Return new instance with specified vertical alignment
     *
     * @param Enums::VERTICAL_ALIGNMENT_* $alignment Vertical alignment
     *
     * @return static
     */
    public function withVerticalContentAlignment($alignment)
    {
        self::assertEnumValue($alignment, 'VERTICAL_ALIGNMENT_', 'alignment');
        return $this->with('verticalContentAlignment', $alignment);
    }
}
