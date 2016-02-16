<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Task
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */

namespace Netresearch\Kite\Task;
use Netresearch\Kite\Exception;
use Netresearch\Kite\Task;

/**
 * Evaluate an expression and return the result
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Task
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */
class EvaluateTask extends Task
{
    /**
     * @var string
     */
    protected $expression;

    /**
     * Configure the options
     *
     * @return array
     */
    protected function configureVariables()
    {
        return array(
            'expression' => array(
                'type' => 'string',
                'required' => true,
                'label' => 'The question to ask'
            ),
            '--'
        ) + parent::configureVariables();
    }

    /**
     * Set a variable and it's value
     *
     * @param string $name  The variable name
     * @param mixed  $value The value
     *
     * @return $this
     */
    public function offsetSet($name, $value)
    {
        if ($name === 'expression') {
            $this->expression = $value;
        }
        parent::offsetSet($name, $value);
    }


    /**
     * Execute the task
     *
     * @return mixed
     */
    public function execute()
    {
        if (!$this->expression) {
            throw new Exception('Missing expression');
        }
        return $this->getParent()->expand('{' . $this->expression . '}');
    }
}
?>
