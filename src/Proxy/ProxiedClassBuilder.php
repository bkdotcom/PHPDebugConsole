<?php

namespace bdk\Proxy;

use bdk\Proxy\ClassDefFactory;
use bdk\Proxy\Manager;
use InvalidArgumentException;

/**
 * @internal
 *
 * Build class php code based definition
 */
class ProxiedClassBuilder
{
    /** @var array<string,mixed> */
    private $classDef = array();

    /**
     * Builds class contents to a string.
     *
     * @param array $classDef Class definition
     *
     * @return string Class contents as a string, opening PHP tag is not included.
     *
     * @throws InvalidArgumentException
     */
    public function build(array $classDef)
    {
        $this->classDef = $classDef;

        return 'use ' . __NAMESPACE__ . '\\ProxyTrait;'  . "\n\n"
            . \trim($this->buildClassSignature()) . "\n"
            . '{' . "\n"
            . $this->buildClassBody() . "\n"
            . '}';
    }

    /**
     * Builds class body.
     *
     * @return string Class body as a string
     */
    private function buildClassBody()
    {
        return \rtrim(
            $this->buildIndent() . 'use ProxyTrait;' . "\n\n"
            . $this->buildProperties()
            . $this->buildMethods()
        );
    }

    /**
     * Build the extends part of the class signature.
     *
     * @param string $extends classname
     *
     * @return string
     */
    private function buildClassExtends($extends)
    {
        return $extends
            ? 'extends ' . $extends . ' '
            : '';
    }

    /**
     * Builds implements part of the class signature.
     *
     * @param string[] $interfaces A list of interfaces' names with namespaces.
     *
     * @return string Implements section as a string. Empty string is returned when no interfaces were passed.
     */
    private function buildClassImplements(array $interfaces)
    {
        return $interfaces
            ? 'implements ' . \implode(', ', $interfaces) . ' '
            : '';
    }

    /**
     * Builds class signature
     *
     * @return string Class signature as a string.
     */
    private function buildClassSignature()
    {
        $classDef = $this->classDef;
        $isFinal = \in_array('final', $classDef['modifiers'], true);

        if ($isFinal) {
            $classDef['parentClass'] = null; // we can't extend a final class
        }
        if (!$isFinal) {
            $classDef['parentClass'] = $classDef['name'];
            // we don't need to explicitly implement interfaces when extending a class
            $classDef['implements'] = [];
        }
        if ($classDef['isInterface']) {
            $classDef['parentClass'] = null;
            $classDef['interfaces'] = [$classDef['name']];
        }
        $classDef['modifiers'] = \array_diff($classDef['modifiers'], ['abstract']);

        $classSignatureTemplate = '{{modifiers}} class {{shortName}} {{extends}}{{implements}}';
        return \trim(\strtr($classSignatureTemplate, [
            '{{extends}}' => $this->buildClassExtends($classDef['parentClass']),
            '{{implements}}' => $this->buildClassImplements($classDef['interfaces']),
            '{{modifiers}}' => \implode(' ', $classDef['modifiers']),
            '{{shortName}}' => Manager::getProxyShortName($classDef['name']),
        ]));
    }

    /**
     * Builds all methods.
     *
     * @return string
     */
    private function buildMethods()
    {

        $methods = \array_filter($this->classDef['methods'], function ($method) {
            $modifiers = isset($method['modifiers']) ? $method['modifiers'] : ['public'];
            // don't proxy final methods, as they can't be overridden in the proxy class
            return !\in_array('final', $modifiers, true);
        });
        $methods = \array_map(function ($method) {
            return $this->buildMethod($method);
        }, $methods);
        return \implode("\n\n", $methods);
    }

    /**
     * Builds a single method.
     *
     * @param array $method Method definition
     *
     * @return string Method as a string.
     */
    private function buildMethod(array $method)
    {
        $method = \array_merge(ClassDefFactory::$defaultMethod, $method);
        return $this->buildIndent() . $this->buildMethodSignature($method) . "\n"
            . $this->buildIndent() . '{' . "\n"
            . $this->buildMethodBody($method) . "\n"
            . $this->buildIndent() . '}';
    }

    /**
     * Builds a proxy method's body
     *
     * @param array $method Method definition
     *
     * @return string Method body as a string.
     */
    private function buildMethodBody(array $method)
    {
        $lastParamInfo = end($method['parameters']);
        $lastParamInfo = $lastParamInfo
            ? \array_merge(ClassDefFactory::$defaultParam, $lastParamInfo)
            : false;
        $haveVariadicParameter = $lastParamInfo && $lastParamInfo['isVariadic'];
        $template = \in_array('static', $method['modifiers'], true)
            ? '{{return}}self::proxyCallStatic({{name}}, {{paramsCall}});'
            : '{{return}}$this->proxyCall({{name}}, {{paramsCall}});';
        $params = $this->buildMethodCallParams($method['parameters']);

        if ($method['proxyViaFuncGetArgs']) {
            $params = 'func_get_args()';
        } elseif ($haveVariadicParameter && PHP_VERSION_ID < 70400) {
            // array unpacking not supported until php 7.4... use ugly workaround for older versions
            \preg_match('/\[(?:(.*?), )?&?\.\.\.(\$\S+)\]/', $params, $matches);
            $template  = '$proxyParamsTemp = [' . $matches[1] . '];' . "\n"
                . 'foreach (' . $matches[2]. ' as &$value) {' . "\n"
                . '    $proxyParamsTemp[] = &$value;' . "\n"
                . '}' . "\n"
                . $template;
            $params = '$proxyParamsTemp';
        }

        $values = array(
            '{{name}}' => "'" . $method['name'] . "'",
            '{{paramsCall}}' => $params,
            '{{parentClassName}}' => '\\' . $this->classDef['name'],
            '{{return}}' => $this->buildReturn($method),
        );
        if ($this->classDef['isInterface'] && \in_array($method['name'], ['__call', '__callStatic'], true)) {
            // Subject does actually have __call and __callStatic methods
            // we need to proxy the method and arguments to the subject
            $values['{{name}}'] = '$method';
            $values['{{paramsCall}}'] = '$args';
        }
        $output =  \strtr($template, $values);
        $indent = $this->buildIndent(2);
        return $indent . \str_replace("\n", "\n" . $indent, $output);
    }

    /**
     * Builds parameters passed to a proxy's method call.
     *
     * @param array $parameters A map where key is parameter name and value is parameter definition.
     *
     * @return string Parameters as a string. Empty string is returned when no parameters were passed.
     */
    private function buildMethodCallParams(array $parameters)
    {
        $parameters = \array_map(function ($param) {
            $param = \array_merge(ClassDefFactory::$defaultParam, $param);
            return ''
                . ($param['isPassedByReference'] && !$param['isVariadic'] ? '&' : '')
                . ($param['isVariadic'] ? '...' : '')
                . '$' . $param['name'];
        }, $parameters);
        return '[' . \implode(', ', $parameters) . ']';
    }

    /**
     * Builds a proxy method signature
     *
     * @param array $method Method definition.
     *
     * @return string Method signature as a string.
     */
    private function buildMethodSignature(array $method)
    {
        $method['modifiers'] = \array_diff($method['modifiers'], ['abstract']);
        $methodSignatureTemplate = '{{modifiers}} function {{name}}({{params}}){{returnType}}';
        return \trim(\strtr($methodSignatureTemplate, array(
            '{{modifiers}}' => \implode(' ', $method['modifiers']),
            '{{name}}' => $method['name'],
            '{{params}}' => $this->buildMethodSignatureParams($method['parameters']),
            '{{returnType}}' => $this->buildReturnType($method),
        )));
    }

    /**
     * Builds all parameters for a method.
     *
     * @param array $parameters A list of parameter definitions
     *
     * @return string Method parameters as a string. Empty string is returned when no parameters were passed.
     */
    private function buildMethodSignatureParams(array $parameters)
    {
        $parameters = \array_map(function ($param) {
            $param = \array_merge(ClassDefFactory::$defaultParam, $param);
            $param = $this->buildType($param['type'])
                . ' '
                . ($param['isPassedByReference'] ? '&' : '')
                . ($param['isVariadic'] ? '...' : '')
                . '$' . $param['name']
                . $this->buildParameterDefaultValue($param);
            return \ltrim($param);
        }, $parameters);
        return \implode(', ', $parameters);
    }

    /**
     * Builds default value for a parameter. Equal sign (surrounded with spaces) is included.
     *
     * @param array $parameter Parameter definition
     *
     * @return string Parameter's default value as a string (via var_export)
     */
    private function buildParameterDefaultValue(array $parameter)
    {
        if (!$parameter['isDefaultValueAvailable']) {
            return '';
        }

        $value = $parameter['isDefaultValueConstant']
            ? $parameter['defaultValue']
            : self::varExport($parameter['defaultValue']);

        return ' = ' . $value;
    }

    /**
     * Build class properties
     *
     * @return string
     */
    private function buildProperties()
    {
        $properties = \array_map(function ($property) {
            $property = \array_merge(ClassDefFactory::$defaultProperty, $property);
            return $this->buildIndent()
                . \implode(' ', $property['modifiers']) . ' '
                . ($property['type']
                    ? $this->buildType($property['type']) . ' '
                    : '')
                . '$' . $property['name']
                . ($property['hasValue']
                    ? ' = ' . self::varExport($property['value'])
                    : '')
                . ';' . "\n\n";
        }, $this->classDef['properties']);
        return \implode('', $properties);
    }

    /**
     * Builds return statement for a method.
     *
     * @param array $method Method definition
     *
     * @return string Return statement as a string.
     *                  Empty string is returned when no return type was specified or it was explicitly specified as `void`.
     */
    private function buildReturn(array $method)
    {
        return $method['name'] === '__construct' || $method['returnType'] === 'void'
            ? ''
            : 'return ';
    }

    /**
     * Builds return type for a method.
     *
     * @param array $method Method definition
     *
     * @return string Return type as a string. Empty string is returned when method has no return type.
     */
    private function buildReturnType(array $method)
    {
        return $method['returnType'] !== null
            ? ': ' . $this->buildType($method['returnType'])
            : '';
    }

    /**
     * Builds a type. Nullability is handled too.
     *
     * @param string|null $type Type definition
     *
     * @return string Type as a string.
     */
    private function buildType($type)
    {
        return (string) $type;
    }

    /**
     * Builds indent. 4 spaces are used, with no tabs.
     *
     * @param int $count How many times indent should be repeated.
     *
     * @return string Indent as a string.
     */
    private function buildIndent($count = 1)
    {
        return \str_repeat('    ', $count);
    }

    /**
     * var_export with additional formatting
     *
     * @param mixed $value Value to export
     *
     * @return string
     */
    private static function varExport($value)
    {
        if (\in_array($value, [null, true, false], true)) {
            return \strtolower(\var_export($value, true));
        }
        $value = \var_export($value, true);
        $value = \str_replace('array (', 'array(', $value);
        $value = \preg_replace('/array\(\s*\)/s', 'array()', $value);
        $value = \preg_replace('/=> \n\s+array/', '=> array', $value);
        $value = \preg_replace_callback('/^(\s*)/m', static function ($matches) {
            return \str_repeat($matches[1], 2);
        }, $value);
        $value = \str_replace('\'\' . "\0" . \'\'', '"\x00"', $value);
        return $value;
    }
}
