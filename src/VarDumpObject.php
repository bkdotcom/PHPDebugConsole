<?php
/**
 * Methods used to display objects
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2016 Brad Kent
 * @version   v1.3.3
 */

namespace bdk\Debug;

/**
 * Use reflection to get dump object info
 */
class VarDumpObject
{

    /**
     * Constructor
     *
     * @param object $debug debug instance
     */
    public function __construct($debug)
    {
        $this->debug = $debug;
        $this->phpDoc = new PhpDoc();
    }

    /**
     * return information about object
     *
     * @param mixed  $abs      object abstraction array, or object
     * @param string $outputAs ['html']
     * @param array  $path     {@internal}
     *
     * @return string|array
     */
    public function dump($abs, $outputAs = 'html', $path = array())
    {
        if (!is_array($abs)) {
            $abs = $this->getAbstraction($abs);
        }
        if ($outputAs == 'html') {
            $dump = $this->dumpAsHtml($abs, $path);
        } elseif ($outputAs == 'script' || $outputAs == 'firephp') {
            $dump = $this->dumpAsArray($abs, $outputAs, $path);
        } elseif ($outputAs == 'text') {
            $dump = $this->dumpAsText($abs, $path);
        } else {
            $dump = $abs;
        }
        return $dump;
    }

    /**
     * returns an array structure
     *
     * @param array  $abs      object "abstraction"
     * @param string $outputAs how we're outputing
     * @param array  $path     {@internal}
     *
     * @return array
     */
    protected function dumpAsArray($abs, $outputAs, $path = array())
    {
        $dump = array(
            'class' => $abs['className'],
            'constants' => $abs['constants'],
            'properties' => $abs['properties'],
            'methods' => $abs['methods'],
        );
        $pathCount = count($path);
        if (empty($dump['constants']) || !$this->debug->varDump->get('outputConstants')) {
            unset($dump['constants']);
        }
        foreach ($dump['properties'] as $property => $propertyInfo) {
            foreach ($propertyInfo as $prop => $val) {
                if ($val === VarDump::UNDEFINED) {
                    unset($propertyInfo[$prop]);
                }
            }
            $path[$pathCount] = $property;
            $propertyInfo['value'] = $this->debug->varDump->dump($propertyInfo['value'], $outputAs, $path);
            $dump['properties'][$property] = $propertyInfo;
        }
        if ($abs['collectMethods'] && $this->debug->varDump->get('outputMethods')) {
            foreach ($dump['methods'] as $method => $methodInfo) {
                foreach ($methodInfo as $key => $val) {
                    if ($val === VarDump::UNDEFINED) {
                        unset($methodInfo[$key]);
                    }
                }
                foreach ($methodInfo['params'] as $param => $paramProps) {
                    foreach ($paramProps as $prop => $val) {
                        if ($val === VarDump::UNDEFINED) {
                            unset($paramProps[$prop]);
                        }
                    }
                    $methodInfo['params'][$param] = $paramProps;
                }
                $dump['methods'][$method] = $methodInfo;
            }
        } else {
            unset($dump['methods']);
        }
        if ($abs['isRecursion'] || $abs['excluded']) {
            $dump['info'] = $abs['isRecursion']
                ? 'recursion'
                : 'excluded';
            unset($dump['properties']);
            unset($dump['methods']);
        }
        return $dump;
    }

    /**
     * dump object as html
     *
     * @param array $abs  object abstraction
     * @param array $path path
     *
     * @return string
     */
    protected function dumpAsHtml($abs, $path)
    {
        $strClassName = '<b class="t_object-class">'.$abs['className'].' object</b>';
        if ($abs['isRecursion']) {
            $html = $strClassName
                .' <span class="t_recursion">*RECURSION*</span>';
        } elseif ($abs['excluded']) {
            $html = $strClassName
                .' <span class="excluded">(not inspected)</span>';
        } else {
            $objToString = null;
            if (isset($abs['methods']['__toString']['returnValue'])) {
                $toStringVal = $abs['methods']['__toString']['returnValue'];
                $toStringValAppend = '';
                if (strlen($toStringVal) > 100) {
                    $toStringLen = strlen($toStringVal);
                    $toStringVal = substr($toStringVal, 0, 100);
                    $toStringValAppend = '&hellip; <i>('.($toStringLen - 100).' more chars)</i>';
                }
                $toStringDump = $this->debug->varDump->dump($toStringVal);
                $classAndValue = $this->debug->utilities->parseAttribString($toStringDump);
                $objToString = '<span class="'.$classAndValue['class'].' t_toStringValue" title="__toString()">'
                    .$classAndValue['innerhtml']
                    .$toStringValAppend
                    .'</span> ';
            }
            $misc = '';
            foreach ($abs['misc'] as $k => $v) {
                $misc .= $k.': '.$v.'<br />';
            }
            $html = $objToString
                .$strClassName
                .'<dl class="object-inner">'
                    .'<dt>extends</dt><dd>'.implode('<br />', $abs['extends']).'</dd>'
                    .'<dt>implements</dt><dd class="interface">'.implode('</dd><dd class="interface">', $abs['implements']).'</dd>'
                    .'<dt>misc</dt><dd>'.$misc.'</dd>'
                    .$this->dumpConstantsAsHtml($abs['constants'])
                    .$this->dumpPropertiesAsHtml($abs['properties'], $path, array('viaDebugInfo'=>$abs['viaDebugInfo']))
                    .($abs['collectMethods'] && $this->debug->varDump->get('outputMethods')
                        ? $this->dumpMethodsAsHtml($abs['methods'])
                        : ''
                    )
                .'</dl>';
            // remove empty <dt>s
            $html = preg_replace('#<dt[^>]*>\w+</dt><dd[^>]*></dd>#', '', $html);
        }
        $accessible = $abs['scopeClass'] == $abs['className']
            ? 'private'
            : 'public';
        $html = '<span class="t_object" data-accessible="'.$accessible.'">'.$html.'</span>';
        return $html;
    }

    /**
     * returns text representation of object
     *
     * @param array $abs  object "abstraction"
     * @param array $path (@internal)
     *
     * @return string
     */
    protected function dumpAsText($abs, $path = array())
    {
        $dump = $this->dumpAsArray($abs, 'text', $path);
        if (!empty($dump['info']) && $dump['info'] == 'recursion') {
            $str = '(object) '.$dump['class'].' *RECURSION*';
        } elseif (!empty($dump['info']) && $dump['info'] == 'excluded') {
            $str = '(object) '.$dump['class'].' (not inspected)';
        } else {
            $accessible = $abs['scopeClass'] == $abs['className']
                ? 'private'
                : 'public';
            $str = '(object) '.$dump['class']."\n";
            $propHeader = '';
            $properties = '';
            foreach ($dump['properties'] as $property => $info) {
                if ($accessible == 'public') {
                    if ($info['visibility'] != 'public') {
                        continue;
                    }
                    $properties .= '    '.$property.' = '.$info['value']."\n";
                } else {
                    $properties .= '    '.$info['visibility'].' '.$property.' = '.$info['value']."\n";
                }
            }
            if ($accessible == 'public') {
                $propHeader = $properties
                    ? 'Properties (only listing public)'
                    : 'No public properties';
            } else {
                $propHeader = $properties
                    ? 'Properties'
                    : 'No Properties';
            }
            $str .= '  '.$propHeader.':'."\n".$properties;
            $methodCount = 0;
            if ($abs['collectMethods'] && $this->debug->varDump->get('outputMethods')) {
                if (!empty($dump['methods'])) {
                    foreach ($dump['methods'] as $info) {
                        if ($accessible == 'public' && $info['visibility'] !== 'public') {
                            continue;
                        }
                        $methodCount++;
                    }
                    if ($accessible == 'public') {
                        $str .= '  '.$methodCount.' Public Methods (not listed)'."\n";
                    } else {
                        $str .= '  '.$methodCount.' Methods (not listed)'."\n";
                    }
                } else {
                    $str .= '  No Methods'."\n";
                }
            }
        }
        if (count($path) > 1) {
            $str = str_replace("\n", "\n    ", $str);
        }
        $str = trim($str);
        return $str;
    }

    /**
     * get formatted constant info
     *
     * @param array $constants array of name=>value
     *
     * @return string html
     */
    protected function dumpConstantsAsHtml($constants)
    {
        $str = '';
        if ($constants && $this->debug->varDump->get('outputConstants')) {
            $str = '<dt class="constants">constants</dt>';
            foreach ($constants as $k => $value) {
                $str .= '<dd class="constant">'
                    .'<span class="constant-name">'.$k.'</span>'
                    .' <span class="t_operator">=</span> '
                    .$this->debug->varDump->dump($value, 'html')
                    .'</dd>';
            }
        }
        return $str;
    }

    /**
     * get formatted method info
     *
     * @param array $methods methods as returned from getMethods
     *
     * @return string html
     */
    protected function dumpMethodsAsHtml($methods)
    {
        $label = count($methods)
            ? 'methods'
            : 'no methods';
        $str = '<dt class="methods">'.$label.'</dt>';
        foreach ($methods as $methodName => $info) {
            $paramStr = $this->dumpParamsAsHtml($info['params']);
            $modifiers = array_keys(array_filter(array(
                'final' => $info['isFinal'],
                $info['visibility'] => true,
                'static' => $info['isStatic'],
            )));
            foreach ($modifiers as $i => $modifier) {
                $modifiers[$i] = '<span class="t_modifier">'.$modifier.'</span>';
            }
            $str .= '<dd class="method visibility-'.$info['visibility'].'" data-implements="'.$info['implements'].'">'
                .implode(' ', $modifiers)
                .' <span class="t_type"'
                        .' title="'.htmlspecialchars($info['returnDesc']).'"'
                    .'>'.$info['returnType'].'</span>'
                .' <span class="method-name"'
                        .' title="'.htmlspecialchars($info['desc']).'"'
                    .'>'.$methodName.'</span>'
                .'<span class="t_punct">(</span>'.$paramStr.'<span class="t_punct">)</span>'
                .($methodName == '__toString'
                    ? '<br /><span class="indent">'.$this->debug->varDump->dump($info['returnValue']).'</span>'
                    : ''
                )
                .'</dd>'."\n";
        }
        $str = str_replace(' title="'.VarDump::UNDEFINED.'"', '', $str);  // t_type && method-name
        $str = str_replace(' data-implements=""', '', $str);
        $str = preg_replace('#<span[^>]*>('.VarDump::UNDEFINED.')?</span>#', '', $str); // returnType
        return $str;
    }

    /**
     * Dump method parameters as HTML
     *
     * @param array $params params as returned from getPaarams()
     *
     * @return string html
     */
    protected function dumpParamsAsHtml($params)
    {
        $paramStr = '';
        foreach ($params as $info) {
            $paramStr .= '<span class="parameter">';
            if (!empty($info['type'])) {
                $paramStr .= '<span class="t_type">'.$info['type'].'</span> ';
            }
            $paramStr .= '<span class="t_parameter-name"'
                .($info['desc'] != VarDump::UNDEFINED
                    ? ' title="'.htmlspecialchars(str_replace("\n", ' ', $info['desc'])).'"'
                    : ''
                ).'>'.htmlspecialchars($info['name']).'</span>';
            if ($info['defaultValue'] != VarDump::UNDEFINED) {
                $defaultValue = $info['defaultValue'];
                if (is_string($defaultValue)) {
                    $defaultValue = str_replace("\n", ' ', $defaultValue);
                }
                $paramStr .= ' <span class="t_operator">=</span> ';
                $paramStr .= '<span class="t_parameter-default">'.$this->debug->varDump->dump($defaultValue).'</span>';
            }
            $paramStr .= '</span>, '; // end .parameter
        }
        $paramStr = trim($paramStr, ', ');
        return $paramStr;
    }

    /**
     * Dump object properties as HTML
     *
     * @param array $properties properties as returned from getProperties()
     * @param array $path       array/object path taken
     * @param array $meta       meta information (viaDebugInfo)
     *
     * @return string
     */
    protected function dumpPropertiesAsHtml($properties, $path = array(), $meta = array())
    {
        $label = count($properties)
            ? 'properties'
            : 'no properties';
        if ($meta['viaDebugInfo']) {
            $label .= ' <span class="text-muted">(via __debugInfo)</span>';
        }
        $str = '<dt class="properties">'.$label.'</dt>';
        $pathCount = count($path);
        foreach ($properties as $k => $info) {
            $path[$pathCount] = $k;
            $viaDebugInfo = !empty($info['viaDebugInfo']);
            $str .= '<dd class="property visibility-'.$info['visibility'].' '.($viaDebugInfo ? 'debug-value' : '').'">'
                .'<span class="t_modifier">'.$info['visibility'].'</span>'
                .($info['type'] !== VarDump::UNDEFINED
                    ? ' <span class="t_type">'.$info['type'].'</span>'
                    : ''
                )
                .' <span class="property-name"'
                    .($info['desc'] !== VarDump::UNDEFINED
                        ? ' title="'.htmlspecialchars($info['desc']).'"'
                        : ''
                    )
                    .'>'.$k.'</span>'
                .' <span class="t_operator">=</span> '
                .$this->debug->varDump->dump($info['value'], 'html', $path)
                .'</dd>'."\n";
        }
        return $str;
    }

    /**
     * returns information about an object
     *
     * @param object $obj  object to inspect
     * @param array  $hist (@internal) array & object history
     *
     * @return array
     */
    public function getAbstraction($obj, &$hist = array())
    {
        $reflectionClass = new \reflectionClass($obj);
        $return = array(
            'debug' => VarDump::ABSTRACTION,
            'type' => 'object',
            'excluded' => $hist && in_array(get_class($obj), $this->debug->varDump->get('objectsExclude')),
            'collectMethods' => $this->debug->varDump->get('collectMethods'),
            'viaDebugInfo' => $this->debug->varDump->get('useDebugInfo') && method_exists($obj, '__debugInfo'),
            'isRecursion' => in_array($obj, $hist, true),
            'className' => get_class($obj),
            'extends' => array(),
            'implements' => $reflectionClass->getInterfaceNames(),
            'constants' => array(),
            'properties' => array(),
            'methods' => array(),
            'scopeClass' => $this->getScopeClass($hist),
            'misc' => array(),
        );
        if (!$return['isRecursion'] && !$return['excluded']) {
            $collectConstants = $this->debug->varDump->get('collectConstants');
            $return['properties'] = $this->getProperties($obj, $hist);
            if ($collectConstants) {
                $return['constants'] = $reflectionClass->getConstants();
            }
            while ($reflectionClass = $reflectionClass->getParentClass()) {
                $return['extends'][] = $reflectionClass->getName();
                if ($collectConstants) {
                    $return['constants'] = array_merge($reflectionClass->getConstants(), $return['constants']);
                }
            }
            if ($this->debug->varDump->get('objectSort') == 'name') {
                ksort($return['constants']);
            }
            if ($return['collectMethods']) {
                $return['methods'] = $this->getMethods($obj);
            } elseif (method_exists($obj, '__toString')) {
                $return['methods'] = array(
                    '__toString' => array(
                        'returnValue' => call_user_func(array($obj, '__toString')),
                    ),
                );
            }
        }
        return $return;
    }

    /**
     * Returns array of objects methods
     *
     * @param string $obj object
     *
     * @return array
     */
    public function getMethods($obj)
    {
        $methodArray = array();
        $reflectionObject = new \ReflectionObject($obj);
        $methods = $reflectionObject->getMethods();
        $reflectionClass = new \reflectionClass($obj);
        $interfaces = $reflectionClass->getInterfaceNames();
        $interfaceMethods = array(
            'ArrayAccess' => array('offsetExists','offsetGet','offsetSet','offsetUnset'),
            'Countable' => array('count'),
            'Iterator' => array('current','key','next','rewind','void'),
            'IteratorAggregate' => array('getIterator'),
            // 'Throwable' => array('getMessage','getCode','getFile','getLine','getTrace','getTraceAsString','getPrevious','__toString'),
        );
        $interfacesHide = array_intersect($interfaces, array_keys($interfaceMethods));
        foreach ($methods as $reflectionMethod) {
            $vis = 'public';
            if ($reflectionMethod->isPrivate()) {
                $vis = 'private';
            } elseif ($reflectionMethod->isProtected()) {
                $vis = 'protected';
            }
            $docComment = $reflectionMethod->getDocComment();
            $phpDocParts = $this->phpDoc->parse($docComment);
            $methodName = $reflectionMethod->getName();
            $parsedReturnTag = isset($phpDocParts['return'][0])
                ? $this->phpDoc->parseTag('return', $phpDocParts['return'][0])
                : $this->phpDoc->parseTag('return', '');
            $info = array(
                'visibility' => $vis,
                'isFinal' => $reflectionMethod->isFinal(),
                'isStatic' => $reflectionMethod->isStatic(),
                'isAbstract' => $reflectionMethod->isAbstract(),
                'params' => $this->getParams($reflectionMethod, $phpDocParts),
                'desc' => $phpDocParts['comment'][0],
                'returnType' => $parsedReturnTag['type'],
                'returnDesc' => $parsedReturnTag['desc'],
                'implements' => null,
            );
            foreach (array('desc','returnType','returnDesc') as $key) {
                if ($info[$key] === null) {
                    $info[$key] = VarDump::UNDEFINED;
                }
            }
            foreach ($interfacesHide as $interface) {
                if (in_array($methodName, $interfaceMethods[$interface])) {
                    $info['implements'] = $interface;
                    break;
                }
            }
            if ($methodName == '__toString') {
                $info['returnValue'] = $reflectionMethod->invoke($obj);
            }
            $methodArray[$methodName] = $info;
        }
        $this->sort($methodArray);
        return $methodArray;
    }

    /**
     * get parameter details
     *
     * @param \ReflectionMethod $reflectionMethod method object
     * @param array             $phpDocParts      parsedDocComment
     *
     * @return array
     */
    public function getParams(\ReflectionMethod $reflectionMethod, $phpDocParts = array())
    {
        $paramArray = array();
        $params = $reflectionMethod->getParameters();
        if (empty($phpDocParts)) {
            $docComment = $reflectionMethod->getDocComment();
            $phpDocParts = $this->phpDoc->parse($docComment);
        }
        if (empty($phpDocParts['param'])) {
            $phpDocParts['param'] = array();
        }
        foreach ($params as $reflectionParameter) {
            $hasDefaultValue = $reflectionParameter->isDefaultValueAvailable();
            $name = '$'.$reflectionParameter->getName();
            if (method_exists($reflectionParameter, 'isVariadic') && $reflectionParameter->isVariadic()) {
                $name = '...'.$name;
            }
            if ($reflectionParameter->isPassedByReference()) {
                $name = '&'.$name;
            }
            $paramArray[$reflectionParameter->getName()] = array(
                'name' => $name,
                'optional' => $reflectionParameter->isOptional(),
                'defaultValue' =>  $hasDefaultValue
                    ? $reflectionParameter->getDefaultValue()
                    : VarDump::UNDEFINED,
                'type' => $this->getParamTypeHint($reflectionParameter),
                'desc' => VarDump::UNDEFINED,
            );
        }
        $paramKeys = array_keys($paramArray);
        foreach ($phpDocParts['param'] as $i => $paramValue) {
            $parsed = $this->phpDoc->parseTag('param', $paramValue);
            if ($parsed['name'] === null && isset($paramKeys[$i])) {
                $parsed['name'] = $paramKeys[$i];
            }
            if (isset($paramArray[ $parsed['name'] ])) {
                $param = &$paramArray[ $parsed['name'] ];
                if (!isset($param['type'])) {
                    $param['type'] = $parsed['type'];
                }
                $param['desc'] = $parsed['desc'];
            }
        }
        return $paramArray;
    }

    /**
     * Get true typehint (not phpDoc typehint)
     *
     * @param \ReflectionParameter $reflectionParameter reflectionParameter
     *
     * @return string|null
     */
    public function getParamTypeHint(\ReflectionParameter $reflectionParameter)
    {
        $return = null;
        if ($reflectionParameter->isArray()) {
            $return = 'array';
        } elseif (preg_match('/\[\s\<\w+?>\s([\w\\\\]+)/s', $reflectionParameter->__toString(), $matches)) {
            $return = $matches[1];
        }
        return $return;
    }

    /**
     * Returns array of objects properties
     *
     * @param object $obj  object
     * @param array  $hist (@internal) object history
     *
     * @return array
     */
    public function getProperties($obj, &$hist = array())
    {
        $hist[] = $obj;
        $propArray = array();
        $reflectionObject = new \ReflectionObject($obj);
        $useDebugInfo = $reflectionObject->hasMethod('__debugInfo') && $this->debug->varDump->get('useDebugInfo');
        $debugInfo = $useDebugInfo
            ? call_user_func(array($obj, '__debugInfo'))
            : array();
        if ($obj instanceof \DOMNodeList) {
            // for reasons unknown, DOMNodeList's properties are invisible to reflection
            $propArray['length'] = array(
                'visibility' => 'public',
                'isStatic' => false,
                'type' => 'integer',
                'value' => $obj->length,
                'desc' => VarDump::UNDEFINED,
            );
        }
        while ($reflectionObject) {
            $objClassName = $reflectionObject->getName();
            $properties = $reflectionObject->getProperties();
            $objNamespace = substr($objClassName, 0, strrpos($objClassName, '\\'));
            $isDebugObj = $objNamespace == __NAMESPACE__;
            while ($properties) {
                $prop = array_shift($properties);
                $name = $prop->getName();
                if (isset($propArray[$name])) {
                    continue;
                } elseif ($useDebugInfo && !array_key_exists($name, $debugInfo)) {
                    continue;
                } elseif ($isDebugObj && in_array($name, array('data','debug','instance'))) {
                    continue;
                }
                $propInfo = $this->getPropInfo($obj, $prop);
                if ($useDebugInfo) {
                    $debugValue = $debugInfo[$name];
                    $propInfo['viaDebugInfo'] = $debugValue != $propInfo['value'];
                    $propInfo['value'] = $debugValue;
                    unset($debugInfo[$name]);
                }
                if ($this->debug->varDump->needsAbstraction($propInfo['value'])) {
                    $propInfo['value'] = $this->debug->varDump->getAbstraction($propInfo['value'], $hist);
                }
                $propArray[$name] = $propInfo;
            }
            $reflectionObject = $reflectionObject->getParentClass();
        }
        /*
            Any values left in $debugInfo are only defined via __debugInfo
        */
        foreach ($debugInfo as $name => $value) {
            if ($this->debug->varDump->needsAbstraction($value)) {
                $value = $this->debug->varDump->getAbstraction($value, $hist);
            }
            $propArray[$name] = array(
                'visibility' => 'debug',
                'isStatic' => false,
                'type' => VarDump::UNDEFINED,
                'value' => $value,
                'desc' => VarDump::UNDEFINED,
                'viaDebugInfo' => true,
            );
        }
        $this->sort($propArray);
        return $propArray;
    }

    /**
     * Get property info
     *
     * @param object              $obj  object
     * @param \ReflectionProperty $prop reflection property
     *
     * @return array
     */
    protected function getPropInfo($obj, \ReflectionProperty $prop)
    {
        $prop->setAccessible(true); // only accessible via reflection
        $name = $prop->name;
        $objClassName = $prop->class;
        // get type and comment from phpdoc
        $commentInfo = $this->getPropCommentInfo($prop);
        $propInfo = array(
            'visibility' => 'public',
            'isStatic' => $prop->isStatic(),
            'type' => $commentInfo['type'],
            'value' => null,
            'desc' => $commentInfo['desc'],
            // viaDebugInfo
        );
        if ($prop->isPrivate()) {
            $propInfo['visibility'] = 'private';
        } elseif ($prop->isProtected()) {
            $propInfo['visibility'] = 'protected';
        }
        if ($objClassName == 'mysqli' && $obj->connect_errno) {
            // avoid "Property access is not allowed yet"
            $propsAlwaysAvail = array('client_info','client_version','connect_errno','connect_error','errno','error','stat');
            $value = in_array($name, $propsAlwaysAvail)
                ? $prop->getValue($obj)
                : null;
        } else {
            $value = $prop->getValue($obj);
        }
        $propInfo['value'] = $value;
        return $propInfo;
    }

    /**
     * Get property type and description from phpDoc comment
     *
     * @param \ReflectionProperty $prop property reflection object
     *
     * @return array
     */
    protected function getPropCommentInfo(\ReflectionProperty $prop)
    {
        $objClassName = $prop->class;
        $name = $prop->name;
        $info = array(
            'type' => VarDump::UNDEFINED,
            'desc' => VarDump::UNDEFINED,
        );
        $docComment = property_exists($objClassName, $name)
            ? $prop->getDocComment()
            : '';
        if ($docComment) {
            $phpDocParts = $this->phpDoc->parse($docComment);
            if ($phpDocParts['comment'][0]) {
                $info['desc'] = $phpDocParts['comment'][0];
            }
            if (isset($phpDocParts['var'])) {
                $parsed = $this->phpDoc->parseTag('var', $phpDocParts['var'][0]);
                $info['type'] = $parsed['type'];
                $info['desc'] = $info['desc'] && $parsed['desc'] !== VarDump::UNDEFINED
                    ? $info['desc'].': '.$parsed['desc']
                    : $parsed['desc'];
            }
        }
        return $info;
    }

    /**
     * for nested objects (ie object is a property of an object), returns the parent class
     *
     * @param array $hist (@internal) array & object history
     *
     * @return null|string
     */
    protected function getScopeClass(&$hist)
    {
        $className = null;
        for ($i = count($hist) - 1; $i >= 0; $i--) {
            if (is_object($hist[$i])) {
                $className = get_class($hist[$i]);
                break;
            }
        }
        if ($i < 0) {
            $backtrace = version_compare(PHP_VERSION, '5.4.0', '>=')
                ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
                : debug_backtrace(false);   // don't provide object
            foreach ($backtrace as $i => $frame) {
                if (!isset($frame['class']) || strpos($frame['class'], __NAMESPACE__) !== 0) {
                    break;
                }
            }
            $className = isset($backtrace[$i]['class'])
                ? $backtrace[$i]['class']
                : null;
        }
        return $className;
    }

    /**
     * Sorts property/method array by visibility or name
     *
     * @param array $array array to sort
     *
     * @return void
     */
    protected function sort(&$array)
    {
        if ($array) {
            $sort = $this->debug->varDump->get('objectSort');
            if ($sort == 'name') {
                ksort($array);
            } elseif ($sort == 'visibility') {
                $sortVisOrder = array('public', 'protected', 'private', 'debug');
                $sortData = array();
                foreach ($array as $name => $info) {
                    $sortData['name'][$name] = $name;
                    $sortData['vis'][$name] = array_search($info['visibility'], $sortVisOrder);
                }
                array_multisort($sortData['vis'], $sortData['name'], $array);
            }
        }
    }
}
