PHP&#xfeff;Debug&#xfeff;Console
===============

Browser/javascript like console class for PHP

**Website/Usage/Examples:** http://www.bradkent.com/php/debug

* PHP port of the [javascript web console api](https://developer.mozilla.org/en-US/docs/Web/API/console)
* multiple simultaneous output options
    * [ChromeLogger](https://craig.is/writing/chrome-logger/techspecs)
    * [FirePHP](http://www.firephp.org/)  (no FirePHP dependency!)
    * HTML
    * Plain text / file
    * &lt;script&gt;
    * WebSocket (WAMP)
    * "plugin"
* [PSR-3](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md) (Logger) Implementation
* custom error handler
    * errors (even fatal) are captured / logged / displayed
    * send error notices via email (throttled as to not to send out a flood of emails)
* password protected
* send debug log via email

![Screenshot of PHPDebugConsole's Output](http://www.bradkent.com/images/php/screenshot_1.4.png)

### Installation
This library requires PHP 5.4 (function array dereferencing, closure `$this` support) or later and has no userland dependencies.

It is installable and autoloadable via [Composer](https://getcomposer.org/) as [bdk/debug](https://packagist.org/packages/bdk/debug).

```json
{
    "require": {
        "bdk/debug": "^2.0",
    }
}
```
Alternatively, [download a release](https://github.com/bkdotcom/PHPDebugConsole/releases) or clone this repository, then require `src/Debug/Debug.php`

See http://www.bradkent.com/php/debug for more information

### Usage

See http://www.bradkent.com/php/debug

### PSR-3 Usage

PHPDebugConsole includes a [PSR-3](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md) implementation (which can be used as a [monolog](https://github.com/Seldaek/monolog) PSR handler).  If you're using a application or library that uses these standards, drop PHPDebugConsole right in.

(this library includes neither psr/log or monolog/monolog.  Include separately if needed.)

PSR-3:
```php
// instantiate PHPDebugLogger / get instance
$debug = \bdk\Debug::getInstance();
$psr3logger = $debug->logger;
$psr3logger->emergency('fallen and can\'t get up');
```

monolog:
```php
$monolog = new Monolog\Logger('myApplication');
$monolog->pushHandler(new Monolog\Handler\PsrHandler($debug->logger));
$monolog->critical('all your base are belong to them');
```

### Methods

* log
* info
* warn
* error
* assert
* clear
* count
* countReset
* group
* groupCollapsed
* groupEnd
* profile
* profileEnd
* table
* time
* timeEnd
* timeLog
* trace
* *&hellip; [more](http://www.bradkent.com/php/debug#methods)*

### Tests / Quality
![No Dependencies](https://img.shields.io/badge/dependencies-none-333333.svg)
[![Build Status](https://img.shields.io/travis/bkdotcom/PHPDebugConsole/v2.3.svg)](https://travis-ci.org/bkdotcom/PHPDebugConsole)
[![SensioLabsInsight](https://img.shields.io/sensiolabs/i/789295b4-6040-4367-8fd5-b04a6f0d7a0c.svg)](https://insight.sensiolabs.com/projects/789295b4-6040-4367-8fd5-b04a6f0d7a0c)

### Changelog
http://www.bradkent.com/php/debug#changelog
