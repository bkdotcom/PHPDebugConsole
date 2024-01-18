<?php

namespace bdk\Test\Slack;

use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use bdk\Slack\BlockFactory;
use bdk\Slack\SlackMessage;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\Slack\AbstractBlockFactory
 * @covers \bdk\Slack\BlockElementsFactory
 * @covers \bdk\Slack\BlockFactory
 * @covers \bdk\Slack\SlackMessage
 */
class SlackMessageTest extends TestCase
{
    use ExpectExceptionTrait;

    public function testConstrutor()
    {
        $slackMessage = new SlackMessage([
            'text' => 'foo',
        ]);
        self::assertSame([
            'mrkdwn' => true,
            'reply_broadcast' => false,
            'text' => 'foo',
            'unfurl_links' => false,
            'unfurl_media' => true,
        ], $slackMessage->getData());
    }

    public function testWithData()
    {
        $slackMessage = new SlackMessage([
            'text' => 'foo',
            'username' => 'joe',
        ]);
        $slackMessage = $slackMessage->withData([
            'icon' => ':no_entry:',
            'text' => 'bar',
        ]);
        self::assertSame([
            'icon_emoji' => ':no_entry:',
            'mrkdwn' => true,
            'reply_broadcast' => false,
            'text' => 'bar',
            'unfurl_links' => false,
            'unfurl_media' => true,
        ], $slackMessage->getData());
    }

    public function testWithDataThrowsException()
    {
        $this->expectException('InvalidArgumentException');
        $slackMessage = new SlackMessage([
            'foo' => 'bar',
        ]);
    }

    public function testWithAttachment()
    {
        $slackMessage = new SlackMessage();
        $slackMessage = $slackMessage->withAttachment("I'm an attachment");
        self::assertSame([
            'attachments' => [
                [
                    'blocks' => [],
                    'color' => '#dddddd',
                    'text' => "I'm an attachment",
                ],
            ],
            'reply_broadcast' => false,
            'unfurl_links' => false,
            'unfurl_media' => true,
        ], $slackMessage->getData());
    }

    public function testWithAttachments()
    {
        $slackMessage = new SlackMessage();
        $slackMessage = $slackMessage->withAttachments([
            $slackMessage->getBlockFactory()->attachment('attachment 1'),
            $slackMessage->getBlockFactory()->attachment('attachment 2'),
        ]);
        self::assertSame([
            'attachments' => [
                [
                    'blocks' => [],
                    'color' => '#dddddd',
                    'text' => 'attachment 1',
                ],
                [
                    'blocks' => [],
                    'color' => '#dddddd',
                    'text' => 'attachment 2',
                ],
            ],
            'reply_broadcast' => false,
            'unfurl_links' => false,
            'unfurl_media' => true,
        ], $slackMessage->getData());
    }

    public function testThrowsExceptionWithTooManyAttachments()
    {
        $this->expectException('OutOfBoundsException');
        $this->expectExceptionMessage('A maximum of 20 message attachments are allowed. 21 provided');
        $slackMessage = new SlackMessage();
        $attachment = $slackMessage->getBlockFactory()->attachment('some attachment');
        $slackMessage = $slackMessage->withAttachments(\array_fill(0, 21, $attachment));
    }

    public function testWithBlock()
    {
        $slackMessage = new SlackMessage();
        $slackMessage = $slackMessage
            ->withBlock(
                $slackMessage->getBlockFactory()->divider()
            )
            ->withBlock(
                $slackMessage->getBlockFactory()->section('look at me')
            );
        self::assertSame([
            'blocks' => [
                [
                    'type' => 'divider',
                ],
                [
                    'text' => [
                        'text' => 'look at me',
                        'type' => 'mrkdwn',
                    ],
                    'type' => 'section',
                ],
            ],
            'reply_broadcast' => false,
            'unfurl_links' => false,
            'unfurl_media' => true,
        ], $slackMessage->getData());
    }

    public function testWithBlocks()
    {
        $slackMessage = new SlackMessage();
        $slackMessage = $slackMessage
            ->withBlock(
                $slackMessage->getBlockFactory()->divider()
            )
            ->withBlock(
                $slackMessage->getBlockFactory()->section('look at me')
            );
        $imgUrl = 'https://i.pinimg.com/originals/f7/85/7f/f7857f7dc8194d91da6b825d3ab90fce.gif';
        $slackMessage = $slackMessage->withBlocks([
            $slackMessage->getBlockFactory()->image($imgUrl, 'alt text'),
        ]);
        self::assertSame([
            'blocks' => [
                [
                    'alt_text' => 'alt text',
                    'image_url' => $imgUrl,
                    'type' => 'image',
                ],
            ],
            'reply_broadcast' => false,
            'unfurl_links' => false,
            'unfurl_media' => true,
        ], $slackMessage->getData());
    }

    public function testWithChannel()
    {
        $slackMessage = new SlackMessage();
        $slackMessage = $slackMessage->withChannel('#slack-integration');
        self::assertSame([
            'channel' => '#slack-integration',
            'reply_broadcast' => false,
            'unfurl_links' => false,
            'unfurl_media' => true,
        ], $slackMessage->getData());
    }

    public function testWithIcon()
    {
        $slackMessage = new SlackMessage();

        $slackMessage = $slackMessage->withIcon('http://www.example.com/image.png');
        self::assertSame('http://www.example.com/image.png', $slackMessage->getData()['icon_url']);
        self::assertArrayNotHasKey('icon_emoji', $slackMessage->getData());

        $slackMessage = $slackMessage->withIcon(':no_entry:');
        self::assertSame(':no_entry:', $slackMessage->getData()['icon_emoji']);
        self::assertArrayNotHasKey('icon_url', $slackMessage->getData());

        $slackMessage = $slackMessage->withIcon();
        self::assertArrayNotHasKey('icon_emoji', $slackMessage->getData());
        self::assertArrayNotHasKey('icon_url', $slackMessage->getData());
    }

    public function testWithText()
    {
        $slackMessage = new SlackMessage();
        $slackMessage = $slackMessage->withText('hi team', false);
        self::assertSame('hi team', $slackMessage->getData()['text']);
        self::assertSame(false, $slackMessage->getData()['mrkdwn']);
    }

    public function testWithUsername()
    {
        $slackMessage = new SlackMessage();
        $slackMessage = $slackMessage->withUsername('billy');
        self::assertSame('billy', $slackMessage->getData()['username']);
    }

    public function testWithValueThrowsException()
    {
        $slackMessage = new SlackMessage();
        $this->expectException('InvalidArgumentException');
        $slackMessage->withValue('foo', 'bar');
    }

    /**
     * @dataProvider providerMagicMethod
     */
    public function testMagicMethods($method, $args, $blockExpect)
    {
        $slackMessage = new SlackMessage();
        $slackMessage = \call_user_func_array([$slackMessage, $method], $args);
        self::assertSame($blockExpect, $slackMessage->getData()['blocks'][0]);
    }

    public function testCallUnknown()
    {
        $slackMessage = new SlackMessage();
        $this->expectException('BadMethodCallException');
        $slackMessage->wat();
    }

    public function testJsonSerialize()
    {
        $slackMessage = new SlackMessage([
            'text' => 'yippie',
        ]);
        self::assertInstanceOf('JsonSerializable', $slackMessage);
        $json = \json_encode($slackMessage);
        self::assertSame([
            'mrkdwn' => true,
            'reply_broadcast' => false,
            'text' => 'yippie',
            'unfurl_links' => false,
            'unfurl_media' => true,
        ], \json_decode($json, true));
    }

    public static function providerMagicMethod()
    {
        $blockFactory = new BlockFactory();
        $methods = [
            'withActions' => [
                [
                    [
                        $blockFactory->button('buttonId', 'button label'),
                    ],
                ],
                [
                    'elements' => [
                        [
                            'action_id' => 'buttonId',
                            'style' => 'default',
                            'text' => [
                                'text' => 'button label',
                                'type' => 'plain_text',
                            ],
                            'type' => 'button',
                        ],
                    ],
                    'type' => 'actions',
                ],
            ],
            'withContext' => [
                [
                    [
                        $blockFactory->image('http://example.com/img.sgv', 'alt text'),
                    ],
                ],
                [
                    'elements' => [
                        [
                            'alt_text' => 'alt text',
                            'image_url' => 'http://example.com/img.sgv',
                            'type' => 'image',
                        ],
                    ],
                    'type' => 'context',
                ],
            ],
            'withDivider' => [
                [],
                [
                    'type' => 'divider',
                ],
            ],
            'withHeader' => [
                [
                    'Header text',
                ],
                [
                    'text' => [
                        'text' => 'Header text',
                        'type' => 'plain_text',
                    ],
                    'type' => 'header',
                ],
            ],
            'withImage' => [
                [
                    'http://example.com/img.sgv',
                    'alt text',
                ],
                [
                    'alt_text' => 'alt text',
                    'image_url' => "http://example.com/img.sgv",
                    'type' => 'image',
                ],
            ],
            'withInput' => [
                [
                    'input label',
                    $blockFactory->textInput('action-id', [
                        'placeholder' => 'expess yourself',
                    ]),
                ],
                [
                    'dispatch_action' => false,
                    'element' => [
                        'action_id' => 'action-id',
                        'multiline' => false,
                        'placeholder' => [
                            'text' => 'expess yourself',
                            'type' => 'plain_text',
                        ],
                        'type' => 'plain_text_input',
                    ],
                    'label' => [
                        'text' => 'input label',
                        'type' => 'plain_text',
                    ],
                    'optional' => false,
                    'type' => 'input',
                ],
            ],
            'withSection' => [
                [
                    'Section!',
                    [
                        'field 1',
                        'field 2',
                    ],
                ],
                [
                    'fields' => [
                        [
                            'text' => 'field 1',
                            'type' => 'mrkdwn',
                        ],
                        [
                            'text' => 'field 2',
                            'type' => 'mrkdwn',
                        ],
                    ],
                    'text' => [
                        'text' => 'Section!',
                        'type' => 'mrkdwn',
                    ],
                    'type' => 'section',
                ],
            ],
            'withVideo' => [
                [
                    'https://youtu.be/dQw4w9WgXcQ',
                    'definitely not Rick Astley',
                    'You should watch this',
                ],
                [
                    'alt_text' => 'definitely not Rick Astley',
                    'title' => 'You should watch this',
                    'type' => 'video',
                    'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
                ],
            ],
        ];
        foreach ($methods as $method => $args) {
            $methods[$method] = \array_merge([$method], $args);
        }
        return $methods;
    }
}
