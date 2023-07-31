<?php

namespace bdk\Slack;

use BadMethodCallException;
use InvalidArgumentException;
use JsonSerializable;
use OutOfBoundsException;

/**
 * Represent a Slack message payload "composition"
 *
 * Think of this like PSR-7's MessageInterface
 *
 * @method static withActions(array $elements, array $values)
 * @method static withContext(array $elements, array $values)
 * @method static withDivider()
 * @method static withHeader(string $text, array $values)
 * @method static withImage(string url, string $altText, string $title, array $values)
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
    /**
     * @var array<string, mixed>
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

    protected $data = array();

    /** @var BlockFactory */
    private $blockFactory;

    /**
     * Construct
     *
     * @param array<string, mixed> $values Initial SlackMessage values
     *
     * @throws InvalidArgumentException
     */
    public function __construct($values = array())
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
    public function __call($method, $args)
    {
        $factoryMethods = array(
            'withActions',
            'withContext',
            'withDivider',
            'withHeader',
            'withImage',
            'withInput',
            'withSection',
            'withVideo',
        );
        if (\in_array($method, $factoryMethods, true)) {
            $method = \strtolower(\substr($method, 4));
            $block = \call_user_func_array(array($this->getBlockFactory(), $method), $args);
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
     * @return mixed[]
     */
    public function getData()
    {
        $data = \array_merge($this->dataDefault, $this->data);
        if ($data['text'] === null) {
            $data['mrkdwn'] = null;
        }
        if (\count($data['blocks']) === 0) {
            $data['blocks'] = null;
        }
        if (\count($data['attachments']) === 0) {
            $data['attachments'] = null;
        }
        $data = $this->removeNull($data);
        \ksort($data);
        return $data;
    }

    /**
     * Returns new instance populated with values
     *
     * @param array<string,mixed> $values Request values
     *
     * @return static
     */
    public function withData($values)
    {
        $new = clone $this;
        $new->setData($values);
        return $new;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->getData();
    }

    /**
     * Append new attachment to message
     *
     *    withAttachment(array $attachment)
     *    withAttachment(string $text, array $blocks, array $values)
     *
     * @param array $attachment New attachment
     *
     * @return static
     *
     * @throws OutOfBoundsException
     */
    public function withAttachment($attachment = array())
    {
        if (\is_array($attachment) === false) {
            $attachment = \call_user_func_array(array($this->getBlockFactory(), 'attachment'), \func_get_args());
        }
        $new = clone $this;
        $new->data['attachments'][] = $attachment;
        $count = \count($new->data['attachments']);
        if ($count > 20) {
            // according to slack message guidelines:
            // https://api.slack.com/reference/messaging/payload
            throw new OutOfBoundsException(\sprintf('A maximum of 20 message attachments are allowed. %d provided', $count));
        }
        return $new;
    }

    /**
     * Replace existing attachments with new attachments
     *
     * @param array $attachments New attachments
     *
     * @return static
     *
     * @throws OutOfBoundsException
     */
    public function withAttachments($attachments = array())
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
    public function withBlock($block = array())
    {
        $new = clone $this;
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
    public function withBlocks($blocks = array())
    {
        return $this->withValue('blocks', $blocks);
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
        return $this->withValue('channel', $channel);
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
        return $this->withValue('icon', $icon);
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
        return $this->withValue('text', $text)
            ->withValue('mrkdwn', $isMrkdwn);
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
        return $this->withValue('username', $username);
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
        $new = clone $this;
        if ($key === 'icon') {
            return $new->setIcon($value);
        }
        if (\array_key_exists($key, $this->dataDefault) === false) {
            throw new InvalidArgumentException(\sprintf('"%s"is an invalid message value', $key));
        }
        $new->data[$key] = $value;
        return $new;
    }

    /**
     * Set data values
     * Clears all existing values
     *
     * @param array<string,mixed> $values data values
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private function setData($values)
    {
        $unknownData = \array_diff_key($values, $this->dataDefault, \array_flip(array('icon')));
        if ($unknownData) {
            throw new InvalidArgumentException('Unknown values: ' . \implode(', ', \array_keys($unknownData)));
        }
        $this->data = $values;
        if (\array_key_exists('icon', $values)) {
            unset($this->data['icon']);
            $this->setIcon($values['icon']);
        }
    }

    /**
     * Set icon_url or icon_emoji
     *
     * @param string|null $icon icon to use
     *
     * @return static
     */
    private function setIcon($icon = null)
    {
        unset($this->data['icon_url'], $this->data['icon_emoji']);
        if ($icon === null || $icon === '') {
            return $this;
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
     * Remove null values from array
     *
     * @param array $values Input array
     *
     * @return array
     */
    private static function removeNull($values)
    {
        return \array_filter($values, static function ($value) {
            return $value !== null;
        });
    }
}
