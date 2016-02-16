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
 * Catch exceptions while executing tasks
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Task
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */
class TryCatchTask extends SubTask
{
    /**
     * @var \Netresearch\Kite\Task
     */
    protected $catchTask;

    /**
     * @var bool If the next fetched task should be the catch task
     */
    protected $catch = false;

    /**
     * Configure the options
     *
     * @return array
     */
    protected function configureVariables()
    {
        return array(
            'onCatch' => array(
                'type' => 'array',
                'label' => 'Task to execute when an exception was catched'
            ),
            'errorMessage' => array(
                'type' => 'string',
                'label' => 'Message to display on error'
            ),
            '--'
        ) + parent::configureVariables();
    }

    /**
     * Set a variable or it's value
     *
     * @param string $name  The name
     * @param mixed  $value The value
     *
     * @return void
     */
    public function offsetSet($name, $value)
    {
        if ($name === 'onCatch') {
            $this->catchTask = $this->factory->createTask($value, $this);
            return;
        }
        parent::offsetSet($name, $value);
    }


    /**
     * Set task as catchTask if $this->catch
     *
     * @param \Netresearch\Kite\Task $task The task
     *
     * @return $this|mixed $this or the task return value when this is running
     */
    public function addTask(Task $task)
    {
        if ($this->catch) {
            $this->catchTask = $task;
            $this->catch = false;
            return $task;
        }
        return parent::addTask($task);
    }

    /**
     * Fetch the next task as that task to execute when an exception occured while
     * executing the other tasks
     *
     * @return $this
     */
    public function onCatch()
    {
        if ($this->catchTask) {
            throw new Exception('Only one task may be executed on catch');
        }
        $this->catch = true;
        return $this;
    }

    /**
     * Run an array of tasks
     *
     * @return mixed The ran tasks return value
     */
    public function run()
    {
        try {
            return parent::run();
        } catch (\Exception $e) {
            $message = $this->get('errorMessage');
            if ($message) {
                $this->console->output($message);
            }
            if ($this->catchTask) {
                return $this->addTask($this->catchTask);
            }
            return null;
        }
    }
}
?>
