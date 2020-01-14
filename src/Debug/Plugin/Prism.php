<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Plugin;

use bdk\Debug\AssetProviderInterface;

/**
 * Register prismjs' javascript & css
 */
class Prism implements AssetProviderInterface
{

    /**
     * {@inheritdoc}
     */
    public function getAssets()
    {
        return array(
            'css' => array(
                './js/prism.css',
                '.debug pre[class*="language-"] {
                    padding: 0;
                    margin: 0;
                }
                .debug pre[data-line] {
                    padding: .75em 0 .75em 3em;
                }
                .debug pre[data-line] .line-highlight {
                    margin-top: .75em;
                }
                .debug code[class*="language-"],
                .debug code[class*="language-"] span,
                .debug .line-numbers-rows > span {
                    padding: 0;
                    font-size: 13px !important;
                    line-height: 15px !important;
                }
                .debug pre[class*="language-"].line-numbers {
                    padding-left: 3.8em;
                }'
            ),
            'script' => array(
                './js/prism.js',
                'Prism.manual = true;
                (function(){
                    $("body").on("enhanced.debug", function(e){
                        var $target = $(e.target);
                        if ($target.hasClass("m_group")) {
                            return;
                        }
                        $target.find(".prism").removeClass("prism").each(function() {
                            var $prism = $(this),
                                $pre,
                                classes = $prism.attr("class").split(" "),
                                classesPre = [],
                                lang,
                                length,
                                i;
                            for (i = 0, length = classes.length; i < length; i++) {
                                if (classes[i].match(/^language-/)) {
                                    lang = classes[i];
                                    $prism.removeClass(lang);
                                } else if (["line-numbers"].indexOf(classes[i]) >= 0) {
                                    $prism.removeClass(classes[i]);
                                    classesPre.push(classes[i]);
                                }
                            }
                            $prism.wrapInner(\'<pre><code class="\'+lang+\'"></code></pre>\');
                            $pre = $prism.find("pre").addClass(classesPre.join(" "));
                            $.each($prism[0].attributes, function() {
                                if (!this.name.length) {
                                    return; // continue;
                                }
                                if (["class","colspan"].indexOf(this.name) < 0) {
                                    $pre.attr(this.name, this.value);
                                    if (this.name.indexOf("data") < 0) {
                                        // dont remove data attr... seems to remove all data attrs & only 1st data attr will get moved
                                        $prism.removeAttr(this.name);
                                    }
                                }
                            });
                            if ($pre.is(":visible")) {
                                Prism.highlightElement($pre.find("> code")[0]);
                            }
                        });
                    });
                    $("body").on("expanded.debug.next", ".context", function(e){
                        var $target = $(e.target),
                            $code = $target.find("code");
                        if ($code.children().length == 0) {
                            Prism.highlightElement($code[0]);
                        }
                    });
                }());',
            ),
        );
    }
}
