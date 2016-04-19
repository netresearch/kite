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
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\ExpressionLanguage\Token;
use Symfony\Component\ExpressionLanguage\TokenStream;

/**
 * Extended expression lexer - handles strings with nested expressions:
 * For example:
 *   "The job {job.name} is {job.getWorkflow().isRunning() ? 'currently' : 'not'} running"
 * will be transformed into that:
 *   "'The job ' ~ (variables.getExpanded('job.name')) ~ ' is ' ~ (variables.getExpanded('job').getWorkflow().isRunning() ? 'currently' : 'not') ~ 'running'"
 *
 * When there is only one expression, the expression type is not casted to string
 * "{{foo: 'bar'}}" will return the specified hash
 *
 * Also this rewrites objects and properties access in order to use the
 * variableRepository getExpanded method - actually this would have been
 * a parser or node task but here we can do that in the quickest possible
 * way
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage ExpressionLanguage
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */
class Lexer extends \Symfony\Component\ExpressionLanguage\Lexer
{
    /**
     * Tokenizes an expression.
     *
     * @param string $expression The expression to tokenize
     *
     * @return TokenStream A token stream instance
     *
     * @throws SyntaxError
     */
    public function tokenize($expression)
    {
        $cursor = -1;
        $end = strlen($expression);
        $expressions = array(array(null, 0, 0));
        $current = 0;
        $group = 0;
        // Split the expression into it's string and actual expression parts
        while (++$cursor < $end) {
            if ($expression[$cursor] === '{' || $expression[$cursor] === '}') {
                if ($cursor && $expression[$cursor - 1] === '\\') {
                    // Escaped parenthesis
                    // Let parser unescape them after parsing
                } else {
                    $type = $expression[$cursor] === '{' ? 1 : -1;
                    $group += $type;
                    if ($group === 1 && $type === 1) {
                        $expressions[++$current] = array(null, 1, $cursor);
                        continue;
                    } elseif ($group === 0 && $type === -1) {
                        $expressions[++$current] = array(null, 0, $cursor);
                        continue;
                    } elseif ($group < 0) {
                        throw new \Exception('Unopened and unescaped closing parenthesis');
                    }
                }
            }
            $expressions[$current][0] .= $expression[$cursor];
        }
        if ($group) {
            throw new \Exception('Unclosed and unescaped opening parenthesis');
        }

        // Filter out empty expressions
        foreach ($expressions as $i => $properties) {
            if ($properties[0] === null) {
                unset($expressions[$i]);
            }
        }

        // Actually tokenize all remaining expressions
        $tokens = array();
        $isMultipleExpressions = count($expressions) > 1;
        foreach (array_values($expressions) as $i => $properties) {
            list($value, $type, $cursor) = $properties;
            if ($isMultipleExpressions && $i > 0) {
                $tokens[] = new Token(Token::OPERATOR_TYPE, '~', $cursor);
            }
            if ($type === 0) {
                $tokens[] = new Token(Token::STRING_TYPE, $value, $cursor);
            } else {
                if ($isMultipleExpressions) {
                    $tokens[] = new Token(Token::PUNCTUATION_TYPE, '(', $cursor);
                }
                foreach ($this->tokenizeExpression($value) as $token) {
                    $token->cursor += $cursor;
                    $tokens[] = $token;
                }
                if ($isMultipleExpressions) {
                    $tokens[] = new Token(Token::PUNCTUATION_TYPE, ')', $cursor);
                }
            }
        }

        $tokens[] = new Token(Token::EOF_TYPE, null, $end);

        return new TokenStream($tokens);
    }

    /**
     * Actually tokenize an expression - at this point object and property access is
     * transformed, so that "this.property" will be "get('this.propery')"
     *
     * @param string $expression The expression
     *
     * @return array
     */
    protected function tokenizeExpression($expression)
    {
        $stream = parent::tokenize($expression);
        $tokens = array();
        $previousWasDot = false;
        $ignorePrimaryExpressions = array_flip(['null', 'NULL', 'false', 'FALSE', 'true', 'TRUE']);
        while (!$stream->isEOF()) {
            /* @var \Symfony\Component\ExpressionLanguage\Token $token */
            $token = $stream->current;
            $stream->next();
            if ($token->type === Token::NAME_TYPE && !$previousWasDot) {
                if (array_key_exists($token->value, $ignorePrimaryExpressions)) {
                    $tokens[] = $token;
                    continue;
                }
                $isTest = false;
                if ($stream->current->test(Token::PUNCTUATION_TYPE, '(')) {
                    $tokens[] = $token;
                    if ($token->value === 'isset' || $token->value === 'empty') {
                        $isTest = true;
                        $tokens[] = $stream->current;
                        $stream->next();
                        $token = $stream->current;
                        if ($token->type !== Token::NAME_TYPE) {
                            throw new SyntaxError('Expected name', $token->cursor);
                        }
                        $stream->next();
                    } else {
                        continue;
                    }
                }
                $names = array($token->value);
                $isFunctionCall = false;
                while (!$stream->isEOF() && $stream->current->type === Token::PUNCTUATION_TYPE && $stream->current->value === '.') {
                    $stream->next();
                    $nameToken = $stream->current;
                    $stream->next();
                    // Operators like "not" and "matches" are valid method or property names - others not
                    if ($nameToken->type !== Token::NAME_TYPE
                        && ($nameToken->type !== Token::OPERATOR_TYPE || !preg_match('/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/A', $nameToken->value))
                    ) {
                        throw new SyntaxError('Expected name', $nameToken->cursor);
                    }
                    if ($stream->current->test(Token::PUNCTUATION_TYPE, '(')) {
                        $isFunctionCall = true;
                    } else {
                        $names[] = $nameToken->value;
                    }
                }
                if ($isTest) {
                    if ($isFunctionCall) {
                        throw new SyntaxError('Can\'t use function return value in write context',  $stream->current->cursor);
                    }
                    if (!$stream->current->test(Token::PUNCTUATION_TYPE, ')')) {
                        throw new SyntaxError('Expected )',  $stream->current->cursor);
                    }
                    $tokens[] = new Token(Token::STRING_TYPE, implode('.', $names), $token->cursor);
                } else {
                    $tokens[] = new Token(Token::NAME_TYPE, 'get', $token->cursor);
                    $tokens[] = new Token(Token::PUNCTUATION_TYPE, '(', $token->cursor);
                    $tokens[] = new Token(Token::STRING_TYPE, implode('.', $names), $token->cursor);
                    $tokens[] = new Token(Token::PUNCTUATION_TYPE, ')', $token->cursor);
                    if ($isFunctionCall) {
                        $tokens[] = new Token(Token::PUNCTUATION_TYPE, '.', $nameToken->cursor - strlen($nameToken->value));
                        $tokens[] = $nameToken;
                    }
                }
            } else {
                $tokens[] = $token;
                $previousWasDot = $token->test(Token::PUNCTUATION_TYPE, '.');
            }
        }

        return $tokens;
    }

}

?>
