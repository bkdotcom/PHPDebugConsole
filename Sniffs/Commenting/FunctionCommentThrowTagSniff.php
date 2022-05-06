<?php

/**
 * Verifies that a @throws tag exists for each exception type a function throws.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */

namespace bdk\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\Commenting\FunctionCommentThrowTagSniff as SquizFunctionCommentThrowTagSniff;

/**
 * Extend Squiz FunctionCommentThrowTagSniff to exclude comments consisting solely of "{@inheritDoc}"
 */
class FunctionCommentThrowTagSniff extends SquizFunctionCommentThrowTagSniff
{
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  The position of the current token
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($this->isInheritDoc($phpcsFile, $stackPtr)) {
            return;
        }
        parent::process($phpcsFile, $stackPtr);
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
