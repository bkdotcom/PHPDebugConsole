<?php

namespace bdk\Sniffs\Complexity;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Cognitive Complexity
 */
class CognitiveSniff implements Sniff
{
    /**
     * B1. Increments
     *
     * Boolean operators are handled separately due to their chain logic.
     *
     * @var int[]|string[]
     */
    private const INCREMENTS = [
        T_IF      => T_IF,
        T_ELSE    => T_ELSE,
        T_ELSEIF  => T_ELSEIF,
        T_SWITCH  => T_SWITCH,
        T_FOR     => T_FOR,
        T_FOREACH => T_FOREACH,
        T_WHILE   => T_WHILE,
        T_DO      => T_DO,
        T_CATCH   => T_CATCH,
    ];

    /** @var int[]|string[] */
    private const BOOLEAN_OPERATORS = [
        T_BOOLEAN_AND => T_BOOLEAN_AND, // &&
        T_BOOLEAN_OR  => T_BOOLEAN_OR, // ||
    ];

    /** @var int[]|string[] */
    private const OPERATOR_CHAIN_BREAKS = [
        T_OPEN_PARENTHESIS  => T_OPEN_PARENTHESIS,
        T_CLOSE_PARENTHESIS => T_CLOSE_PARENTHESIS,
        T_SEMICOLON         => T_SEMICOLON,
        T_INLINE_THEN       => T_INLINE_THEN,
        T_INLINE_ELSE       => T_INLINE_ELSE,
    ];

    /**
     * B3. Nesting increments
     *
     * @var int[]|string[]
     */
    private const NESTING_INCREMENTS = [
        T_CLOSURE     => T_CLOSURE,
        T_ELSEIF      => T_ELSEIF,  // increments, but does not receive
        T_ELSE        => T_ELSE,    // increments, but does not receive
        T_IF          => T_IF,
        T_INLINE_THEN => T_INLINE_THEN,
        T_SWITCH      => T_SWITCH,
        T_FOR         => T_FOR,
        T_FOREACH     => T_FOREACH,
        T_WHILE       => T_WHILE,
        T_DO          => T_DO,
        T_CATCH       => T_CATCH,
    ];

    /**
     * B1. Increments
     *
     * @var int[]
     */
    private const BREAKING_TOKENS = [
        T_CONTINUE => T_CONTINUE,
        T_GOTO     => T_GOTO,
        T_BREAK    => T_BREAK,
    ];

    /** @var int */
    public $maxComplexity = 5;

    /** @var int */
    private $cognitiveComplexity = 0;

    /** @var int */
    private $lastBooleanOperator = 0;

    private $phpcsFile;

    /**
     * @return int[]
     */
    public function register()
    {
        return [T_FUNCTION];
    }

    /**
     * @param File $phpcsFile File instance
     * @param int  $stackPtr  Current pointer position
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $this->phpcsFile = $phpcsFile;
        $cognitiveComplexity = $this->computeForFunctionFromTokensAndPosition(
            $stackPtr
        );

        if ($cognitiveComplexity <= $this->maxComplexity) {
            return;
        }

        $name = $phpcsFile->getDeclarationName($stackPtr);

        $phpcsFile->addError(
            'Cognitive complexity for "%s" is %d but has to be less than or equal to %d.',
            $stackPtr,
            'TooHigh',
            [
                $name,
                $cognitiveComplexity,
                $this->maxComplexity,
            ]
        );
    }

    /**
     * @param int $position current index
     *
     * @return int
     */
    public function computeForFunctionFromTokensAndPosition($position)
    {
        $tokens = $this->phpcsFile->getTokens();

        // function without body, e.g. in interface
        if (!isset($tokens[$position]['scope_opener'])) {
            return 0;
        }

        // Detect start and end of this function definition
        $functionStartPosition = $tokens[$position]['scope_opener'];
        $functionEndPosition = $tokens[$position]['scope_closer'];

        $this->lastBooleanOperator = 0;
        $this->cognitiveComplexity = 0;

        /*
            Keep track of parser's level stack
            We push to this stak whenever we encounter a Tokens::$scopeOpeners
        */
        $levelStack = array();
        /*
            We look for changes in token[level] to know when to remove from the stack
            however ['level'] only increases when there are tokens inside {}
            after pushing to the stack watch for a level change
        */
        $levelIncreased = false;

        for ($i = $functionStartPosition + 1; $i < $functionEndPosition; ++$i) {
            $currentToken = $tokens[$i];

            $isNestingToken = false;
            if (\in_array($currentToken['code'], Tokens::$scopeOpeners)) {
                $isNestingToken = true;
                if ($levelIncreased === false && \count($levelStack)) {
                    // parser's level never increased
                    // caused by empty condition such as `if ($x) { }`
                    \array_pop($levelStack);
                }
                $levelStack[] = $currentToken;
                $levelIncreased = false;
            } elseif (isset($tokens[$i - 1]) && $currentToken['level'] < $tokens[$i - 1]['level']) {
                $diff = $tokens[$i - 1]['level'] - $currentToken['level'];
                \array_splice($levelStack, 0 - $diff);
            } elseif (isset($tokens[$i - 1]) && $currentToken['level'] > $tokens[$i - 1]['level']) {
                $levelIncreased = true;
            }

            $this->resolveBooleanOperatorChain($currentToken);

            if (!$this->isIncrementingToken($currentToken, $tokens, $i)) {
                continue;
            }

            ++$this->cognitiveComplexity;

            $addNestingIncrement = isset(self::NESTING_INCREMENTS[$currentToken['code']])
                && !\in_array($currentToken['code'], array(T_ELSEIF, T_ELSE));
            if (!$addNestingIncrement) {
                continue;
            }
            $measuredNestingLevel = \count(\array_filter($levelStack, function ($token) {
                return \in_array($token['code'], self::NESTING_INCREMENTS);
            }));
            if ($isNestingToken) {
                $measuredNestingLevel--;
            }
            // B3. Nesting increment
            if ($measuredNestingLevel > 0) {
                $this->cognitiveComplexity += $measuredNestingLevel;
            }
        }

        return $this->cognitiveComplexity;
    }

    /**
     * Keep track of consecutive matching boolean operators, that don't receive increment.
     *
     * @param mixed[] $token Token
     *
     * @return void
     */
    private function resolveBooleanOperatorChain(array $token)
    {
        // Whenever we cross anything that interrupts possible condition we reset chain.
        if ($this->lastBooleanOperator && isset(self::OPERATOR_CHAIN_BREAKS[$token['code']])) {
            $this->lastBooleanOperator = 0;
            return;
        }

        if (!isset(self::BOOLEAN_OPERATORS[$token['code']])) {
            return;
        }

        // If we match last operator, there is no increment added for current one.
        if ($this->lastBooleanOperator === $token['code']) {
            return;
        }

        ++$this->cognitiveComplexity;
        $this->lastBooleanOperator = $token['code'];
    }

    /**
     * @param mixed[] $token
     * @param mixed[] $tokens
     *
     * @return bool
     */
    private function isIncrementingToken(array $token, array $tokens, $position)
    {
        if (isset(self::INCREMENTS[$token['code']])) {
            return true;
        }

        // B1. ternary operator
        if ($token['code'] === T_INLINE_THEN) {
            return true;
        }

        // B1. goto LABEL, break LABEL, continue LABEL
        if (isset(self::BREAKING_TOKENS[$token['code']])) {
            $nextToken = $this->phpcsFile->findNext(Tokens::$emptyTokens, $position + 1, null, true);
            if ($nextToken === false || $tokens[$nextToken]['code'] !== T_SEMICOLON) {
                return true;
            }
        }

        return false;
    }
}
