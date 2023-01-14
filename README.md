# PHP&#xfeff;Debug&#xfeff;Console

Browser/javascript like console class for PHP

**Website/Usage/Examples:** <http://www.bradkent.com/php/debug>

* PHP port of the [javascript web console api](https://developer.mozilla.org/en-US/docs/Web/API/console)
* multiple simultaneous output options
  * [ChromeLogger](https://craig.is/writing/chrome-logger/techspecs)
  * [FirePHP](http://www.firephp.org/)  (no FirePHP dependency!)
  * HTML
  * Plain text / file
  * &lt;script&gt;
  * WebSocket (WAMP)
  * "plugin"
* "Collectors" / wrappers for
  * Guzzle
  * Doctrine
  * Mysqli
  * PDO
  * PhpCurlClass
  * SimpleCache
  * SoapClient
  * SwiftMailer
  * more
* [PSR-3](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md) (Logger) Implementation
* [PSR-15](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-15-request-handlers-meta.md) (Middleware) Implementation
* custom error handler
  * errors (even fatal) are captured / logged / displayed
  * optionally send error notices via email (throttled as to not to send out a flood of emails)
* password protected
* send debug log via email

![Screenshot of PHPDebugConsole's Output](http://www.bradkent.com/images/php/screenshot_1.4.png)

## Installation

This library supports PHP 5.4 - 8.2 and has no userland dependencies.

It is installable and autoloadable via [Composer](https://getcomposer.org/) as [bdk/debug](https://packagist.org/packages/bdk/debug).

```json
{
    "require": {
        "bdk/debug": "3.0",
    }
}
```

**installation without Composer**

* [download a release](https://github.com/bkdotcom/PHPDebugConsole/releases) or clone this repository.
* include Autoloader and Debug in your code

```php
// Step 1: include and register our autoloader
require 'path-to/src/Debug/Autoloader.php';
$autoloader = new \bdk\Debug\Autoloader();
$autoloader->register();
// Step 2:  you can now instantiate and user Debug
$debug = new \bdk\Debug();
```

See <http://www.bradkent.com/php/debug> for more information

## Usage

See <http://www.bradkent.com/php/debug>

## PSR-3 Usage

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
$monolog = new \Monolog\Logger('myApplication');
$monolog->pushHandler(new \bdk\Debug\Collector\MonologHandler($debug));
$monolog->critical('all your base are belong to them');
```

## Methods

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

## Tests / Quality

![No Dependencies](https://img.shields.io/badge/dependencies-none-333333.svg)
![Supported PHP versions](https://img.shields.io/static/v1?label=PHP&message=5.4%20-%208.2&color=blue)
![Build Status](https://img.shields.io/github/actions/workflow/status/bkdotcom/PHPDebugConsole/phpunit.yml.svg?logo=github)
[![Codacy Score](https://img.shields.io/codacy/grade/e950849edfd9463b993386080d39875e/master.svg?logo=codacy)](https://app.codacy.com/gh/bkdotcom/PHPDebugConsole/dashboard)
[![Maintainability](https://img.shields.io/codeclimate/maintainability/bkdotcom/PHPDebugConsole.svg?logo=codeclimate)](https://codeclimate.com/github/bkdotcom/PHPDebugConsole)
[![Coverage](https://img.shields.io/codeclimate/coverage-letter/bkdotcom/PHPDebugConsole?logo=codeclimate)](https://codeclimate.com/github/bkdotcom/PHPDebugConsole)

## Changelog

<http://www.bradkent.com/php/debug#changelog>
