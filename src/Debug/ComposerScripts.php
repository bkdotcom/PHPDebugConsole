<?php

namespace bdk\Debug;

use Composer\Script\Event;

/**
 * Composer scripts
 *
 * @see https://getcomposer.org/doc/articles/scripts.md
 */
class ComposerScripts
{
    /**
     * Require slevomat/coding-standard if dev mode & PHP >= 7.1
     *
     * Ran after the update command has been executed,
     *   or after the install command has been executed without a lock file present.
     *
     * @param Event $event Composer event instance
     *
     * @return void
     */
    public static function postUpdate(Event $event)
    {
        /*
            Test if Continuous Integration / Travis
            @see https://docs.travis-ci.com/user/environment-variables/#default-environment-variables
        */
        $haveSlevomat = false;
        if ($event->isDevMode()) {
            $info = self::installDependencies();
            $haveSlevomat = $info['haveSlevomat'];
        }
        self::updatePhpcsXml($haveSlevomat);
    }

    /**
     * update phpcs.xml.dist
     * convert relative dirs to absolute
     *
     * @param bool $inclSlevomat Whether or not to include Slevomat sniffs
     *
     * @return void
     */
    public static function updatePhpcsXml($inclSlevomat = true)
    {
        /*
            Comment/uncomment slevomat rule
        */
        $phpcsPath = __DIR__ . '/../../phpcs.xml.dist';
        $xml = \file_get_contents($phpcsPath);

        $ruleSlevomat = '<rule ref="./phpcs.slevomat.xml" />';
        $regex = '#<!--\s*(' . \preg_quote($ruleSlevomat) . ')\s*-->#s';
        $xml = \preg_replace($regex, '$1', $xml);
        if (!$inclSlevomat) {
            \str_replace($ruleSlevomat, '<!--' . $ruleSlevomat . '-->', $xml);
        }

        /*
        $regexFind = '#(<rule ref="[^"]+CognitiveComplexity/ruleset.xml".*?</rule>)#is';
        \preg_match($regexFind, $xml, $matches);
        $ruleCc = $matches[1];
        $regex = '#<!--\s*(' . \preg_quote($ruleCc) . ')\s*-->#s';
        $xml = \preg_replace($regex, '$1', $xml);
        // if (!$inclCognitive) {
        //    \str_replace($ruleCc, '<!--' . $ruleCc . '-->', $xml);
        // }
        */

        \file_put_contents($phpcsPath, $xml);

        if ($inclSlevomat) {
            self::updateSlevomat();
        }
    }

    /**
     * Install/require development dependencies
     *
     * @return array
     */
    private static function installDependencies()
    {
        $composer = $GLOBALS['argv'][0];
        $info = array(
            'haveSlevomat' => false,
        );
        $isCi = \filter_var(\getenv('CI'), FILTER_VALIDATE_BOOLEAN);
        PHP_VERSION_ID >= 80000
            // need a newer version to avoid ReturnTypeWillChange fatal
            // v 2.0 requires php 7.0
            ? \exec($composer . ' require twig/twig ~3.1 --dev --no-scripts')
            : \exec($composer . ' require twig/twig ~1.42 --dev --no-scripts');
        if (PHP_VERSION_ID >= 50500) {
            \exec($composer . ' require guzzlehttp/guzzle --dev --no-scripts');
        }
        if (PHP_VERSION_ID >= 70000) {
            \exec($composer . ' require psr/http-server-middleware --dev --no-scripts');
            \exec($composer . ' require mindplay/middleman --dev --no-scripts');
        }
        if ($isCi) {
            return $info;
        }
        if (PHP_VERSION_ID >= 70200) {
            \exec($composer . ' require slevomat/coding-standard ^8.9.0 --dev --no-scripts');
            $info['haveSlevomat'] = true;
        }
        return $info;
    }

    /**
     * Update phpcs.slevomat.xml
     *
     * @return void
     */
    private static function updateSlevomat()
    {
        $phpcsPath = __DIR__ . '/../../phpcs.slevomat.xml';
        $xml = \file_get_contents($phpcsPath);
        /*
            convert relative paths to absolute
        */
        $regex = '#(<config name="installed_paths" value=")([^"]+)#';
        $xml = \preg_replace_callback($regex, static function ($matches) {
            $baseDir = \realpath(__DIR__ . '/../..') . '/';
            $paths = \preg_split('/,\s*/', $matches[2]);
            foreach ($paths as $i => $path) {
                if (\strpos($path, 'vendor') === 0) {
                    $paths[$i] = $baseDir . $path;
                }
            }
            return $matches[1] . \join(', ', $paths);
        }, $xml);
        \file_put_contents($phpcsPath, $xml);
    }
}
