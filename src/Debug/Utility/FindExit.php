<?php

namespace bdk\Debug\Utility;

/**
 * Attempt to find if shutdown via `exit` or `die`
 */
class FindExit
{

    private $classesSkip = array();
    private $depth = 0;
    private $funcCount = 0;
    private $function = '';
    private $inFunc = false;

    /**
     * Constructor
     *
     * @param array $classesSkip Classnames to bypass when going through backtrace
     */
    public function __construct($classesSkip = array())
    {
        $classesSkip[] = __CLASS__;
        $this->classesSkip = $classesSkip;
    }

    /**
     * If xdebug is avail, search if exit triggered via exit() or die()
     *
     * @return array|null|false array if exit found, null if not, false if not supported
     */
    public function find()
    {
        if (\extension_loaded('xdebug') === false) {
            return false;
        }
        if (\extension_loaded('tokenizer') === false) {
            return false;
        }
        $frame = $this->getLastFrame();
        if (!$frame) {
            return false;
        }
        list($file, $lineStart, $phpSrcCode) = $this->getFrameSource($frame);
        $phpSrcCode = \preg_replace('/^\s*((public|private|protected|final)\s+)+/', '', $phpSrcCode);
        $tokens = $this->getTokens($phpSrcCode);
        /*
        $this->debug->table('tokens', \array_map(function ($token) {
            return \is_array($token)
                ? array(
                    'name' => \token_name($token[0]),
                    'value' => $token[1],
                    'line' => $token[2],
                )
                : array(
                    'value' => $token,
                );
        }, $tokens));
        */
        $this->depth = 0; // keep track of bracket depth
        $this->funcCount = 0;
        $this->function = $frame['function'];
        $this->inFunc = false;
        $token = $this->searchTokens($tokens);
        if ($token) {
            return array(
                'class' => isset($frame['class']) ? $frame['class'] : null,
                'file' => $file,
                'function' => $frame['function'],
                'found' => $token[1],
                'line' => $token[2] + $lineStart - 1,
            );
        }
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
            if (!\is_array($token)) {
                $continue = $this->handleStringToken($token);
                if (!$continue) {
                    return null;
                }
                continue;
            }
            if ($token[0] === T_FUNCTION) {
                $tokenNext = $tokens[$i + 1];
                $this->handleTfunction($tokenNext);
            }
            if (!$this->inFunc) {
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
            if ($this->depth === 0 && $this->inFunc) {
                return false;
            }
        }
        return true;
    }

    /**
     * Chect if we're entering target function
     *
     * @param array|string $tokenNext next token
     *
     * @return void
     */
    private function handleTfunction($tokenNext)
    {
        if ($this->funcCount === 0) {
            $this->depth = 0;
        }
        $this->funcCount++;
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
        foreach ($backtrace as $frame) {
            if (\strpos($frame['function'], 'call_user_func:') === 0) {
                continue;
            }
            if (!isset($frame['class'])) {
                return $frame;
            }
            if (\in_array($frame['class'], $this->classesSkip) === false) {
                return $frame;
            }
        }
        return false;
    }

    /**
     * Get the source code for function called from the specified frame
     *
     * @param array $frame backtrace frame
     *
     * @return string
     */
    private function getFrameSource($frame)
    {
        if ($frame['function'] === '{main}') {
            return array(
                $frame['file'],
                $frame['line'],
                \file_get_contents($frame['file']),
            );
        }
        if (\preg_match('/^.*\{closure:(.+):(\d+)-(\d+)\}$/', $frame['function'], $matches)) {
            $file = $matches[1];
            $lineStart = $matches[2];
            $lineEnd = $matches[3];
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
        $reflection = isset($frame['class'])
            ? (new \ReflectionClass($frame['class']))->getMethod($frame['function'])
            : new \ReflectionFunction($frame['function']);
        if ($reflection->isInternal()) {
            return '';
        }
        $php = \array_slice(
            \file($reflection->getFileName()),
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1
        );
        return array(
            $reflection->getFileName(),
            $reflection->getStartLine(),
            \implode('', $php),
        );
    }

    /**
     * Get tokens for given source
     *
     * @param string $source         php source
     * @param bool   $inclWhitespace include whitespace frames?
     *
     * @return array
     */
    private function getTokens($source, $inclWhitespace = false)
    {
        if (\strpos($source, '<?php') === false) {
            $source = '<?php ' . $source;
        }
        $tokens = \defined('TOKEN_PARSE')
            ? \token_get_all($source, TOKEN_PARSE)
            : \token_get_all($source);
        if ($inclWhitespace === false) {
            $tokens = \array_filter($tokens, function ($token) {
                return !\is_array($token) || $token[0] !== T_WHITESPACE;
            });
            $tokens = \array_values($tokens);
        }
        return $tokens;
    }
}
