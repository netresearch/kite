<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage ExpressionLanguage
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */

namespace Netresearch\Kite\ExpressionLanguage;
use Netresearch\Kite\Task;
use Netresearch\Kite\Variables;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\ExpressionLanguage\ParserCache\ParserCacheInterface;

/**
 * Extension of Symfonies expression language to inject our custom
 * lexer and register some functions
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage ExpressionLanguage
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */
class ExpressionLanguage extends \Symfony\Component\ExpressionLanguage\ExpressionLanguage
{
    /**
     * @var string
     */
    const VARIABLES_KEY = 'variables';

    /**
     * ExpressionLanguage constructor.
     *
     * @param ParserCacheInterface|null $cache     The cache
     * @param array                     $providers Providers
     */
    public function __construct(ParserCacheInterface $cache = null, array $providers = array())
    {
        parent::__construct($cache, $providers);

        $reflectionProperty = new \ReflectionProperty('\Symfony\Component\ExpressionLanguage\ExpressionLanguage', 'lexer');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this, new Lexer());
    }

    /**
     * Ask a question
     *
     * @param Task     $task     The task on which the question was asked
     * @param Question $question The question
     *
     * @return mixed The answer
     */
    protected function ask(Task $task, $question)
    {
        $console = $task->console;
        return $console->getHelper('question')->ask(
            $console->getInput(),
            $console->getOutput(),
            $question
        );
    }

    /**
     * Register functions
     *
     * @return void
     */
    protected function registerFunctions()
    {
        parent::registerFunctions();
        $this->register(
            'confirm',
            function ($question) {
            },
            function (array $values, $question) {
                return $this->ask($values[self::VARIABLES_KEY], new ConfirmationQuestion("<question>$question</question> [y] "));
            }
        );
        $this->register(
            'answer',
            function ($question) {
            },
            function (array $values, $question) {
                return $this->ask($values[self::VARIABLES_KEY], new Question("<question>$question</question> "));
            }
        );
        $this->register(
            'choose',
            function ($question) {
            },
            function (array $values, $question, array $choices) {
                return $this->ask($values[self::VARIABLES_KEY], new ChoiceQuestion("<question>$question</question> ", $choices));
            }
        );
        $this->register(
            'isset',
            function ($var) {
            },
            function (array $values, $var) {
                return $values[self::VARIABLES_KEY]->has($var);
            }
        );
        $this->register(
            'empty',
            function ($var) {
            },
            function (array $values, $var) {
                return $values[self::VARIABLES_KEY]->has($var) && $values[self::VARIABLES_KEY]->get($var);
            }
        );
        $this->register(
            'get',
            function () {
            },
            function (array $values, $var) {
                return $values[self::VARIABLES_KEY]->get($var);
            }
        );
        $this->register(
            'set',
            function () {
            },
            function (array $values, $var, $value) {
                $values[self::VARIABLES_KEY]->set($var, $value);
                return $value;
            }
        );
        $this->register(
            'replace',
            function () {
            },
            function (array $values, $search, $replace, $subject, $regex = false) {
                if ($regex) {
                    return preg_replace($search, $replace, $subject);
                } else {
                    return str_replace($search, $replace, $subject);
                }
            }
        );
    }
}
?>
