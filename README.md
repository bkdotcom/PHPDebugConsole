PHP&#xfeff;Debug&#xfeff;Console
===============

Browser/javascript like console class for PHP

**Website/Usage/Examples:** http://www.bradkent.com/?page=php/debug

* PHP port of the [javascript web console api](https://developer.mozilla.org/en-US/docs/Web/API/console)
* can abstract/wrap [FirePHP](http://www.firephp.org/)
* custom error handler

![Screenshot of PHPDebugConsole's Output](http://www.bradkent.com/images/bradkent.com/php/screenshot_1.3.png)

### Installation
This library requires PHP 5.3 (namespaces) or later and has no userland dependencies.

It is installable and autoloadable via [Composer](https://getcomposer.org/) as [bdk/debug](https://packagist.org/packages/bdk/debug).

```json
{
    "require": {
        "bdk/debug": "~1.2",
    }
}
```
Alternatively, [download a release](https://github.com/bkdotcom/debug/releases) or clone this repository, then require or include its `Debug.php` file

See http://www.bradkent.com/?page=php/debug for more information

### Methods

* log
* info
* warn
* error
* assert
* count
* group
* groupCollapsed
* groupEnd
* table
* time
* timeEnd
* *&hellip; [more](http://www.bradkent.com/?page=php/debug#docs-methods)*

### Quality
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/789295b4-6040-4367-8fd5-b04a6f0d7a0c/big.png)](https://insight.sensiolabs.com/projects/789295b4-6040-4367-8fd5-b04a6f0d7a0c)

### Changelog
http://www.bradkent.com/?page=php/debug#docs-changelog
