<?php

$htmlExpect = '
<li class="m_log"><div class="groupByInheritance t_object" data-accessible="public"><span class="t_identifier" data-type-more="className" title="PHP 8.4 property hooks"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>PropertyHooks</span></span>
<dl class="object-inner">
<dt class="properties">properties</dt>
<dd class="getHook property public setHook"><span class="t_modifier_public">public</span> <span class="t_type">string</span> <span class="no-quotes t_identifier t_string">backedGetAndSet</span></dd>
<dd class="getHook property public"><span class="t_modifier_public">public</span> <span class="t_type">string</span> <span class="no-quotes t_identifier t_string">backedGetOnly</span></dd>
<dd class="property public setHook"><span class="t_modifier_public">public</span> <span class="t_type">string</span> <span class="no-quotes t_identifier t_string">backedSetOnly</span></dd>
<dd class="isStatic property public"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_type">array</span> <span class="no-quotes t_identifier t_string">static</span> <span class="t_operator">=</span> <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span></dd>
<dd class="property public"><span class="t_modifier_public">public</span> <span class="no-quotes t_identifier t_string">things</span> <span class="t_operator">=</span> <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span></dd>
<dd class="getHook isVirtual property public setHook"><span class="t_modifier_public">public</span> <span class="t_type">string</span> <span class="no-quotes t_identifier t_string">virtualGetAndSet</span> <span class="t_operator">=</span> <span class="t_notInspected">NOT INSPECTED</span></dd>
<dd class="getHook isVirtual property public"><span class="t_modifier_public">public</span> <span class="t_type">string</span> <span class="no-quotes t_identifier t_string">virtualGetOnly</span> <span class="t_operator">=</span> <span class="t_notInspected">NOT INSPECTED</span></dd>
<dd class="isVirtual isWriteOnly property public setHook"><span class="t_modifier_public">public</span> <span class="t_type">string</span> <span class="no-quotes t_identifier t_string" title="Write only property">virtualSetOnly</span></dd>
<dt class="methods">no methods</dt>
</dl>
</div></li>
';

return $htmlExpect;
