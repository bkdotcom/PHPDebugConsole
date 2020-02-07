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
        if ($event->isDevMode() && \version_compare(PHP_VERSION, '7.1', '>=') && !$isCi) {
            \exec('composer require slevomat/coding-standard --dev');
            self::updatePhpcsXml();
        }
    }

    /**
     * update phpcs.xml.dist
     * convert relative dirs to absolute
     *
     * @return void
     */
    public static function updatePhpcsXml()
    {
        $phpcsPath = __DIR__ . '/../../phpcs.xml.dist';
        $regex = '#(<config name="installed_paths" value=")([^"]+)#';
        $xml = \file_get_contents($phpcsPath);
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
