<?php

namespace bdk\Debug\Utility;

use bdk\Backtrace;
use Reflector;

/**
 * Attempt to find if shutdown via `exit` or `die`
 *
 * Requires xdebug and xdebug.mode develop
 */
class FindExit
{
    private $classesSkip = array();
    private $depth = 0;
    private $funcStack = array(); // nested functions
    private $function = '';
    private $inFunc = false;

    /**
     * Constructor
     *
     * @param array $classesSkip Classnames to bypass when going through backtrace
     */
    public function __construct($classesSkip = array())
    {
        $this->setSkipClasses($classesSkip);
    }

    /**
     * If xdebug is avail, search if exit triggered via exit() or die()
     *
     * @return array|null|false array if exit found, null if not, false if not supported
     */
    public function find()
    {
        if (\extension_loaded('tokenizer') === false) {
            return false; // @codeCoverageIgnore
        }
        if (Backtrace::isXdebugFuncStackAvail() === false) {
            return false; // @codeCoverageIgnore
        }
        $frame = $this->getLastFrame();
        if (!$frame) {
            return false;
        }
        list($file, $lineStart, $phpSrcCode) = $this->getFrameSource($frame);
        $phpSrcCode = \preg_replace('/^\s*((public|private|protected|final|static)\s+)+/', '', $phpSrcCode);
        $tokens = $this->getTokens($phpSrcCode, true, false);
        $this->searchTokenInit($frame);
        $token = $tokens
            ? $this->searchTokens($tokens)
            : null;
        return $token
            ? array(
                'class' => $frame['class'],
                'file' => $file,
                'found' => $token[1],
                'function' => $frame['function'],
                'line' => $token[2] + $lineStart - 1,
            )
            : null;
    }

    /**
     * Get tokens for given source
     *
     * @param string $source         php source
     * @param bool   $parse          parse
     * @param bool   $inclWhitespace include whitespace tokens?
     * @param int    $startLine      (1) start line number
     *
     * @return array|false
     */
    public static function getTokens($source, $parse = true, $inclWhitespace = true, $startLine = 1)
    {
        $addOpen = \strpos($source, '<?php') === false;
        if ($addOpen) {
            $source = '<?php ' . $source;
        }
        try {
            $tokens = \defined('TOKEN_PARSE') && $parse
                ? \token_get_all($source, TOKEN_PARSE)
                : \token_get_all($source);
        } catch (\ParseError $e) {
            return false;
        }
        if ($addOpen) {
            \array_shift($tokens);
        }
        $tokens = \array_filter($tokens, static function ($token) use ($inclWhitespace) {
            return $inclWhitespace || \is_array($token) === false || $token[0] !== T_WHITESPACE;
        });
        $tokens = \array_map(static function ($token) use ($startLine) {
            if (\is_array($token) === false) {
                return $token;
            }
            $token[2] = $token[2] + $startLine - 1;
            return $token;
        }, $tokens);
        return \array_values($tokens);
    }

    /**
     * Set the classes that should be skipped when going through backtrace
     *
     * @param strjing|array $classes Classnames to bypass
     *
     * @return void
     */
    public function setSkipClasses($classes)
    {
        $classes = (array) $classes;
        $classes[] = __CLASS__;
        $this->classesSkip = $classes;
    }

    /**
     * Reset/Init depth, & function stack
     *
     * @param array $frame backtrace frame
     *
     * @return void
     */
    private function searchTokenInit($frame)
    {
        $this->depth = 0; // keep track of bracket depth
        $this->funcStack = array();
        $this->function = $frame['function'];
        $this->inFunc = empty($frame['function']);
    }

    /**
     * Search tokens for die/exit within function
     *
     * @param array $tokens array of tokens
     *
     * @return array|null found exit token or null
     */
    private function searchTokens($tokens)
    {
        $count = \count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (\is_array($token) === false) {
                $continue = $this->handleStringToken($token);
                if ($continue) {
                    continue;
                }
                return null;
            }
            if ($token[0] === T_FUNCTION) {
                $tokenNext = $tokens[$i + 1];
                $this->handleTfunction($tokenNext);
            }
            if (!$this->inFunc) {
                continue;
            }
            if ($this->funcStack) {
                continue;
            }
            if ($token[0] === T_EXIT) {
                return $token;
            }
        }
        return null;
    }

    /**
     * keep track of bracket depth
     *
     * @param string $token token string
     *
     * @return bool
     */
    private function handleStringToken($token)
    {
        if ($token === '{') {
            $this->depth++;
        } elseif ($token === '}') {
            $this->depth--;
            if (\end($this->funcStack) === $this->depth) {
                \array_pop($this->funcStack);
            }
            if ($this->function && $this->depth === 0 && $this->inFunc) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if we're entering target function
     *
     * @param array|string $tokenNext next token
     *
     * @return void
     */
    private function handleTfunction($tokenNext)
    {
        if ($this->inFunc) {
            $this->funcStack[] = $this->depth;
            return;
        }
        if (
            \is_array($tokenNext)
            && $tokenNext[0] === T_STRING
            && $tokenNext[1] === $this->function
        ) {
            $this->depth = 0;
            $this->inFunc = true;
        } elseif ($tokenNext === '(' && \strpos($this->function, '{closure:') !== false) {
            $this->depth = 0;
            $this->inFunc = true;
        }
    }

    /**
     * Get the last frame
     *
     * @return array|false
     */
    private function getLastFrame()
    {
        $backtrace = \xdebug_get_function_stack();
        $backtrace = \array_reverse($backtrace);
        $frame = false;
        foreach ($backtrace as $frame) {
            $frame = \array_merge(array(
                'class' => null,
                'file' => null,
                'function' => '',
            ), $frame);
            if (\strpos($frame['function'], 'call_user_func:') === 0) {
                continue;
            }
            if (\in_array($frame['class'], $this->classesSkip, true)) {
                continue;
            }
            if ($this->isFrameInternal($frame) === false) {
                break;
            }
        }
        return $frame;
    }

    /**
     * Get the source code for function called from the specified frame
     *
     * @param array $frame backtrace frame
     *
     * @return array [$filepath, start-line, src]
     */
    private function getFrameSource($frame)
    {
        if (isset($frame['include_filename'])) {
            return array(
                $frame['include_filename'],
                0,
                \file_get_contents($frame['include_filename']),
            );
        }
        if ($frame['function'] === '{main}') {
            return array(
                $frame['file'],
                $frame['line'],
                \file_get_contents($frame['file']),
            );
        }
        // xdebug < 3.0: namespace\{closure}
        // xdebug 3.0    namespace\{closure:filepath.php:48-55}
        if (\preg_match('/^.*\{closure:(.+):(\d+)-(\d+)\}$/', $frame['function'], $matches)) {
            return $this->getFrameSourceClosure($matches[1], $matches[2], $matches[3]);
        }
        try {
            $reflector = isset($frame['class'])
                ? (new \ReflectionClass($frame['class']))->getMethod($frame['function'])
                : new \ReflectionFunction($frame['function']);
            return $this->getFrameSourceReflection($reflector);
        } catch (\ReflectionException $e) {
            return array($frame['file'], $frame['line'], ''); // @codeCoverageIgnore
        }
    }

    /**
     * Get the source code for a closure
     *
     * @param string $file      file path
     * @param int    $lineStart start line
     * @param int    $lineEnd   end line
     *
     * @return array [filepath, lineStart, src]
     */
    private function getFrameSourceClosure($file, $lineStart, $lineEnd)
    {
        $php = \array_slice(
            \file($file),
            $lineStart - 1,
            $lineEnd - $lineStart + 1
        );
        return array(
            $file,
            $lineStart,
            \implode('', $php),
        );
    }

    /**
     * Get the source code for a function or class method
     *
     * @param Reflector $reflector Reflector instance
     *
     * @return array [filepath, lineStart, src]
     */
    private function getFrameSourceReflection(Reflector $reflector)
    {
        if ($reflector->isInternal()) {
            return array(
                null,
                0,
                '',
            );
        }
        $php = \array_slice(
            \file($reflector->getFileName()),
            $reflector->getStartLine() - 1,
            $reflector->getEndLine() - $reflector->getStartLine() + 1
        );
        return array(
            $reflector->getFileName(),
            $reflector->getStartLine(),
            \implode('', $php),
        );
    }

    /**
     * Is frame a core php function (vs user defined)
     *
     * @param array $frame backtrace frame
     *
     * @return bool
     */
    private function isFrameInternal($frame)
    {
        try {
            $reflection = isset($frame['class'])
                ? (new \ReflectionClass($frame['class']))->getMethod($frame['function'])
                : new \ReflectionFunction($frame['function']);
            return $reflection->isInternal();
        } catch (\ReflectionException $e) {
            // {closure...} or {main}, etc
            return false;
        }
    }
}
