<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
 */

namespace bdk\Debug\Plugin;

use bdk\Debug\AssetProviderInterface;

/**
 * Register PrismJs' javascript & css
 */
class Highlight implements AssetProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function getAssets() // phpcs:ignore SlevomatCodingStandard.Functions.FunctionLength
    {
        return array(
            'css' => [
                './css/PrismJsLightDark.css',
                '.debug pre[class*="language-"] {
                    padding: 0;
                    margin: 0.25em 0 0.25em 0;
                }
                .debug td > pre:first-child {
                    margin-top: 0;
                }
                .debug pre[data-line] {
                    padding-left: 3em;
                }
                .debug pre[data-line] .line-highlight {
                    margin-top: 0;
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
                }',
            ],
            'script' => [
                './js/prism.js',
                'Prism.manual = true;
                (function(){
                    $("body").on("enhanced.debug expanded.debug.group shown.debug.tab", function (e) {
                        var $target = $(e.target)
                        var selector = ".highlight:visible"
                        if (e.type === "enhanced" && $target.hasClass("m_group")) {
                            return
                        }
                        // .add($target.filter(selector))
                        $target.find(selector).removeClass("highlight").each(function () {
                            var $high = $(this)
                            var $pre
                            var classes = $high.attr("class").split(" ")
                            var classesPre = []
                            var lang
                            var length
                            var i
                            var endsWithNl
                            var text
                            if ($high.is("pre")) {
                                $pre = $high
                            } else {
                                for (i = 0, length = classes.length; i < length; i++) {
                                    if (classes[i].match(/^language-/)) {
                                        lang = classes[i];
                                        $high.removeClass(lang);
                                    } else if (["line-numbers"].indexOf(classes[i]) >= 0) {
                                        $high.removeClass(classes[i]);
                                        classesPre.push(classes[i]);
                                    }
                                }
                                $high.wrapInner(\'<pre><code class="\'+lang+\'"></code></pre>\')
                                $pre = $high.find("pre").addClass(classesPre.join(" "))
                                $.each($high[0].attributes, function() {
                                    if (!this.name.length) {
                                        return // continue
                                    }
                                    if (["class","colspan"].indexOf(this.name) < 0) {
                                        $pre.attr(this.name, this.value)
                                        if (this.name.indexOf("data") < 0) {
                                            // don\'t remove data attr... seems to remove all data attrs & only 1st data attr will get moved
                                            $high.removeAttr(this.name)
                                        }
                                    }
                                })
                            }
                            text = $pre.find("> code").text()
                            endsWithNl = text.substring(text.length - 1, text.length) === "\n";
                            if (endsWithNl) {
                                // throw a zero-width-space on the end so we get the blank line
                                $pre.find("> code").append("&#8203;")
                            }
                            setTimeout(function () {
                                Prism.highlightElement($pre.find("> code")[0])
                            }, 100)
                        })
                    })
                    $("body").on("expanded.debug.next", ".context", function (e) {
                        var $code = $(e.target).find("code")
                        if ($code.length && $code.children().length === 0) {
                            setTimeout(function () {
                                Prism.highlightElement($code[0])
                            }, 100)
                        }
                    })
                }());',
            ],
        );
    }
}
