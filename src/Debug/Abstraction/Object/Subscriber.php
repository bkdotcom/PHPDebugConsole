<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0.4
 */

namespace bdk\Debug\Abstraction\Object;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\Definition as AbstractObjectDefinition;
use bdk\Debug\Abstraction\Object\PropertiesDom;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Data;
use bdk\Debug\Utility\PhpDoc;
use bdk\PubSub\SubscriberInterface;
use Error;
use Exception;
use mysqli;
use ReflectionFunction;
use RuntimeException;
use UnitEnum;

/**
 * Internal subscriber to ABSTRACT_START and ABSTRACT_END events
 */
class Subscriber implements SubscriberInterface
{
    /** @var AbstractObject */
    protected $abstractObject;

    /** @var PropertiesDom */
    private $dom;

    /**
     * Constructor
     *
     * @param AbstractObject $abstractObject Object abstracter
     */
    public function __construct(AbstractObject $abstractObject)
    {
        $this->abstractObject = $abstractObject;
        $this->dom = new PropertiesDom($abstractObject->abstracter);
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_OBJ_ABSTRACT_END => 'onEnd',
            Debug::EVENT_OBJ_ABSTRACT_START => 'onStart',
        );
    }

    /**
     * Debug::EVENT_OBJ_ABSTRACT_START event subscriber
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    public function onStart(Abstraction $abs)
    {
        $obj = $abs->getSubject();
        switch (true) {
            case $obj instanceof \DateTime || $obj instanceof \DateTimeImmutable:
                // check for both DateTime and DateTimeImmutable
                //   DateTimeInterface (and DateTimeImmutable) not available until Php 5.5
                $abs['isTraverseOnly'] = false;
                $abs['stringified'] = $obj->format(\DateTime::ISO8601);
                break;
            case $obj instanceof mysqli:
                $this->onStartMysqli($abs);
                break;
            case $obj instanceof Data:
                $abs['propertyOverrideValues']['data'] = Abstracter::NOT_INSPECTED;
                break;
            case $obj instanceof PhpDoc:
                $abs['propertyOverrideValues']['cache'] = Abstracter::NOT_INSPECTED;
                break;
            case $obj instanceof UnitEnum:
                $abs['isTraverseOnly'] = false;
                break;
            case $obj instanceof AbstractObjectDefinition:
                $abs['propertyOverrideValues']['cache'] = Abstracter::NOT_INSPECTED;
                break;
            case $abs['className'] === 'Closure':
                $this->onStartClosure($abs);
                break;
        }
    }

    /**
     * Debug::EVENT_OBJ_ABSTRACT_END event subscriber
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    public function onEnd(Abstraction $abs)
    {
        $obj = $abs->getSubject();
        if ($obj instanceof Exception && isset($abs['properties']['xdebug_message'])) {
            $abs['properties']['xdebug_message']['debugInfoExcluded'] = true;
        } elseif ($obj instanceof mysqli && !$abs['collectPropertyValues']) {
            $this->onEndMysqli($abs);
        } elseif ($obj instanceof UnitEnum) {
            $this->onEndEnum($abs);
        }
        $this->dom->add($abs);
        if (isset($abs['methods']['__toString'])) {
            $abs['methods']['__toString']['returnValue'] = $this->abstractObject->methods->toString($abs);
        }
        $this->promoteParamDescs($abs);
    }

    /**
     * Add enum case's @var desc (if exists) to phpDoc
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    private function onEndEnum(Abstraction $abs)
    {
        if ($abs['debugMethod'] === 'table') {
            $abs['cfgFlags'] |= AbstractObject::BRIEF;
        }
        if (!($abs['cfgFlags'] & AbstractObject::PHPDOC_COLLECT)) {
            return;
        }
        $reflector = $abs['reflector'];
        $name = $abs->getSubject()->name;
        $caseReflector = $reflector->getCase($name);
        $phpDocCase = $this->abstractObject->helper->getPhpDocVar($caseReflector);
        if ($phpDocCase) {
            $phpDoc = $this->abstractObject->helper->getPhpDoc($reflector);
            $phpDoc = \array_merge($phpDoc, array(
                'desc' => \trim($phpDoc['summary'] . "\n" . $phpDoc['desc']),
                'summary' => $phpDocCase['summary'],
            ));
            \ksort($phpDoc);
            $abs['phpDoc'] = $phpDoc;
        }
    }

    /**
     * Add mysqli property values
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    private function onEndMysqli(Abstraction $abs)
    {
        $obj = $abs->getSubject();
        $propsAlwaysAvail = [
            'client_info','client_version','connect_errno','connect_error','errno','error','stat',
        ];
        \set_error_handler(static function () {
            // ignore error
        });
        $refObject = $abs['reflector'];
        foreach ($propsAlwaysAvail as $name) {
            if (!isset($abs['properties'][$name])) {
                // stat property may be missing in php 7.4??
                continue;
            }
            $abs['properties'][$name]['value'] = $refObject->getProperty($name)->getValue($obj);
        }
        \restore_error_handler();
    }

    /**
     * Set Closure definition and debug properties
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    private function onStartClosure(Abstraction $abs)
    {
        // get the per-instance __invoke signature
        $this->abstractObject->methods->add($abs);
        $obj = $abs->getSubject();
        $ref = new ReflectionFunction($obj);
        $abs['definition'] = array(
            'extensionName' => $ref->getExtensionName(),
            'fileName' => $ref->getFileName(),
            'startLine' => $ref->getStartLine(),
        );
        $properties = $abs['properties'];
        $properties['debug.file'] = $this->abstractObject->properties->buildValues(array(
            'type' => Type::TYPE_STRING,
            'value' => $abs['definition']['fileName'],
            'valueFrom' => 'debug',
            'visibility' => ['debug'],
        ));
        $properties['debug.line'] = $this->abstractObject->properties->buildValues(array(
            'type' => Type::TYPE_INT,
            'value' => (int) $abs['definition']['startLine'],
            'valueFrom' => 'debug',
            'visibility' => ['debug'],
        ));
        $abs['properties'] = $properties;
    }

    /**
     * Test if we can collect mysqli property values
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    private function onStartMysqli(Abstraction $abs)
    {
        /*
            test if stat() throws an error (ie "Property access is not allowed yet")
            if so, don't collect property values
        */
        $haveError = false;
        \set_error_handler(static function () use (&$haveError) {
            $haveError = true;
            return true;
        }, E_ALL);
        try {
            $mysqli = $abs->getSubject();
            $mysqli->stat();
        } catch (Error $e) {
            $haveError = true;
        } catch (RuntimeException $e) {
            $haveError = true;
        }
        if ($haveError) {
            $abs['collectPropertyValues'] = false;
        }
        \restore_error_handler();
    }

    /**
     * Reuse the phpDoc description from promoted __construct params
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    private function promoteParamDescs(Abstraction $abs)
    {
        if (isset($abs['methods']['__construct']) === false) {
            return;
        }
        foreach ($abs['methods']['__construct']['params'] as $info) {
            if ($info['isPromoted'] && $info['desc']) {
                $paramName = $info['name'];
                $abs['properties'][$paramName]['phpDoc']['summary'] = $info['desc'];
            }
        }
    }
}
