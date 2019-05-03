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

use bdk\Debug\AssetProvider;

/**
 * Register prismjs' javascript & css
 */
class Prism implements AssetProvider
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
                        var target = e.target;
                        if ($(target).is(".m_group")) {
                            return;
                        }
                        // console.log("Prism enhanced.debug", target);
                        Prism.highlightAllUnder(target);
                    });
                }());',
            ),
        );
    }
}
