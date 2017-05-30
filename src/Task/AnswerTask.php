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

use Netresearch\Kite\Task;
use Symfony\Component\Console\Question\Question;

/**
 * Ask a question and return the answer.
 *
 * @category   Netresearch
 *
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 *
 * @link       http://www.netresearch.de
 */
class AnswerTask extends Task
{
    /**
     * Configure the variables.
     *
     * @return array
     */
    protected function configureVariables()
    {
        return [
            'question' => [
                'type'     => 'string',
                'required' => true,
                'label'    => 'The question to ask',
            ],
            'default' => [
                'type'  => 'string|numeric',
                'label' => 'Default value (shown to the user as well)',
            ],
            '--',
        ] + parent::configureVariables();
    }

    /**
     * Format a question.
     *
     * @param string $question The question
     * @param mixed  $default  Default value
     *
     * @return string
     */
    protected function formatQuestion($question, $default = null)
    {
        return '<question>'.$question.'</question> '
        .($default !== null && $default !== '' ? "[{$default}] " : '');
    }

    /**
     * Create a question.
     *
     * @param string $question The question
     * @param mixed  $default  Default value
     *
     * @return Question
     */
    protected function createQuestion($question, $default)
    {
        return new Question($this->formatQuestion($question, $default), $default);
    }

    /**
     * Execute the task.
     *
     * @return mixed
     */
    public function execute()
    {
        $answer = $this->console->getHelper('question')->ask(
            $this->console->getInput(),
            $this->console->getOutput(),
            $this->createQuestion($this->get('question'), $this->get('default', null))
        );

        return $answer;
    }
}
