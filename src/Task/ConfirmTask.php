<?php
/**
 * See class comment.
 *
 * PHP Version 5
 *
 * @category   Netresearch
 *
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 *
 * @link       http://www.netresearch.de
 */

namespace Netresearch\Kite\Task;

use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Ask a confirmation question and return the answer.
 *
 * @category   Netresearch
 *
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 *
 * @link       http://www.netresearch.de
 */
class ConfirmTask extends AnswerTask
{
    /**
     * Create a question.
     *
     * @param string $question The question
     * @param mixed  $default  Default value
     *
     * @return ConfirmationQuestion
     */
    protected function createQuestion($question, $default)
    {
        $default = $default !== false;

        return new ConfirmationQuestion(
            $this->formatQuestion($question, $default ? 'y' : 'n'),
            $default
        );
    }
}
