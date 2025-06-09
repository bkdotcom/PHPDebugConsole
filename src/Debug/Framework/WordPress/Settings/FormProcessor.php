<?php

namespace bdk\Debug\Framework\WordPress\Settings;

use bdk\Debug\Utility\ArrayUtil;

/**
 * "Sanitize" form data for the given form controls
 */
class FormProcessor
{
    /** @var array */
    private static $postData = array();

    /** @var array */
    private static $values = array();

    /**
     * For the given form controls, get the values from passed postData
     *
     * extraneous/invalid values are stripped
     *
     * @param array $controls Form controls
     * @param array $postData $_POST data
     *
     * @return array
     */
    public static function getValues(array $controls, array $postData)
    {
        self::$postData = $postData;
        \array_walk($controls, [__CLASS__, 'doControl']);
        // \add_settings_error(self::GROUP_NAME, 'bdk-debug-options_key', 'This is a test error', 'error');
        // \add_settings_error(self::GROUP_NAME, 'bdk-debug-options_key', 'This is another error', 'error');
        return self::$values;
    }

    /**
     * For the given control, update self::$values
     *
     * @param array $control Control definition
     *
     * @return void
     */
    private static function doControl(array $control)
    {
        $nameParts = self::getNameParts($control);
        if ($control['type'] === 'checkbox' && \count($control['options']) > 1) {
            self::doCheckboxGroup($control);
            return;
        }
        if ($control['type'] === 'checkbox') {
            // single checkbox
            $isChecked = ArrayUtil::pathGet(self::$postData, $nameParts) === $control['options'][0]['value'];
            ArrayUtil::pathSet(self::$values, $nameParts, $isChecked);
            return;
        }
        if (\in_array($control['type'], ['radio', 'select'], true)) {
            self::doRadioSelect($control);
            return;
        }
        $value = ArrayUtil::pathGet(self::$postData, $nameParts);
        if ($control['type'] === 'number' && \is_numeric($value)) {
            $value = $value * 1;
        }
        ArrayUtil::pathSet(self::$values, $nameParts, $value);
    }

    /**
     * Get Checkbox group values
     *
     * @param array $control Control definition
     *
     * @return void
     */
    private static function doCheckboxGroup(array $control)
    {
        $nameParts = self::getNameParts($control);
        $submittedValues = ArrayUtil::pathGet(self::$postData, $nameParts, array());
        if (\is_array($submittedValues) === false) {
            return;
        }
        foreach ($control['options'] as $option) {
            if (\strpos($option['name'], $control['name']) === 0) {
                if (\in_array($option['value'], $submittedValues, true)) {
                    $path = \array_merge($nameParts, ['__push__']);
                    ArrayUtil::pathSet(self::$values, $path, $option['value']);
                }
                continue;
            }
            $nameParts = self::getNameParts($option);
            $isChecked = ArrayUtil::pathGet(self::$postData, $nameParts) === $option['value'];
            ArrayUtil::pathSet(self::$values, $nameParts, $isChecked);
        }
    }

    /**
     * Get radio or select value
     *
     * @param array $control Control definition
     *
     * @return void
     */
    private static function doRadioSelect(array $control)
    {
        $nameParts = self::getNameParts($control);
        $submittedValue = ArrayUtil::pathGet(self::$postData, $nameParts);
        $allowedValues = \array_column($control['options'], 'value');
        if (\in_array($submittedValue, $allowedValues, true) === false) {
            // invalid valiue
            return;
        }
        if (\in_array($submittedValue, ['true', 'false', 'null'], true)) {
            $submittedValue = \json_decode($submittedValue);
        }
        ArrayUtil::pathSet(self::$values, $nameParts, $submittedValue);
    }

    /**
     * Split name into parts
     *
     * fruits[tropical][banana]  => ['fruits', 'tropical', 'banana']
     *
     * @param array $attribs Control definition or attributes
     *
     * @return array
     */
    private static function getNameParts(array $attribs)
    {
        $matches = [];
        \preg_match_all('/\[?([^\[\]]+)\]?/', $attribs['name'], $matches);
        return $matches[1];
    }
}
