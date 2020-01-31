<?php

/**
 * Parses and verifies the doc comments for functions.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */

namespace bdk\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\Commenting\FunctionCommentSniff as SquizFunctionCommentSniff;

class FunctionCommentSniff extends SquizFunctionCommentSniff
{

    protected $isInheritDoc = false;

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  The position of the current token
     *                           in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $this->isInheritDoc = $this->isInheritDoc($phpcsFile, $stackPtr);
        parent::process($phpcsFile, $stackPtr);
    }

    /**
     * Process the return comment of this function comment.
     *
     * @param File $phpcsFile    The file being scanned.
     * @param int  $stackPtr     The position of the current token
     *                              in the stack passed in $tokens.
     * @param int  $commentStart The position in the stack where the comment started.
     *
     * @return void
     */
    protected function processReturn(File $phpcsFile, $stackPtr, $commentStart)
    {
        if ($this->isInheritDoc) {
            return;
        }
        parent::processReturn($phpcsFile, $stackPtr, $commentStart);
    }

    /**
     * Process any throw tags that this function comment has.
     *
     * @param File $phpcsFile    The file being scanned.
     * @param int  $stackPtr     The position of the current token
     *                              in the stack passed in $tokens.
     * @param int  $commentStart The position in the stack where the comment started.
     *
     * @return void
     */
    protected function processThrows(File $phpcsFile, $stackPtr, $commentStart)
    {
        if ($this->isInheritDoc) {
            return;
        }
        parent::processThrows($phpcsFile, $stackPtr, $commentStart);
    }

    /**
     * Process the function parameter comments.
     *
     * @param File $phpcsFile    The file being scanned.
     * @param int  $stackPtr     The position of the current token
     *                              in the stack passed in $tokens.
     * @param int  $commentStart The position in the stack where the comment started.
     *
     * @return void
     */
    protected function processParams(File $phpcsFile, $stackPtr, $commentStart)
    {
        if ($this->isInheritDoc) {
            return;
        }
        parent::processParams($phpcsFile, $stackPtr, $commentStart);
    }

    /**
     * Does comment only contain "{@inheritdoc}" ?
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  The position of the current token
     *
     * @return bool
     */
    private function isInheritDoc(File $phpcsFile, $stackPtr)
    {
        $start = $phpcsFile->findPrevious(T_DOC_COMMENT_OPEN_TAG, $stackPtr) + 1;
        $end = $phpcsFile->findNext(T_DOC_COMMENT_CLOSE_TAG, $start);
        $content = $phpcsFile->getTokensAsString($start, ($end - $start));
        // remove leading "*"s
        $content = \preg_replace('#^[ \t]*\*[ ]?#m', '', $content);
        $content = \trim($content);
        return \preg_match('#^{@inheritdoc}$#i', $content) === 1;
    }
}
