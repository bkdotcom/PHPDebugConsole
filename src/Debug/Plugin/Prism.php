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
                'pre[class*="language-"] {
                    padding: 0;
                    margin: 0;
                }'
            ),
            'script' => array(
                './js/prism.js',
                '(function(){
                    $("body").on("enhanced.debug", function(e){
                        var target = e.target,
                            $prism,
                            classes,
                            lang,
                            length,
                            i;
                        if ($(target).is(".m_group")) {
                            return;
                        }
                        $prism = $(target).find(".prism").removeClass("prism").css({display:"block"});
                        if (!$prism.length) {
                            return;
                        }
                        classes = $prism.attr("class").split(" ");
                        for (i = 0, length = classes.length; i < length; i++) {
                            if (classes[i].match(/^language-/)) {
                                lang = classes[i];
                                $prism.removeClass(lang);
                                break;
                            }
                        }
                        $prism.wrapInner(\'<pre><code class="\'+lang+\'"></code></pre>\');
                        // console.log("Prism enhanced.debug", target);
                        Prism.highlightAllUnder(target);
                    });
                }());',
            ),
        );
    }
}
