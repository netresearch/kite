<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category Netresearch
 * @package  Netresearch\Kite
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */

namespace Netresearch\Kite;

/**
 * Base class for Workflows
 *
 * @category Netresearch
 * @package  Netresearch\Kite
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */
abstract class Workflow extends Tasks
{
    /**
     * @var bool
     */
    private $assembled = false;

    /**
     * Variable configuration
     *
     * @return array
     */
    protected function configureVariables()
    {
        return array(
            'tasks' => null,
            'task' => null,
            'workflow' => null,
            'script' => null
        ) + parent::configureVariables();
    }

    /**
     * Called from parent task as soon as task is ready to run - which doesn't
     * necessarely mean that it'll be run.
     *
     * @return void
     */
    protected function initialize()
    {
        parent::initialize();

        if (!$this->assembled) {
            $this->assemble();
            $this->assembled = true;
        }
    }

    /**
     * Override to create the tasks from the according options
     *
     * @param string $name  Variable name
     * @param mixed  $value Variable value
     *
     * @return void
     */
    public function offsetSet($name, $value)
    {
        if (in_array($name, ['workflow', 'script', 'task', 'tasks'], true)) {
            throw new Exception($name . ' not allowed on workflows');
        }
        parent::offsetSet($name, $value);
    }


    /**
     * Run an array of tasks
     *
     * @return $this
     */
    public function run()
    {
        return parent::run();
    }

    /**
     * Override to assemble the tasks
     *
     * @return void
     */
    abstract public function assemble();
}
?>
