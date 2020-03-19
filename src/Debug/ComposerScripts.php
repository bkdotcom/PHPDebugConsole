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
        $isCi = \filter_var(\getenv('CI'), FILTER_VALIDATE_BOOLEAN);
        $inclSlevomat = $event->isDevMode() && \version_compare(PHP_VERSION, '7.1', '>=') && !$isCi;
        if ($inclSlevomat) {
            \exec('composer require slevomat/coding-standard --dev --no-scripts');
        }
        self::updatePhpcsXml($inclSlevomat);
    }

    /**
     * update phpcs.xml.dist
     * convert relative dirs to absolute
     *
     * @param bool $inclSlevomat whether or not to include Slevomat sniffs
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
        $rule = '<rule ref="./phpcs.slevomat.xml" />';
        $regex = '#<!--\s*(' . \preg_quote($rule) . ')\s*-->#s';
        $xml = \preg_replace($regex, '$1', $xml);
        if (!$inclSlevomat) {
            \str_replace($rule, '<!--' . $rule . '-->', $xml);
        }
        \file_put_contents($phpcsPath, $xml);

        if ($inclSlevomat) {
            $phpcsPath = __DIR__ . '/../../phpcs.selevomat.xml';
            $xml = \file_get_contents($phpcsPath);
            /*
                convert relative paths to absolute
            */
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
}
