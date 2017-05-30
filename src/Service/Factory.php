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

namespace Netresearch\Kite\Service;

use Netresearch\Kite\Exception;
use Netresearch\Kite\Job;
use Netresearch\Kite\Task;
use Netresearch\Kite\Variables;
use Netresearch\Kite\Workflow;

/**
 * Task factory.
 *
 * @category   Netresearch
 *
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 *
 * @link       http://www.netresearch.de
 */
class Factory
{
    /**
     * @var Console
     */
    protected $console;

    /**
     * @var array
     */
    protected $namespaces = [
        'task' => [
            'Netresearch\Kite\Task',
        ],
        'workflow' => [
            'Netresearch\Kite\Workflow',
        ],
    ];

    /**
     * Construct factory.
     *
     * @param Console $console Optional console (passed to Job)
     */
    public function __construct(Console $console)
    {
        $this->console = $console;
    }

    /**
     * Create a job.
     *
     * @param string $job The job name
     *
     * @return Job
     */
    public function createJob($job)
    {
        $jobInstance = new Job($this->console);

        return $jobInstance->setFromArray($this->console->getConfig()->getJobConfiguration($job));
    }

    /**
     * Create/validate a workflow.
     *
     * @param string|Workflow $workflow  The workflow definition
     * @param Variables       $variables Parent object for the workflow
     *
     * @return Workflow
     */
    public function createWorkflow($workflow, Variables $variables)
    {
        if (is_string($workflow)) {
            $className = $this->getWorkflowClassName($workflow);
            $workflow = new $className($variables);
        }
        if (!$workflow instanceof Workflow) {
            throw new Exception('Workflow must extend \Netresearch\Kite\Domain\Model\Workflow');
        }

        return $workflow;
    }

    /**
     * Create a task.
     *
     * @param string|array|callable $task      The task name, it's properies or a callable
     * @param Variables             $variables The parent variables object for the task
     * @param array                 $options   Options for the task
     *
     * @return \Netresearch\Kite\Task
     */
    public function createTask($task, Variables $variables, array $options = [])
    {
        if (is_array($task)) {
            if (count($task) === 2 && array_key_exists(0, $task) && array_key_exists(1, $task) && is_callable($task)) {
                $options['callback'] = $task;
                $task = 'callback';
            } else {
                $options += $task;
                if (array_key_exists('workflow', $options)) {
                    unset($options['workflow']);
                    $task = $this->createWorkflow($task['workflow'], $variables);
                } else {
                    unset($options['type']);
                    $task = array_key_exists('type', $task) ? $task['type'] : 'sub';
                }
            }
        } elseif ($task instanceof \Closure) {
            $options['callback'] = $task;
            $task = 'callback';
        }
        if (is_string($task)) {
            $className = $this->getTaskClassName($task);
            $task = new $className($variables);
        }
        if ($task instanceof Task) {
            $task->setFromArray($options);
        } else {
            throw new Exception('Invalid task definition');
        }

        return $task;
    }

    /**
     * Get the full class name from a task definition.
     *
     * @param string $definition Last part of class name without postfix or full class name
     *
     * @return string
     */
    public function getTaskClassName($definition)
    {
        return $this->getClassName($definition, 'task');
    }

    /**
     * Get the full class name from a workflow definition.
     *
     * @param string $definition Last part of class name without postfix or full class name
     *
     * @return string
     */
    public function getWorkflowClassName($definition)
    {
        return $this->getClassName($definition, 'workflow', false);
    }

    /**
     * Get the full class name from a definition.
     *
     * @param string $definition  Last part of class name without postfix or full class name
     * @param string $type        "workflow" or "task" currently
     * @param bool   $postfixType Whether $type is required as postfix at the class name
     *
     * @return string
     */
    protected function getClassName($definition, $type, $postfixType = true)
    {
        $ucType = ucfirst($type);
        if (!strpos($definition, '\\')) {
            $taskClass = str_replace(' ', '\\', ucwords(str_replace('-', ' ', $definition)));
            $taskClass = $taskClass.($postfixType ? $ucType : '');
            foreach ($this->namespaces[$type] as $namespace) {
                $potentialClass = '\\'.$namespace.'\\'.$taskClass;
                if (class_exists($potentialClass)) {
                    return $potentialClass;
                }
            }
        } elseif (!is_subclass_of(ltrim($definition, '\\'), 'Netresearch\\Kite\\'.$ucType)) {
            throw new Exception($definition.' must extend Netresearch\\Kite\\'.$ucType);
        }

        return $definition;
    }

    /**
     * Get the namespaces - either for a specific type or all.
     *
     * @param string|null $type workflow or tasks
     *
     * @return array
     */
    public function getNamespaces($type = null)
    {
        return $type ? $this->namespaces[$type] : $this->namespaces;
    }
}
