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
 * Base Task Class
 *
 * {@see configureVariables() for option information}
 *
 * @category Netresearch
 * @package  Netresearch\Kite
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */
abstract class Task extends Variables
{
    /**
     * @var \Netresearch\Kite\Service\Console
     */
    public $console;

    /**
     * @var Job
     */
    protected $job;

    /**
     * Task constructor.
     *
     * @param Variables $parent Parent object (Task/Job/Workflow)
     */
    public function __construct(Variables $parent)
    {
        $this->job = $parent instanceof Job ? $parent : $parent->get('job');
        $this->console = $this->job->console;
        
        parent::__construct($parent);
    }

    /**
     * Configures the available options
     *
     * @return array
     */
    protected function configureVariables()
    {
        return array(
            'name' => array(
                'type' => 'string',
                'label' => 'Name of the task'
            ),
            'after' => array(
                'type' => 'string',
                'label' => 'Name of the task to execute this task after'
            ),
            'before' => array(
                'type' => 'string',
                'label' => 'Name of the task to execute this task before'
            ),
            'message' => array(
                'type' => 'string',
                'label' => 'Message to output when job is run with --dry-run or prior to execution'
            ),
            'if' => array(
                'type' => array('string', 'callback', 'bool'),
                'label' => 'Expression string, callback returning true or false or boolean. Depending of that the task will be executed or not'
            ),
            'executeInPreview' => array(
                'type' => 'bool',
                'default' => false,
                'label' => 'Whether to execute this task even when job is run with --dry-run'
            ),
            'force' => array(
                'type' => 'bool',
                'default' => false,
                'label' => 'Whether this task should be run even when prior tasks (inside the current workflow) failed, exited or broke execution.'
            ),
            'toVar' => array(
                'type' => 'string',
                'label' => 'The variable to save the return value of the execute method of the task to.'
            ),
        );
    }

    /**
     * Called from parent task as soon as task is ready to run - which doesn't
     * necessarely mean that it'll be run.
     *
     * @return void
     */
    protected function initialize()
    {
    }

    /**
     * Shortcut to set()
     *
     * @param bool $flag The flag
     *
     * @return $this
     */
    public function executeInPreview($flag = true)
    {
        return $this->set(__FUNCTION__, $flag);
    }

    /**
     * Shortcut to set()
     *
     * @param string $var The variable name
     *
     * @return $this
     */
    public function toVar($var)
    {
        return $this->set(__FUNCTION__, $var);
    }

    /**
     * Shortcut to set()
     *
     * @param bool $flag The flag
     *
     * @return $this
     */
    public function force($flag = true)
    {
        return $this->set(__FUNCTION__, $flag);
    }

    /**
     * Shortcut to set()
     *
     * @param string $message The message
     *
     * @return $this
     */
    public function message($message)
    {
        return $this->set(__FUNCTION__, $message);
    }

    /**
     * Shortcut to set()
     *
     * @param string|callable|boolean $condition The condition
     *
     * @return $this
     */
    public function when($condition)
    {
        return $this->set('if', $condition);
    }

    /**
     * Run this task
     *
     * @throws Exception
     *
     * @return mixed
     */
    public function run()
    {
        $this->preview();
        if ($this->shouldExecute()) {
            return $this->execute();
        }
    }

    /**
     * Determines whether this task should be executed
     *
     * @return bool
     */
    protected function shouldExecute()
    {
        return !$this->job->isDryRun() || $this->get('executeInPreview');
    }

    /**
     * The preview for this task - this is called right before execution and
     * in preview mode
     *
     * @return void
     */
    public function preview()
    {
        $message = $this->get('message');
        if ($message) {
            $this->console->output($message);
        }
    }

    /**
     * Actually execute this task
     *
     * @return mixed
     */
    public function execute()
    {
        throw new Exception('Method not implemented');
    }
}
?>
