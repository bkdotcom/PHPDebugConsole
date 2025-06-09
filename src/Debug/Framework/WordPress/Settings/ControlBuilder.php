<?php

namespace bdk\Debug\Framework\WordPress\Settings;

use bdk\Debug\Utility\Html;

/**
 * Build a form control (label, input, "describedBy" etc)
 */
class ControlBuilder
{
    /** @var array */
    protected $cfg = array();

    /** @var array */
    protected $defaultProperties = array(
        'attribs' => array(),
        'default' => null,
        'describedBy' => '',
        'id' => '',
        'label' => '',
        'name' => '',
        'options' => array(), // for checkbox / radio / select
        'required' => false,
        'section' => 'general',
        'type' => 'text',
        'value' => null,
        'wpLabelFor' => null,
        'wpTrClass' => null,
    );

    /** @var Html */
    protected $htmlUtil;

    /**
     * Constructor
     *
     * @param array $cfg      Configuration
     * @param Html  $htmlUtil Html utility class
     */
    public function __construct(array $cfg = array(), $htmlUtil = null)
    {
        $this->cfg = \array_merge(array(
            'getValue' => static function (array $control) { // @phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
                return null;
            },
            'groupName' => null, // controls will be grouped under this name in $_POST
            'haveValues' => false, // will use default value if no value is set
        ), $cfg);
        $this->htmlUtil = $htmlUtil ?: new Html();
    }

    /**
     * Build a control's html markup
     *
     * @param array $control Control definition
     *
     * @return string
     */
    public function build(array $control)
    {
        $html = '';
        switch ($control['type']) {
            case 'checkbox':
            case 'radio':
                $html = $this->buildCheckboxRadio($control) . "\n";
                break;
            case 'select':
                $html = $this->buildSelect($control) . "\n";
                break;
            default:
                $html = $this->buildDefault($control) . "\n";
        }
        if ($control['describedBy']) {
            $html .= '<div class="description" id="' . $control['id'] . '-description">' . $control['describedBy'] . '</div>' . "\n";
        }
        return $html;
    }

    /**
     * "Prep" a field's attributes / merge with defaults
     *
     * @param array $control Control definition
     *
     * @return array
     */
    public function fieldPrep(array $control)
    {
        $control = \array_merge($this->defaultProperties, $control);
        $control['name'] = $this->fieldPrepName($control);

        if (empty($control['id'])) {
            $control['id'] = \trim(\preg_replace('/[\[\]_]+/', '_', $control['name']), '_');
        }

        $control = $this->fieldPrepValue($control);

        if (\in_array($control['type'], ['checkbox', 'radio', 'select'], true)) {
            $control = $this->fieldPrepCheckboxRadioSelect($control);
        }

        return $this->fieldPrepWpLabelFor($control);
    }

    /**
     * Build markup for checkbox / radio input(s)
     *
     * @param array $control Control definition
     *
     * @return string HTML
     */
    private function buildCheckboxRadio(array $control)
    {
        $options = \array_map(function (array $option) use ($control) {
            return $this->htmlUtil->buildTag(
                'label',
                array(),
                $this->htmlUtil->buildTag('input', array(
                    'checked' => $option['checked'],
                    'id' => $option['id'],
                    'name' => $option['name'],
                    'type' => $control['type'],
                    'value' => $option['value'],
                )) . ' ' . $option['label']
            );
        }, $control['options']);
        return \implode('<br />' . "\n", $options);
    }

    /**
     * Build text type input
     *
     * @param array $control Control definition
     *
     * @return string HTML
     */
    private function buildDefault(array $control)
    {
        return $this->htmlUtil->buildTag('input', \array_merge(array(
            'aria-describedBy' => $control['describedBy'] ? $control['id'] . '-description' : null,
            'id' => $control['id'],
            'name' => $control['name'],
            'required' => $control['required'],
            'type' => $control['type'],
            'value' => $control['value'],
        ), $control['attribs']));
    }

    /**
     * Build select dropdown
     *
     * @param array $control Control definition
     *
     * @return string HTML
     */
    private function buildSelect(array $control)
    {
        $options = array();
        if ($control['required']) {
            $options[] = $this->htmlUtil->buildTag('option', array(
                'disabled' => true,
                'selected' => empty($selected),
            ), 'select');
        }
        \array_walk($control['options'], static function ($option) use (&$options) {
            $options[] = \bdk\Debug\Utility\Html::buildTag('option', array(
                'selected' => $option['checked'],
                'value' => $option['value'],
            ), $option['label']);
        });
        return $this->htmlUtil->buildTag(
            'select',
            \array_merge(array(
                'aria-describedBy' => $control['describedBy'] ? $control['id'] . '-description' : null,
                'id' => $control['id'],
                'name' => $control['name'],
                'required' => $control['required'],
            ), $control['attribs']),
            "\n  " . \implode("\n  ", $options) . "\n"
        );
    }

    /**
     * Prep checkbox / checkbox group / radio group
     *
     * @param array $control Control definition
     *
     * @return array
     */
    private function fieldPrepCheckboxRadioSelect(array $control)
    {
        if ($control['type'] === 'checkbox' && empty($control['options'])) {
            // single checkbox
            $control['options'] = array(
                'on' => '',
            );
        }

        $options = [];
        foreach ($control['options'] as $key => $option) {
            $options[] = $this->fieldPrepOption($control, $key, $option);
        }
        $control['options'] = $options;

        return $control;
    }

    /**
     * Get the field's name attribute
     *
     * @param array $info Control/option definition
     *
     * @return string
     */
    private function fieldPrepName(array $info)
    {
        if (empty($this->cfg['groupName'])) {
            return $info['name'];
        }
        \preg_match_all('/\[?([^\[\]]+)\]?/', $info['name'], $matches);
        $nameParts = $matches[1];
        if ($nameParts[0] !== $this->cfg['groupName']) {
            \array_unshift($nameParts, $this->cfg['groupName']);
        }
        return $nameParts[0] . (\count($nameParts) > 1
            ? '[' . \implode('][', \array_slice($nameParts, 1)) . ']'
            : '');
    }

    /**
     * Prep a checkbox / radio / select option
     *
     * @param array        $control Control definition
     * @param array-key    $key     Option key/index (possibly value)
     * @param array|string $option  option label or array of option attributes
     *
     * @return array
     */
    private function fieldPrepOption($control, $key, $option)
    {
        $isCheckboxGroup = $control['type'] === 'checkbox' && \count($control['options']) > 1;
        $isCheckboxSingle = $control['type'] === 'checkbox' && \count($control['options']) === 1;
        if (\is_array($option) === false) {
            $option = array(
                'label' => $option,
                'value' => $key,
            );
        } elseif (!empty($option['name'])) {
            $option['name'] = $this->fieldPrepName($option);
        }
        $option = \array_merge(array(
            'label' => $key,
            'name' => $control['name'] . ($isCheckboxGroup ? '[]' : ''),
            'value' => 'on',
        ), $option);

        return \array_merge(array(
            'checked' => $this->isOptionChecked($control, $key, $option),
            'id' => \trim(\preg_replace(
                '/[\[\]_]+/',
                '_',
                $option['name'] . ($isCheckboxSingle ? '' : '_' . $option['value'])
            ), '_'),
        ), $option);
    }

    /**
     * Set control value
     *
     * @param array $control Control definition
     *
     * @return array Updated control definition
     */
    private function fieldPrepValue(array $control)
    {
        if ($control['value'] === null && \is_callable($this->cfg['getValue'])) {
            $control['value'] = $this->cfg['getValue']($control);
        }
        if ($control['value'] === null && $this->cfg['haveValues'] === false) {
            $control['value'] = $control['default'];
        }
        return $control;
    }

    /**
     * Set control options
     *
     * @param array $control Control definition
     *
     * @return array Updated control definition
     */
    private function fieldPrepWpLabelFor(array $control)
    {
        $isCheckboxRadioGroup = \in_array($control['type'], ['checkbox', 'radio'], true) && \count($control['options']) > 1;
        $setWPLabelFor = empty($control['wpLabelFor']) && !$isCheckboxRadioGroup;
        if (!$setWPLabelFor) {
            return $control;
        }
        $control['wpLabelFor'] = $control['type'] === 'checkbox'
            ? $control['options'][0]['id']
            : $control['id'];
        return $control;
    }

    /**
     * Get checkbox / radio / select option value
     *
     * @param array     $control Control definition
     * @param array-key $key     Option key/index
     * @param array     $option  Option attributes
     *
     * @return bool
     */
    private function isOptionChecked(array $control, $key, array $option)
    {
        $value = $this->cfg['haveValues']
            ? $this->cfg['getValue']($option)
            : $control['value'];
        if (\in_array($value, [true, false, null], true)) {
            $value = \json_encode($value);
        }
        return \in_array($option['value'], (array) $value, true)
            || \in_array($key, (array) $value, true)
            || ($value === 'true' && $option['value'] === 'on');
    }
}
