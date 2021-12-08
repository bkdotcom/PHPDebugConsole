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
        $haveCognitiveC = false;
        if ($event->isDevMode()) {
            $info = self::installDependencies();
            $haveSlevomat = $info['haveSlevomat'];
            $haveCognitiveC = $info['haveCognitiveC'];
        }
        self::updatePhpcsXml($haveSlevomat, $haveCognitiveC);
    }

    /**
     * update phpcs.xml.dist
     * convert relative dirs to absolute
     *
     * @param bool $inclSlevomat  whether or not to include Slevomat sniffs
     * @param bool $inclCognitive whether or not to include cognitiveComplexity sniffs
     *
     * @return void
     */
    public static function updatePhpcsXml($inclSlevomat = true, $inclCognitive = true)
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

        $regexFind = '#(<rule ref="[^"]+CognitiveComplexity/ruleset.xml".*?</rule>)#is';
        \preg_match($regexFind, $xml, $matches);
        $ruleCc = $matches[1];
        $regex = '#<!--\s*(' . \preg_quote($ruleCc) . ')\s*-->#s';
        $xml = \preg_replace($regex, '$1', $xml);
        if (!$inclCognitive) {
            \str_replace($ruleCc, '<!--' . $ruleCc . '-->', $xml);
        }

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
    private function installDependencies()
    {
        $info = array(
            'haveSlevomat' => false,
            'haveCognitiveC' => false,
        );
        $isCi = \filter_var(\getenv('CI'), FILTER_VALIDATE_BOOLEAN);
        if (\version_compare(PHP_VERSION, '5.5', '>=')) {
            \exec('composer require guzzlehttp/guzzle ^6.5 --dev --no-scripts');
        }
        if (\version_compare(PHP_VERSION, '7.0', '>=')) {
            \exec('composer require psr/http-server-middleware --dev --no-scripts');
            \exec('composer require mindplay/middleman --dev --no-scripts');
        }
        if ($isCi) {
            return $info;
        }
        if (\version_compare(PHP_VERSION, '7.1', '>=')) {
            \exec('composer require slevomat/coding-standard ^7.0.0 --dev --no-scripts');
            $info['haveSlevomat'] = true;
        }
        if (\version_compare(PHP_VERSION, '7.2', '>=')) {
            \exec('composer require rarst/phpcs-cognitive-complexity --dev --no-scripts');
            $info['haveCognitiveC'] = true;
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
        $matches = array();
        $regex = '#(<config name="installed_paths" value=")([^"]+)#';
        $xml = \preg_replace_callback($regex, function ($matches) {
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
