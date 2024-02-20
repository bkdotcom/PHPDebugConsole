<?php

namespace bdk\Test\Slack;

use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use bdk\Slack\BlockFactory;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\Slack\AbstractBlockFactory
 * @covers \bdk\Slack\BlockElementsFactory
 * @covers \bdk\Slack\BlockFactory
 */
class BlockFactoryTest extends TestCase
{
    use ExpectExceptionTrait;

    protected static $blockFactory;

    public static function setUpBeforeClass(): void
    {
        self::$blockFactory = new BlockFactory();
    }

    /**
     * @dataProvider methodProvider
     */
    public function testMethods($method, $args, $expect)
    {
        if (isset($expect['expectException'])) {
            $this->expectException($expect['expectException']);
        }
        if (isset($expect['expectExceptionMessage'])) {
            $this->expectExceptionMessage($expect['expectExceptionMessage']);
        }
        $block = \call_user_func_array([self::$blockFactory, $method], $args);
        self::assertSame($expect, $block);
    }

    public static function methodProvider()
    {
        $blockFactory = new BlockFactory();
        return [
            'actions' => [
                'actions',
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

            'actions.elements.invalid' => [
                'actions',
                [
                    [
                        'some string',
                    ],
                ],
                [
                    'expectException' => 'InvalidArgumentException',
                    'expectExceptionMessage' => 'Invalid element` (index 0).  string provided.',
                ],
            ],

            'actions.elements.invalid.type' => [
                'actions',
                [
                    [
                        $blockFactory->url('urlId'),
                    ],
                ],
                [
                    'expectException' => 'InvalidArgumentException',
                    'expectExceptionMessage' => 'actions block:  Invalid element (index 0).  url_text_input is an invalid type.',
                ],
            ],

            'actions.elements.empty' => [
                'actions',
                [
                    [],
                ],
                [
                    'expectException' => 'LengthException',
                    'expectExceptionMessage' => 'actions block:  At least one element is required.',
                ],
            ],

            'actions.elements.notArray' => [
                'actions',
                [
                    [],
                    [
                        'elements' => null,
                    ],
                ],
                [
                    'expectException' => 'InvalidArgumentException',
                    'expectExceptionMessage' => 'actions block:  elements must be array.  NULL provided.',
                ],
            ],


            'actions.elements.tooMany' => [
                'actions',
                [
                    \array_fill(0, 26, $blockFactory->button('buttonId', 'button label')),
                ],
                [
                    'expectException' => 'OverflowException',
                    'expectExceptionMessage' => 'actions block:  A maximum of 25 elements are allowed.  26 provided.',
                ],
            ],

            'context' => [
                'context',
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

            'context.elements.empty' => [
                'context',
                [
                    [],
                ],
                [
                    'expectException' => 'LengthException',
                    'expectExceptionMessage' => 'context block:  At least one element is required.',
                ],
            ],

            'context.elements.tooMany' => [
                'context',
                [
                    \array_fill(0, 11, 'hello world'),
                ],
                [
                    'expectException' => 'OverflowException',
                    'expectExceptionMessage' => 'context block:  A maximum of 10 elements are allowed.  11 provided.',
                ],
            ],
            'context.element.notArray' => [
                'context',
                [
                    array(),
                    array(
                        'elements' => null,
                    ),
                ],
                [
                    'expectException' => 'UnexpectedValueException',
                    'expectExceptionMessage' => 'context block:  elements must be array.  NULL provided.',
                ],
            ],

            'divider' => [
                'divider',
                [],
                [
                    'type' => 'divider',
                ],
            ],

            'header' => [
                'header',
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

            'image' => [
                'image',
                [
                    'http://example.com/img.png',
                    'alt text',
                ],
                [
                    'alt_text' => 'alt text',
                    'image_url' => 'http://example.com/img.png',
                    'type' => 'image',
                ],
            ],

            'input' => [
                'input',
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

            'input.invalid' => [
                'input',
                [
                    'input label',
                    'some string',
                ],
                [
                    'expectException' => 'InvalidArgumentException',
                    'expectExceptionMessage' => 'Invalid input block.  string provided.',
                ],
            ],

            'input.invalid.type' => [
                'input',
                [
                    'input label',
                    $blockFactory->button('buttonId', 'button label'),
                ],
                [
                    'expectException' => 'InvalidArgumentException',
                    'expectExceptionMessage' => 'invalid input block.  button is an invalid type.',
                ],
            ],

            'section' => [
                'section',
                [
                    'Section!',
                    null,
                ],
                [
                    'text' => [
                        'text' => 'Section!',
                        'type' => 'mrkdwn',
                    ],
                    'type' => 'section',
                ],
            ],

            'section.all.arguments' => [
                'section',
                [
                    'Section!',
                    [
                        'field 1',
                        'field 2',
                    ],
                    $blockFactory->image('http://example.com/img.png', 'alt text'),
                    [
                        'block_id' => 'schmock id',
                    ]
                ],
                [
                    'accessory' => [
                        'alt_text' => 'alt text',
                        'image_url' => 'http://example.com/img.png',
                        'type' => 'image',
                    ],
                    'block_id' => 'schmock id',
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

            'section.accessory.invalid' => [
                'section',
                [
                    'I have an accessory',
                    [],
                    [], // no type
                ],
                [
                    'expectException' => 'InvalidArgumentException',
                    'expectExceptionMessage' => 'Invalid accessory.  type not set',
                ],
            ],

            'section.accessory.invalidType' => [
                'section',
                [
                    'I have an accessory',
                    [],
                    $blockFactory->url('actionid'),
                ],
                [
                    'expectException' => 'InvalidArgumentException',
                    'expectExceptionMessage' => 'Invalid accessory.  url_text_input is an invalid type.',
                ],
            ],

            'section.accessory.elementsNotArray' => [
                'section',
                [
                    'I have an accessory',
                    new \stdClass(),
                ],
                [
                    'expectException' => 'UnexpectedValueException',
                    'expectExceptionMessage' => 'section block:  fields must be array or null.  stdClass provided.',
                ],
            ],


            'section.accessory.typeNotString' => [
                'section',
                [
                    'I have an accessory',
                    [],
                    [
                        'type' => new \stdClass(),
                    ],
                ],
                [
                    'expectException' => 'InvalidArgumentException',
                    'expectExceptionMessage' => 'Invalid accessory.  type must be a string.  stdClass provided.',
                ],
            ],

            'section.fields.tooMany' => [
                'section',
                [
                    'I have an accessory',
                    \array_fill(0, 11, 'field'),
                ],
                [
                    'expectException' => 'OverflowException',
                    'expectExceptionMessage' => 'section block:  A maximum of 10 fields are allowed.  11 provided.',
                ],
            ],

            'video' => [
                'video',
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

            'attachment' => [
                'attachment',
                [
                    'Attach This!',
                    [
                        $blockFactory->image('http://example.com/img.png', 'alt text'),
                    ],
                ],
                [
                    'blocks' => [
                        [
                            'alt_text' => 'alt text',
                            'image_url' => 'http://example.com/img.png',
                            'type' => 'image',
                        ],
                    ],
                    'color' => BlockFactory::COLOR_DEFAULT,
                    'text' => 'Attach This!',
                ],
            ],

            'attachment.fields' => [
                'attachment',
                [
                    'Attach This!',
                    [
                        $blockFactory->image('http://example.com/img.png', 'alt text'),
                    ],
                    [
                        'fields' => [
                            'field 1',
                            [],
                        ],
                    ],
                ],
                [
                    'blocks' => [
                        [
                            'alt_text' => 'alt text',
                            'image_url' => 'http://example.com/img.png',
                            'type' => 'image',
                        ],
                    ],
                    'color' => BlockFactory::COLOR_DEFAULT,
                    'fields' => [
                        [
                            'short' => false,
                            'value' => 'field 1',
                        ],
                    ],
                    'text' => 'Attach This!',
                ],
            ],

            'attachment.fieldsInvalid' => [
                'attachment',
                [
                    'Attach This!',
                    [
                        $blockFactory->image('http://example.com/img.png', 'alt text'),
                    ],
                    [
                        'fields' => new \stdClasS(),
                    ],
                ],
                [
                    'expectException' => 'UnexpectedValueException',
                    'expectExceptionMessage' => 'attachment block:  fields must be array or null.  stdClass provided.',
                ],
            ],

            'imageElement' => [
                'imageElement',
                [
                    'http://example.com/img.png',
                    'an image is worth one thousand words',
                ],
                [
                    'alt_text' => 'an image is worth one thousand words',
                    'image_url' => 'http://example.com/img.png',
                    'type' => 'image',
                ],
            ],

            'button' => [
                'button',
                [
                    'action id',
                    'button label',
                    'button value',
                    [
                        'style' => 'danger',
                    ],
                ],
                [
                    'action_id' => 'action id',
                    'style' => 'danger',
                    'text' => [
                        'text' => 'button label',
                        'type' => 'plain_text',
                    ],
                    'type' => 'button',
                    'value' => 'button value',
                ],
            ],

            'button.typeNotOverridable' => [
                'button',
                [
                    'action id',
                    'label',
                    42,
                    [
                        'type' => 'bogusType',
                    ],
                ],
                [
                    'action_id' => 'action id',
                    'style' => 'default',
                    'text' => [
                        'text' => 'label',
                        'type' => 'plain_text',
                    ],
                    'type' => 'button',
                    'value' => 42,
                ],
            ],

            'button.invalidText' => [
                'button',
                [
                    'action id',
                    new \stdClass(),
                ],
                [
                    'expectException' => 'UnexpectedValueException',
                    'expectExceptionMessage' => 'button text should be string, numeric, null, or array containing valid text value.  stdClass provided.',
                ],
            ],

            'checkboxes' => [
                'checkboxes',
                [
                    'action id',
                    [
                        'One',
                        'Two',
                    ],
                ],
                [
                    'action_id' => 'action id',
                    'options' => [
                        [
                            'text' => [
                                'text' => 'One',
                                'type' => 'plain_text',
                            ],
                            'value' => 'One',
                        ],
                        [
                            'text' => [
                                'text' => 'Two',
                                'type' => 'plain_text',
                            ],
                            'value' => 'Two',
                        ],
                    ],
                    'type' => 'checkboxes',
                ],
            ],

            'checkboxes.invalidOption' => [
                'checkboxes',
                [
                    'action id',
                    [
                        new \stdClass(),
                    ],
                ],
                [
                    'expectException' => 'UnexpectedValueException',
                    'expectExceptionMessage' => 'Option should be string, numeric, null, or array containing valid text value.  stdClass provided.',
                ],
            ],

            'datePicker' => [
                'datePicker',
                [
                    'action id',
                    [
                        'default' => '2023-02-03',
                        'placeholder' => 'date when now',
                    ],
                ],
                [
                    'action_id' => 'action id',
                    'initial_date' => '2023-02-03',
                    'placeholder' => [
                        'text' => 'date when now',
                        'type' => 'plain_text',
                    ],
                    'type' => 'datepicker',
                ],
            ],

            'dateTimePicker' => [
                'dateTimePicker',
                [
                    'action id',
                    [
                        'default' => \time(),
                    ],
                ],
                [
                    'action_id' => 'action id',
                    'initial_date_time' => \time(),
                    'type' => 'datetimepicker',
                ],
            ],

            'email' => [
                'email',
                [
                    'action id',
                    [
                        'default' => 'test@test.com',
                        'placeholder' => 'we needs it!',
                    ],
                ],
                [
                    'action_id' => 'action id',
                    'initial_value' => 'test@test.com',
                    'placeholder' => [
                        'text' => 'we needs it!',
                        'type' => 'plain_text',
                    ],
                    'type' => 'email_text_input',
                ],
            ],

            'number' => [
                'number',
                [
                    [
                        'default' => 42,
                        'placeholder' => 'pick a number',
                    ],
                ],
                [
                    'initial_value' => 42,
                    'is_decimal_allowed' => false,
                    'placeholder' => [
                        'text' => 'pick a number',
                        'type' => 'plain_text',
                    ],
                    'type' => 'number_input',
                ],
            ],

            'overflow' => [
                'overflow',
                [
                    'action id',
                    [
                        'One',
                        'Two',
                    ],
                ],
                [
                    'action_id' => 'action id',
                    'options' => [
                        [
                            'text' => [
                                'text' => 'One',
                                'type' => 'plain_text',
                            ],
                            'value' => 'One',
                        ],
                        [
                            'text' => [
                                'text' => 'Two',
                                'type' => 'plain_text',
                            ],
                            'value' => 'Two',
                        ],
                    ],
                    'type' => 'overflow',
                ],
            ],

            'overflow.tooMany' => [
                'overflow',
                [
                    'action id',
                    [
                        'One',
                        'Two',
                        'Three',
                        'Four',
                        'Five',
                        'Six',
                    ],
                ],
                [
                    'expectException' => 'OverflowException',
                    'expectExceptionMessage' => 'A maximum of 5 options are allowed in overflow element. 6 provided.',
                ],
            ],

            'radio' => [
                'radio',
                [
                    'action id',
                    [
                        'One' => [
                            'description' => 'this option is right',
                            'type' => 'mrkdwn',
                        ],
                        'Two' => [
                            'description' => 'this option is wrong',
                            'type' => 'mrkdwn',
                        ],
                    ],
                    [
                        'default' => 'One',
                    ],
                ],
                [
                    'action_id' => 'action id',
                    'initial_option' => 'One',
                    'options' => [
                        [
                            'description' => 'this option is right',
                            'text' => [
                                'text' => 'One',
                                'type' => 'mrkdwn',
                            ],
                        ],
                        [
                            'description' => 'this option is wrong',
                            'text' => [
                                'text' => 'Two',
                                'type' => 'mrkdwn',
                            ],
                        ],
                    ],
                    'type' => 'radio_buttons',
                ],
            ],

            'select' => [
                'select',
                [
                    'action id',
                    [
                        'One' => 1,
                        'Two' => 2,
                    ],
                    false,
                    [
                        'default' => 'One',
                        'placeholder' => 'Select A Thing',
                    ],
                ],
                [
                    'action_id' => 'action id',
                    'initial_option' => 'One',
                    'options' => [
                        [
                            'text' => [
                                'text' => 'One',
                                'type' => 'plain_text',
                            ],
                            'value' => 1,
                        ],
                        [
                            'text' => [
                                'text' => 'Two',
                                'type' => 'plain_text',
                            ],
                            'value' => 2,
                        ],
                    ],
                    'placeholder' => [
                        'text' => 'Select A Thing',
                        'type' => 'plain_text',
                    ],
                    'type' => 'static_select',
                ],
            ],

            /*
            'select.invalidType' => [
                'select',
                [
                    'action id',
                    [],
                    false,
                    [
                        'type' => 'invalid',
                    ],
                ],
                [
                    'expectException' => 'UnexpectedValueException',
                    'expectExceptionMessage' => 'select: invalid type.  must be either "multi_static_select", or "static_select"',
                ],
            ],
            */

            'select.option_groups' => [
                'select',
                [
                    'action id',
                    [
                        'One',
                        'Two',
                    ],
                    false,
                    [
                        'option_groups' => array(),
                    ],
                ],
                [
                    'action_id' => 'action id',
                    'option_groups' => [],
                    'type' => 'static_select',
                ],
            ],

            'select.multi' => [
                'select',
                [
                    'action id',
                    [
                        'One',
                        'Two',
                    ],
                    true,
                    [
                        'default' => 'One',
                        'placeholder' => 'Select Stuff',
                    ],
                ],
                [
                    'action_id' => 'action id',
                    'initial_options' => [
                        [
                            'text' => [
                                'text' => 'One',
                                'type' => 'plain_text',
                            ],
                            'value' => 'One',
                        ],
                    ],
                    'options' => [
                        [
                            'text' => [
                                'text' => 'One',
                                'type' => 'plain_text',
                            ],
                            'value' => 'One',
                        ],
                        [
                            'text' => [
                                'text' => 'Two',
                                'type' => 'plain_text',
                            ],
                            'value' => 'Two',
                        ],
                    ],
                    'placeholder' => [
                        'text' => 'Select Stuff',
                        'type' => 'plain_text',
                    ],
                    'type' => 'multi_static_select',
                ],
            ],

            'textInput' => [
                'textInput',
                [
                    'action id',
                    [
                        'default' => 'Not enough hours in the day',
                        'multiline' => true,
                        'placeholder' => 'air your grievences',
                    ],
                ],
                [
                    'action_id' => 'action id',
                    'initial_value' => 'Not enough hours in the day',
                    'multiline' => true,
                    'placeholder' => [
                        'text' => 'air your grievences',
                        'type' => 'plain_text',
                    ],
                    'type' => 'plain_text_input',
                ],
            ],

            'timePicker' => [
                'timePicker',
                [
                    'action id',
                    [
                        'default' => '17:00',
                        'placeholder' => 'when now',
                    ],
                ],
                [
                    'action_id' => 'action id',
                    'initial_time' => '17:00',
                    'placeholder' => [
                        'text' => 'when now',
                        'type' => 'plain_text',
                    ],
                    'type' => 'timepicker',
                ],
            ],

            'url' => [
                'url',
                [
                    'action id',
                    [
                        'default' => 'http://www.bradkent.com/',
                        'placeholder' => 'dub dub dub',
                    ],
                ],
                [
                    'action_id' => 'action id',
                    'initial_value' => 'http://www.bradkent.com/',
                    'placeholder' => [
                        'text' => 'dub dub dub',
                        'type' => 'plain_text',
                    ],
                    'type' => 'url_text_input',
                ],
            ],

        ];
    }
}
