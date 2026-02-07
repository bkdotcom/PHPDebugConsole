<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0.4
 */

namespace bdk\Debug\Abstraction\Object;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Abstraction\Object\PropertiesDom;
use bdk\Debug\Abstraction\Type;
use bdk\PubSub\SubscriberInterface;
use Exception;
use mysqli;
use ReflectionFunction;
use UnitEnum;

/**
 * Internal subscriber to OBJ_ABSTRACT_START and OBJ_ABSTRACT_END events
 */
class Subscriber implements SubscriberInterface
{
    /** @var AbstractObject */
    protected $abstractObject;

    /** @var PropertiesDom */
    private $dom;

    private $isAbstractingTable = false;

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
     * @param ObjectAbstraction $abs Abstraction instance
     *
     * @return void
     */
    public function onStart(ObjectAbstraction $abs)
    {
        $obj = $abs->getSubject();
        // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        $handlers = array(
            'bdk\Table\Table' => [$this, 'onStartTable'],
            'bdk\Table\Element' => [$this, 'onStartElement'],
            'Closure' => [$this, 'onStartClosure'],
            'DateTime' => [$this, 'onStartDateTime'],
            'DateTimeImmutable' => [$this, 'onStartDateTime'],
            'mysqli' => [$this, 'onStartMysqli'],
            'bdk\Debug\Abstraction\Object\Definition' => [$this, 'onStartAbstractObjectDefinition'],
            'bdk\Debug\Data' => [$this, 'onStartData'],
            'bdk\Debug\Utility\PhpDoc' => [$this, 'onStartPhpDoc'],
        );
        foreach ($handlers as $class => $handler) {
            if (\is_a($obj, $class)) {
                \call_user_func($handler, $abs);
                break;
            }
        }
    }

    /**
     * Debug::EVENT_OBJ_ABSTRACT_END event subscriber
     *
     * @param ObjectAbstraction $abs Object abstraction instance
     *
     * @return void
     */
    public function onEnd(ObjectAbstraction $abs)
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
     * If cell value is an object, set unstructuredValue for abstraction
     *
     * @param ObjectAbstraction $abs Object abstraction instance
     *
     * @return void
     */
    public function tableCellValueAbstracter(ObjectAbstraction $abs)
    {
        if (isset($abs['stringified'])) {
            $abs['unstructuredValue'] = $abs['stringified'];
        } elseif (isset($abs['methods']['__toString']['returnValue'])) {
            $abs['unstructuredValue'] = $abs['methods']['__toString']['returnValue'];
        }
    }

    /**
     * Add enum case's @var desc (if exists) to phpDoc
     *
     * @param ObjectAbstraction $abs Object abstraction instance
     *
     * @return void
     */
    private function onEndEnum(ObjectAbstraction $abs)
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
     * @param ObjectAbstraction $abs Object abstraction instance
     *
     * @return void
     */
    private function onEndMysqli(ObjectAbstraction $abs)
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
     * Don't inspect Definition cache
     *
     * @param ObjectAbstraction $abs Object abstraction instance
     *
     * @return void
     */
    private function onStartAbstractObjectDefinition(ObjectAbstraction $abs)
    {
        $abs['propertyOverrideValues']['cache'] = Abstracter::NOT_INSPECTED;
    }

    /**
     * Set Closure definition and debug properties
     *
     * @param ObjectAbstraction $abs Object abstraction instance
     *
     * @return void
     */
    private function onStartClosure(ObjectAbstraction $abs)
    {
        // get the per-instance __invoke signature
        $this->abstractObject->methods->add($abs);
        $ref = new ReflectionFunction($abs->getSubject());
        $abs['definition'] = array(
            'extensionName' => $ref->getExtensionName(),
            'fileName' => $ref->getFileName(),
            'startLine' => $ref->getStartLine(),
        );
        $this->abstractObject->properties->addDebugProperties($abs);
    }

    /**
     * Data - don't inspect data prop
     *
     * @param ObjectAbstraction $abs Object abstraction instance
     *
     * @return void
     */
    private function onStartData(ObjectAbstraction $abs)
    {
        $abs['propertyOverrideValues']['data'] = Abstracter::NOT_INSPECTED;
    }

    /**
     * DateTime - store stringified value
     *
     * @param ObjectAbstraction $abs Object abstraction instance
     *
     * @return void
     */
    private function onStartDateTime(ObjectAbstraction $abs)
    {
        $obj = $abs->getSubject();
        $abs['stringified'] = $obj->format(\DateTime::RFC3339);
    }

    /**
     * Handle element abstraction
     *
     * @param ObjectAbstraction $abs Object abstraction instance
     *
     * @return void
     */
    private function onStartElement(ObjectAbstraction $abs)
    {
        $obj = $abs->getSubject();
        $values = $obj->jsonSerialize();
        $abs['unstructuredValue'] = $this->abstractObject->abstracter->crate($values, $abs['debugMethod'], $abs['hist']);
        $abs['isExcluded'] = true;
    }

    /**
     * Test if we can collect mysqli property values
     *
     * @param ObjectAbstraction $abs Object abstraction instance
     *
     * @return void
     */
    private function onStartMysqli(ObjectAbstraction $abs)
    {
        /*
            stat() may throw an error (ie "mysqli object is not fully initialized")
            if so, don't collect property values
        */
        $this->abstractObject->debug->utility->callSuppressed(static function () use ($abs) {
            $mysqli = $abs->getSubject();
            $mysqli->stat();
        }, [], $exception);
        if ($exception) {
            $abs['collectPropertyValues'] = false;
        }
    }

    /**
     * PhpDoc - don't inspect cache
     *
     * @param ObjectAbstraction $abs Object abstraction instance
     *
     * @return void
     */
    private function onStartPhpDoc(ObjectAbstraction $abs)
    {
        $abs['propertyOverrideValues']['cache'] = Abstracter::NOT_INSPECTED;
    }

    /**
     * Handle table abstraction
     *
     * @param ObjectAbstraction $abs Object abstraction instance
     *
     * @return void
     */
    private function onStartTable(ObjectAbstraction $abs)
    {
        $obj = $abs->getSubject();
        $debug = $this->abstractObject->debug;
        $this->isAbstractingTable = true;
        $debug->eventManager->subscribe(Debug::EVENT_OBJ_ABSTRACT_END, [$this, 'tableCellValueAbstracter']);
        $values = $obj->jsonSerialize();
        $values = $debug->abstracter->crate($values, $abs['debugMethod'], $abs['hist']);
        $values['type'] = Type::TYPE_TABLE;
        $debug->eventManager->unsubscribe(Debug::EVENT_OBJ_ABSTRACT_END, [$this, 'tableCellValueAbstracter']);
        $this->isAbstractingTable = false;
        $abs['unstructuredValue'] = new Abstraction(Type::TYPE_TABLE, $values);
        $abs['isExcluded'] = true;
    }

    /**
     * Reuse the phpDoc description from promoted __construct params
     *
     * @param ObjectAbstraction $abs Object abstraction instance
     *
     * @return void
     */
    private function promoteParamDescs(ObjectAbstraction $abs)
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
