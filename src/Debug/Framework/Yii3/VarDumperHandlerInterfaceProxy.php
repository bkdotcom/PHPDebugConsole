<?php

declare(strict_types=1);

namespace bdk\Debug\Framework\Yii3;

use bdk\Debug;
// use bdk\Debug\Framework\Yii3\ProxyTrait;
use Yiisoft\VarDumper\HandlerInterface;

/**
 * Undocumented class
 */
final class VarDumperHandler implements HandlerInterface
{
    // use ProxyTrait;

    /**
     * Constructor
     */
    public function __construct(
        // private readonly HandlerInterface $proxied,
        // private readonly VarDumperCollector $collector,
        private readonly Debug $debug
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function handle(mixed $variable, int $depth, bool $highlight = false): void
    {

        $trace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        $frame = null;
        foreach ($trace as $frame) {
            if (!isset($frame['file'])) {
                continue;
            }
            if (\str_ends_with($frame['file'], '/var-dumper/src/functions.php')) {
                continue;
            }
            if (\str_ends_with($frame['file'], '/var-dumper/src/VarDumper.php')) {
                continue;
            }
            break;
        }
        /** @psalm-var array{file: string, line: int}|null $frame */

        $this->debug->log(
            $variable,
            $frame !== null ? $frame['file'] . ':' . $frame['line'] : ''
        );
        $this->proxied->handle($variable, $depth, $highlight);
    }
}
