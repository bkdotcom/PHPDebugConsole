<?php

namespace bdk\Debug\Dev;

use Composer\Script\Event;

/**
 * Composer scripts
 *
 * @see https://getcomposer.org/doc/articles/scripts.md
 */
class ComposerScripts
{
    protected static $phpVersion;

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
        $platform = \array_replace_recursive(array(
            'php' => PHP_VERSION,
        ), $event->getComposer()->getConfig()->get('platform') ?: array());
        self::$phpVersion = $platform['php'];

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
        $phpcsPath = __DIR__ . '/../phpcs.xml.dist';
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
     * Get the composer command we were invoked with
     *
     * @return string
     */
    private static function getComposerCommand()
    {
        $composer = $GLOBALS['argv'][0];
        if (\substr($composer, -5) === '.phar') {
            // composer.phar
            return 'php ' . $composer;
        }
        return $composer;
    }

    /**
     * Install/require development dependencies
     *
     * @return array
     */
    private static function installDependencies()
    {
        $composer = self::getComposerCommand();
        $info = array(
            'haveSlevomat' => false,
        );
        self::installUnitTestDependencies();
        if (\filter_var(\getenv('CI'), FILTER_VALIDATE_BOOLEAN)) {
            return $info;
        }
        if (\version_compare(self::$phpVersion, '8.0.0', '>=')) {
            \exec($composer . ' require vimeo/psalm ^5.22.2 --dev --with-all-dependencies --no-scripts');
        }
        if (\version_compare(self::$phpVersion, '7.2.0', '>=')) {
            \exec($composer . ' require slevomat/coding-standard ^8.9.0 --dev --with-all-dependencies --no-scripts');
            $info['haveSlevomat'] = true;
        }
        return $info;
    }

    /**
     * Install dependencies needed for unit tests
     *
     * @return void
     */
    private static function installUnitTestDependencies()
    {
        // disable audit block-insecure (needed for older twig versions)
        self::updateComposerJson('audit.block-insecure', false);

        $composer = self::getComposerCommand();
        \version_compare(self::$phpVersion, '8.0.0', '>=')
            // need a newer version to avoid ReturnTypeWillChange fatal
            // v 2.0 requires php 7.0
            ? \exec($composer . ' require twig/twig ~3.1 --dev --no-scripts')
            : \exec($composer . ' require twig/twig ~1.42 --dev --no-scripts');
        if (\version_compare(self::$phpVersion, '7.0.0', '>=')) {
            \exec($composer . ' require psr/http-server-middleware --dev --no-scripts');
            \exec($composer . ' require mindplay/middleman --dev --no-scripts');
        }
        if (\version_compare(self::$phpVersion, '5.5.0', '>=')) {
            \exec($composer . ' require guzzlehttp/guzzle --dev --no-scripts');
        }
    }

    /**
     * Update composer.json
     *
     * https://github.com/composer/composer/issues/12611
     * `composer config audit.block-insecure false`
     * Setting audit.block-insecure does not exist or is not supported by this command
     *
     * @param string|null $path  Dot notation path to value to set
     * @param mixed       $value Value to set
     *
     * @return void
     */
    private static function updateComposerJson($path, $value)
    {
        $composerJsonPath = __DIR__ . '/../composer.json';
        $json = \file_get_contents($composerJsonPath);
        $data = \json_decode($json, true);
        $path = \explode('.', $path);
        foreach ($path as $key) {
            if (!isset($data[$key]) || !\is_array($data[$key])) {
                $data[$key] = array();
            }
            $data = &$data[$key];
        }
        $data = $value;
        $newJson = \json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        \file_put_contents($composerJsonPath, $newJson);
    }

    /**
     * Update phpcs.slevomat.xml
     *
     * @return void
     */
    private static function updateSlevomat()
    {
        $phpcsPath = __DIR__ . '/../phpcs.slevomat.xml';
        $xml = \file_get_contents($phpcsPath);
        /*
            convert relative paths to absolute
        */
        $regex = '#(<config name="installed_paths" value=")([^"]+)#';
        $xml = \preg_replace_callback($regex, static function ($matches) {
            $baseDir = \realpath(__DIR__ . '/..') . '/';
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
