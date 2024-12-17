<?php

$htmlExpect = '
<li class="m_log"><div class="groupByInheritance t_object" data-accessible="public"><span class="t_identifier" data-type-more="className" title="PHP 8.4 Property Asymmetric Visibility"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>PropertyAsymVisibility</span></span>
<dl class="object-inner">
<dt class="properties">properties</dt>
    <dd class="property protected-set public"><span class="t_modifier_public">public</span> <span class="t_modifier_protected-set">protected(set)</span> <span class="t_type">string</span> <span class="no-quotes t_identifier t_string">name</span></dd>
    <dd class="isStatic property public"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_type">array</span> <span class="no-quotes t_identifier t_string">static</span> <span class="t_operator">=</span> <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span></dd>
    <dd class="' . (PHP_VERSION_ID >= 80400 ? 'isFinal ' : '') . 'private-set property protected">' . (PHP_VERSION_ID >= 80400 ? '<span class="t_modifier_final">final</span> ' : '') . '<span class="t_modifier_protected">protected</span> <span class="t_modifier_private-set">private(set)</span> <span class="t_type">int</span> <span class="no-quotes t_identifier t_string">age</span></dd>
<dt class="methods">no methods</dt>
</dl>
</div></li>
';

return $htmlExpect;
