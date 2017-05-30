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

use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Ask a selection question and return the answer.
 *
 * @category   Netresearch
 *
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 *
 * @link       http://www.netresearch.de
 */
class ChooseTask extends AnswerTask
{
    /**
     * Configure the options.
     *
     * @return array
     */
    protected function configureVariables()
    {
        return [
            'choices' => [
                'type'     => 'array',
                'required' => true,
                'label'    => 'The choices, the user can choose from',
            ],
            '--',
        ] + parent::configureVariables();
    }

    /**
     * Create the question.
     *
     * @param string $question The question
     * @param mixed  $default  Default value
     *
     * @return ChoiceQuestion
     */
    protected function createQuestion($question, $default)
    {
        return new ChoiceQuestion($this->formatQuestion($question, $default), $this->get('choices'), $default);
    }
}
