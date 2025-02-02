<?php

/**
 * @package   bdk\slack
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Slack;

use BadMethodCallException;
use InvalidArgumentException;
use JsonSerializable;
use OverflowException;
use UnexpectedValueException;

/**
 * Represent a Slack message payload "composition"
 *
 * Think of this like PSR-7's MessageInterface
 *
 * @method static withActions(array $elements, array $values)
 * @method static withContext(array $elements, array $values)
 * @method static withDivider()
 * @method static withHeader(string $text, array $values)
 * @method static withImage(string $url, string $altText, string $title, array $values)
 * @method static withInput(string $label, array $element, array $values)
 * @method static withSection(string $text, array $fields, array $accessory, array $values)
 * @method static withVideo(string $url, string $title, string $altText, array $values)
 *
 * @link https://api.slack.com/messaging/composing
 * @link https://api.slack.com/reference/messaging/payload
 * @link https://api.slack.com/docs/message-attachments
 * @link https://api.slack.com/reference/messaging/attachments
 * @link https://api.slack.com/methods/chat.postMessage
 */
class SlackMessage implements JsonSerializable
{
    use AssertionTrait;

    /**
     * @var array{
     *   attachments: list<array>,
     *   blocks: list<array>,
     *   ...<string, mixed>
     * }
     */
    protected $dataDefault = array( // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        'attachments' => array(),
        'blocks' => array(),
        'mrkdwn' => true,
        'text' => null, // fallback if using blocks
        'thread_ts' => null, // The ID of another un-threaded message to reply to.
        //
        'channel' => null,
        'icon_emoji' => null,
        'icon_url' => null,
        'link_names' => null,
        'metadata' => null,
        'parse' => null, // full | none
        'reply_broadcast' => false, // Used in conjunction with thread_ts and indicates whether reply should be made visible to everyone in the channel or conversation.
        'unfurl_links' => false, // bool
        'unfurl_media' => true, // bool
        'username' => null,
    );

    /**
     * @var array{
     *   attachments: list<array>,
     *   blocks: list<array>,
     *   ...<string, mixed>
     * }
     */
    protected $data = array(
        'attachments' => array(),
        'blocks' => array(),
    );

    /** @var BlockFactory|null */
    private $blockFactory;

    /**
     * Construct
     *
     * @param array<string,mixed> $values Initial SlackMessage values
     *
     * @throws InvalidArgumentException
     */
    public function __construct(array $values = array())
    {
        $this->setData($values);
    }

    /**
     * Magic call method
     *
     * @param string $method method being called
     * @param array  $args   method arguments
     *
     * @return mixed
     *
     * @throws BadMethodCallException
     */
    public function __call($method, array $args)
    {
        $factoryMethods = [
            'withActions',
            'withContext',
            'withDivider',
            'withHeader',
            'withImage',
            'withInput',
            'withSection',
            'withVideo',
        ];
        if (\in_array($method, $factoryMethods, true)) {
            $method = \strtolower(\substr($method, 4));
            /** @var array<string,mixed> */
            $block = \call_user_func_array([$this->getBlockFactory(), $method], $args);
            return $this->withBlock($block);
        }
        throw new BadMethodCallException($method . ' is not a recognized method');
    }

    /**
     * @return BlockFactory
     */
    public function getBlockFactory()
    {
        if ($this->blockFactory === null) {
            $this->blockFactory = new BlockFactory();
        }
        return $this->blockFactory;
    }

    /**
     * Returns required data in format that Slack is expecting.
     *
     * @return array<string,mixed>
     */
    public function getData()
    {
        $data = \array_merge($this->dataDefault, $this->data);
        if ($data['text'] === null) {
            $data['mrkdwn'] = null;
        }
        if ($data['blocks'] === array()) {
            unset($data['blocks']);
        }
        if ($data['attachments'] === array()) {
            unset($data['attachments']);
        }
        $data = $this->removeNull($data);

        \ksort($data);
        return $data;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return array<string,mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->getData();
    }

    /**
     * Returns new instance populated with values
     *
     * @param array<string,mixed> $values Request values
     *
     * @return static
     */
    public function withData(array $values)
    {
        $new = clone $this;
        $new->setData($values);
        return $new;
    }

    /**
     * Append new attachment to message
     *
     *    withAttachment(array $attachment)
     *    withAttachment(string $text, array $blocks, array $values)
     *
     * @param array|string $attachment New attachment
     *
     * @return static
     *
     * @throws OverflowException
     * @throws UnexpectedValueException
     */
    public function withAttachment($attachment = array())
    {
        if (\is_array($attachment) === false) {
            /** @var array<string,mixed> */
            $attachment = \call_user_func_array([$this->getBlockFactory(), 'attachment'], \func_get_args());
        }
        $new = clone $this;
        /**
         * Psalm bug - attachments becomes list<array<array-key, mixed>>|mixed
         *
         * @psalm-suppress MixedArrayAssignment
         * @psalm-suppress MixedPropertyTypeCoercion
         */
        $new->data['attachments'][] = $attachment;
        $new->assertAttachmentCount(\count($new->data['attachments']));
        return $new;
    }

    /**
     * Replace existing attachments with new attachments
     *
     * @param array<string,mixed>[] $attachments New attachments
     *
     * @return static
     *
     * @throws OverflowException
     * @throws UnexpectedValueException
     */
    public function withAttachments(array $attachments = array())
    {
        $new = clone $this;
        $new->data['attachments'] = array();
        foreach ($attachments as $attachment) {
            $new = $new->withAttachment($attachment);
        }
        return $new;
    }

    /**
     * Append new block to message
     *
     * @param array $block New block
     *
     * @return static
     */
    public function withBlock(array $block = array())
    {
        $new = clone $this;
        /**
         * Psalm bug - blocks becomes list<array<array-key, mixed>>|mixed
         *
         * @psalm-suppress MixedArrayAssignment
         * @psalm-suppress MixedPropertyTypeCoercion
         */
        $new->data['blocks'][] = $block;
        return $new;
    }

    /**
     * Replace existing blocks with new blocks
     *
     * @param array $blocks New blocks
     *
     * @return static
     */
    public function withBlocks(array $blocks = array())
    {
        return $this->withValueDo('blocks', $blocks);
    }

    /**
     * Set the channel to be used by the bot when posting
     *
     * @param string|null $channel Channel name
     *
     * @return static
     */
    public function withChannel($channel = null)
    {
        return $this->withValueDo('channel', $channel);
    }

    /**
     * Set icon e.g. 'ghost', 'http://example.com/user.png
     *
     * @param string|null $icon Icon name or URL
     *
     * @return static
     */
    public function withIcon($icon = null)
    {
        $new = clone $this;
        $new->setIcon($icon);
        return $new;
    }

    /**
     * Set the message text
     *
     * @param string $text     Message text
     * @param bool   $isMrkdwn (true) Markdown?
     *
     * @return static
     */
    public function withText($text, $isMrkdwn = true)
    {
        return $this->withValueDo('text', $text)
            ->withValueDo('mrkdwn', $isMrkdwn);
    }

    /**
     * Set the username to be used by the bot when posting
     *
     * @param string|null $username Name of bot
     *
     * @return static
     */
    public function withUsername($username = null)
    {
        return $this->withValueDo('username', $username);
    }

    /**
     * Set a arbitrary value
     *
     * @param string $key   data key
     * @param mixed  $value value
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withValue($key, $value)
    {
        $method = 'with' . \ucfirst($key);
        if (\method_exists($this, $method)) {
            /** @var static */
            return $this->{$method}($value);
        }
        return $this->withValueDo($key, $value);
    }

    /**
     * Remove null values from array
     *
     * @param array<string,mixed> $values Input array
     *
     * @return array<string,mixed>
     */
    private static function removeNull(array $values)
    {
        return \array_filter($values, static function ($value) {
            return $value !== null;
        });
    }

    /**
     * Set data values
     * Clears all existing values
     *
     * @param array<string,mixed> $values Data values
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private function setData(array $values)
    {
        $this->assertData($values, $this->dataDefault);
        /** @psalm-suppress MixedPropertyTypeCoercion - Psalm bug - we know attachments and blocks are arrays*/
        $this->data = \array_merge(array(
            'attachments' => array(),
            'blocks' => array(),
        ), $values);
        if (\array_key_exists('icon', $values)) {
            unset($this->data['icon']);
            $this->setIcon($values['icon']);
        }
    }

    /**
     * Set icon_url or icon_emoji
     *
     * @param mixed $icon icon to use (null to remove)
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    private function setIcon($icon = null)
    {
        unset($this->data['icon_url'], $this->data['icon_emoji']);
        if ($icon === null || $icon === '') {
            return $this;
        }
        if (\is_string($icon) === false) {
            throw new InvalidArgumentException('icon should be string (url or emoji) or null (to remove)');
        }
        $icon = \trim($icon, ':');
        $iconUrl = \filter_var($icon, FILTER_VALIDATE_URL);
        $key = $iconUrl !== false
            ? 'icon_url'
            : 'icon_emoji';
        $val = $iconUrl !== false
            ? $iconUrl
            : ':' . $icon . ':';
        $this->data[$key] = $val;
        return $this;
    }

    /**
     * Directly set an arbitrary value
     *
     * @param string $key   data key
     * @param mixed  $value [description]
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    private function withValueDo($key, $value)
    {
        if (\array_key_exists($key, $this->dataDefault) === false) {
            throw new InvalidArgumentException(\sprintf('"%s"is an invalid message value', $key));
        }
        $new = clone $this;
        $new->data[$key] = $value;
        return $new;
    }
}
