<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Abstraction;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Utility\PhpDoc;
use bdk\Debug\Utility\UseStatements;
use ReflectionAttribute;
use ReflectionNamedType;
use ReflectionType;
use Reflector;

/**
 * Base class for AbstractObjectMethod & AbstractObjectProperty
 */
abstract class AbstractObjectSub
{

    protected $abs;
    protected $abstracter;
    protected $phpDoc;

    /**
     * Constructor
     *
     * @param Abstracter $abstracter abstracter instance
     * @param PhpDoc     $phpDoc     phpDoc instance
     */
    public function __construct(Abstracter $abstracter, PhpDoc $phpDoc)
    {
        $this->abstracter = $abstracter;
        $this->phpDoc = $phpDoc;
    }

    /**
     * Get object, constant, property, or method attributes
     *
     * @param Reflector $reflector Reflection instance
     *
     * @return array
     */
    public function getAttributes(Reflector $reflector)
    {
        if (PHP_VERSION_ID < 80000) {
            return array();
        }
        return \array_map(function (ReflectionAttribute $attribute) {
            return array(
                'name' => $attribute->getName(),
                'arguments' => $attribute->getArguments(),
            );
        }, $reflector->getAttributes());
    }

    /**
     * Get string representation of ReflectionNamedType or ReflectionType
     *
     * @param ReflectionType|null $type ReflectionType
     *
     * @return string|null
     */
    protected function getTypeString(ReflectionType $type = null)
    {
        if ($type === null) {
            return null;
        }
        return $type instanceof ReflectionNamedType
            ? $type->getName()
            : (string) $type;
    }

    /**
     * Fully quallify type-hint
     *
     * This is only performed if `fullyQualifyPhpDocType` = true
     *
     * @param string|null $type Type-hint string from phpDoc (may be or'd with '|')
     *
     * @return string|null
     */
    protected function resolvePhpDocType($type)
    {
        if (!$type) {
            return $type;
        }
        if (!$this->abs['fullyQualifyPhpDocType']) {
            return $type;
        }
        $keywords = array(
            'array','bool','callable','float','int','iterable','null','object','self','string',
            '$this','false','mixed','resource','static','true','void',
        );
        $types = \preg_split('#\s*\|\s*#', $type);
        foreach ($types as $i => $type) {
            if (\strpos($type, '\\') === 0) {
                $types[$i] = \substr($type, 1);
                continue;
            }
            $isArray = false;
            if (\substr($type, -2) === '[]') {
                $isArray = true;
                $type = \substr($type, 0, -2);
            }
            if (\in_array($type, $keywords)) {
                continue;
            }
            $type = $this->resolvePhpDocTypeClass($type);
            if ($isArray) {
                $type .= '[]';
            }
            $types[$i] = $type;
        }
        return \implode('|', $types);
    }

    /**
     * Check type-hint in use statements, and whether relative or absolute
     *
     * @param string $type Type-hint string
     *
     * @return string
     */
    private function resolvePhpDocTypeClass($type)
    {
        $first = \substr($type, 0, \strpos($type, '\\') ?: 0) ?: $type;
        $useStatements = UseStatements::getUseStatements($this->abs['reflector'])['class'];
        if (isset($useStatements[$first])) {
            return $useStatements[$first] . \substr($type, \strlen($first));
        }
        $namespace = \substr($this->abs['className'], 0, \strrpos($this->abs['className'], '\\') ?: 0);
        if ($namespace) {
            /*
                Truly relative?  Or, does PhpDoc omit '\' ?
                Not 100% accurate, but check if absolute path exists
                Otherwise assume relative to namespace
            */
            return \class_exists($type)
                ? $type
                : $namespace . '\\' . $type;
        }
        return $type;
    }
}
