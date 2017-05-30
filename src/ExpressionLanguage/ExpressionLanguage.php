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

namespace Netresearch\Kite\ExpressionLanguage;

use Netresearch\Kite\Task;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\ExpressionLanguage\ParserCache\ParserCacheInterface;

/**
 * Extension of Symfonies expression language to inject our custom
 * lexer and register some functions.
 *
 * @category   Netresearch
 *
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 *
 * @link       http://www.netresearch.de
 */
class ExpressionLanguage extends \Symfony\Component\ExpressionLanguage\ExpressionLanguage
{
    /**
     * @var array
     */
    protected $expressionResults = [];

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
    public function __construct(ParserCacheInterface $cache = null, array $providers = [])
    {
        parent::__construct($cache, $providers);

        $reflectionProperty = new \ReflectionProperty('\Symfony\Component\ExpressionLanguage\ExpressionLanguage', 'lexer');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this, new Lexer());
    }

    /**
     * Reevaluate parents evaluation results as expressions could be nested.
     *
     * Don't parse expression strings which were the final result of an evaluation
     * ( As for example, given a variable "char" that is "\{",
     *   the result of "{char}" would be "{". Reevaluating this is 1. not intended
     *   and would 2. lead to an error )
     *
     * @param string|\Symfony\Component\ExpressionLanguage\Expression $expression The expression
     * @param array                                                   $values     The values
     *
     * @return string|mixed
     */
    public function evaluate($expression, $values = [])
    {
        if (is_string($expression)
            && !in_array($expression, $this->expressionResults, true)
            && preg_match($couldBeExpressionPattern = '/(^|[^\\\\])\{/', $expression)
        ) {
            do {
                $expression = parent::evaluate($expression, $values);
                if (!is_string($expression)) {
                    break;
                }
                if (!preg_match($couldBeExpressionPattern, $expression)) {
                    $expression = str_replace(['\\{', '\\}'], ['{', '}'], $expression);
                    if (strpos($expression, '{') !== false) {
                        $this->expressionResults[] = $expression;
                    }
                    break;
                }
            } while (true);
        }

        return $expression;
    }

    /**
     * Ask a question.
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
     * Register functions.
     *
     * @return void
     */
    protected function registerFunctions()
    {
        parent::registerFunctions();
        $functions = [
            'call' => function (array $values, $function) {
                $args = array_slice(func_get_args(), 2);
                if (array_key_exists($function, $this->functions)) {
                    array_unshift($args, $values);
                    $function = $this->functions[$function]['evaluator'];
                }

                return call_user_func_array($function, $args);
            },
            'isset' => function (array $values, $var) {
                return $values[self::VARIABLES_KEY]->has($var);
            },
            'empty' => function (array $values, $var) {
                return !$values[self::VARIABLES_KEY]->has($var) || !$values[self::VARIABLES_KEY]->get($var);
            },
            'get' => function (array $values, $var) {
                return $values[self::VARIABLES_KEY]->get($var);
            },
            'set' => function (array $values, $var, $value) {
                $values[self::VARIABLES_KEY]->set($var, $value);

                return $value;
            },
            'confirm' => function (array $values, $question) {
                return $this->ask($values[self::VARIABLES_KEY], new ConfirmationQuestion("<question>$question</question> [y] "));
            },
            'answer' => function (array $values, $question) {
                return $this->ask($values[self::VARIABLES_KEY], new Question("<question>$question</question> "));
            },
            'choose' => function (array $values, $question) {
                return $this->ask($values[self::VARIABLES_KEY], new Question("<question>$question</question> "));
            },
            'replace' => function (array $values, $search, $replace, $subject, $regex = false) {
                $values[self::VARIABLES_KEY]->console->output(
                    '<warning>Expression language function "replace" is deprecated '
                    .'and will be removed in 1.6.0 - use preg_replace or str_replace</warning>'
                );
                if ($regex) {
                    return preg_replace($search, $replace, $subject);
                } else {
                    return str_replace($search, $replace, $subject);
                }
            },
        ];
        foreach ($functions as $name => $function) {
            $this->register(
                $name,
                function () {
                },
                $function
            );
        }
    }
}
