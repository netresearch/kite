<?php
/**
 * See class comment.
 *
 * PHP Version 5
 *
 * @category Netresearch
 *
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 *
 * @link     http://www.netresearch.de
 */

namespace Netresearch\Kite;

use Netresearch\Kite\Exception\BreakException;
use Netresearch\Kite\Exception\ExitException;
use Netresearch\Kite\Exception\ForcedTaskException;
use Netresearch\Kite\Task\SchemaMigrationTask;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A task to aggregate and run a bunch of tasks.
 *
 * @category Netresearch
 *
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 *
 * @link     http://www.netresearch.de
 */
abstract class Tasks extends Task
{
    /**
     * @var \Netresearch\Kite\Service\Factory
     */
    protected $factory;

    /**
     * @var bool|array
     */
    private $isPrepare;

    /**
     * @var \Netresearch\Kite\Task[]
     */
    private $tasks = [];

    /**
     * @var array
     */
    private $deferredTasks = [];

    /**
     * @var bool
     */
    private $started = false;

    /**
     * @var bool
     */
    private $initialized = false;

    /**
     * @var bool
     */
    private $isWorkflow = false;

    /**
     * Tasks constructor.
     *
     * @param Variables $parent Parent object (Task/Job/Workflow)
     */
    public function __construct(Variables $parent)
    {
        parent::__construct($parent);
        $this->factory = $this->console->getFactory();
    }

    /**
     * Clone and reparent the tasks to $this.
     *
     * @return void
     */
    public function __clone()
    {
        // Clone the tasks and bind (reparent) them to $this
        $tasks = [];
        foreach ($this->tasks as $task) {
            $tasks[] = $clone = clone $task;
            $clone->bindTo($this);
        }
        $this->tasks = $tasks;

        // Do this at last because otherwise tasks will be doubly cloned as they
        // are in both, Variables::$children and Tasks::$tasks
        // After the previous reparenting there should only be deferred tasks be
        // left in Variables::$children
        parent::__clone();
    }

    /**
     * Configures the available options.
     *
     * @return array
     */
    protected function configureVariables()
    {
        return [
            'tasks' => [
                'type'  => 'array',
                'label' => 'Array of tasks to add to the subTask',
            ],
            'task' => [
                'type'  => 'mixed',
                'label' => 'Task to run as a sub task',
            ],
            'workflow' => [
                'type'  => 'array',
                'label' => 'Workflow to run as a subtask',
            ],
            'script' => [
                'type'  => 'string',
                'label' => 'Script to include which configures the tasks',
            ],
            '--',
        ] + parent::configureVariables();
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

        $this->isWorkflow = false;
        foreach ($this->tasks as $task) {
            $task->initialize();
        }
        $this->initialized = true;
    }

    /**
     * Override to create the tasks from the according options.
     *
     * @param string $name  Variable name
     * @param mixed  $value Variable value
     *
     * @return void
     */
    public function offsetSet($name, $value)
    {
        if ($this->isWorkflow) {
            // Forward everything to the workflow, as soon as it is set
            $this->tasks[0]->offsetSet($name, $value);

            return;
        }
        if (in_array($name, ['workflow', 'script', 'task', 'tasks'], true)) {
            if ($this->tasks) {
                throw new Exception('Can either use workflow, script, task or tasks');
            }
            $value = $this->expand($value);
        }
        if ($name === 'workflow') {
            $this->addTask($this->factory->createWorkflow($value, $this));
            $this->isWorkflow = true;

            return;
        }
        if ($name === 'script') {
            include $value;

            return;
        }
        if ($name === 'task') {
            $name = 'tasks';
            $value = [$value];
        }
        if ($name === 'tasks') {
            foreach ($value as $name => $task) {
                $task = $this->expand($task);
                $options = [];
                if (!is_numeric($name)) {
                    $options['name'] = $name;
                }
                $this->addTask($this->factory->createTask($task, $this, $options));
            }

            return;
        }
        parent::offsetSet($name, $value);
    }

    /**
     * Run an array of tasks.
     *
     * @return $this
     */
    public function run()
    {
        if ($this->started) {
            throw new Exception('Task was already started');
        }

        $this->started = true;

        $exception = null;

        $this->preview();

        $isMain = $this === $this->job;
        $indent = !$isMain && $this->getParent() !== $this->job ? 1 : 0;
        $this->console->indent($indent);

        $tasks = $this->tasks;
        $name = $this->get('name');
        $this->addDeferredTasks($tasks, 'before', $name);
        if ($isMain) {
            $this->addDeferredTasks($tasks, 'before', '@all');
        }
        do {
            do {
                while ($task = array_shift($tasks)) {
                    $taskName = $task->get('name', null);
                    if ($taskName) {
                        array_unshift($tasks, $task);
                        $this->addDeferredTasks($tasks, 'before', $taskName);
                        $task = array_shift($tasks);
                    }
                    if (!$exception) {
                        try {
                            $this->runTask($task);
                        } catch (ForcedTaskException $e) {
                            throw $e;
                        } catch (\Exception $e) {
                            $exception = $e;
                        }
                    } elseif ($task->has('force') && $task->get('force')) {
                        try {
                            $this->runTask($task);
                        } catch (\Exception $e) {
                            throw new ForcedTaskException($e->getMessage(), $e->getCode(), $exception);
                        }
                    }
                    if ($taskName) {
                        $this->addDeferredTasks($tasks, 'after', $taskName);
                    }
                }
            } while ($this->addDeferredTasks($tasks, 'after', $name));
        } while ($isMain && $this->addDeferredTasks($tasks, 'after', '@all'));

        $this->console->outdent($indent);

        if ($exception) {
            if ($exception instanceof BreakException) {
                $message = $exception->getMessage();
                if ($message) {
                    $this->console->output($task->expand($message));
                }
            } else {
                throw $exception;
            }
        }

        return $this;
    }

    /**
     * Adds tasks that should be run before/after the task with $name.
     *
     * @param array  $tasks The tasks to add the deferred tasks to
     * @param string $type  "before" or "after"
     * @param string $name  The task name
     *
     * @return int
     */
    private function addDeferredTasks(&$tasks, $type, $name)
    {
        if (!$name) {
            return 0;
        }
        $key = $type.':'.$name;
        $tasksAdded = 0;
        if (array_key_exists($key, $this->job->deferredTasks)) {
            while ($task = array_shift($this->job->deferredTasks[$key])) {
                $tasksAdded++;
                $type == ($type === 'after') ? array_push($tasks, $task) : array_unshift($tasks, $task);
            }
        }

        return $tasksAdded;
    }

    /**
     * Run a task.
     *
     * @param Task $task The task
     *
     * @internal Use addTask unless you know what you're doing
     *
     * @return mixed|null The task return value or null when if failed or dry run
     */
    protected function runTask(Task $task)
    {
        if ($if = $task->get('if')) {
            if (is_bool($if)) {
                $shouldRun = $if;
            } elseif (is_string($if)) {
                $shouldRun = (bool) $task->expand(
                    '{'.$if.' ? 1 : 0}'
                );
            } else {
                $shouldRun = call_user_func_array($if, [$task, $this->console]);
                if (!is_bool($shouldRun)) {
                    throw new Exception('Callback must return TRUE or FALSE');
                }
            }
        } else {
            $shouldRun = true;
        }
        if ($shouldRun) {
            $return = $task->run();
            $toVar = $task->get('toVar', null);
            if ($toVar) {
                $task->getParent()->set($toVar, $return);
            }

            return $return;
        }
    }

    /**
     * Add a task - or run it immediately when $this->started.
     *
     * @param Task $task The task
     *
     * @return $this|mixed $this or the task return value when this is running
     */
    public function addTask(Task $task)
    {
        $deferred = false;
        foreach (['before', 'after'] as $type) {
            if ($task->get($type)) {
                $deferred = true;
                foreach ((array) $task->get($type) as $name) {
                    if ($name === '@self') {
                        $name = $this->get('name');
                    }
                    $key = $type.':'.$name;
                    $this->job->deferredTasks[$key][] = $task;
                }
            }
        }

        if ($this->initialized || $deferred) {
            $task->initialize();
        }

        if (!$deferred && $this->started) {
            return $this->runTask($task);
        }

        if (!$deferred) {
            $this->tasks[] = $task;
        }

        return $this;
    }

    /**
     * Setup and add (which eventually runs) a task.
     *
     * @param string|array $type    The task type or configuration
     * @param array        $options The options
     *
     * @return \Netresearch\Kite\Task|mixed The task or the task return when !$this->prepare
     */
    private function createAndAddTask($type, array $options = [])
    {
        $task = $this->factory->createTask($type, $this, $options);
        if (is_array($this->isPrepare)) {
            $beforeOrAfter = $this->isPrepare[0];
            $beforeOrAfterTask = $this->isPrepare[1];
            if ($beforeOrAfterTask instanceof Task) {
                $beforeOrAfterTask = $beforeOrAfterTask->get('name');
            }
            $task->set($beforeOrAfter, $beforeOrAfterTask);
            $this->isPrepare = false;
        }
        if ($this->isPrepare === true) {
            $this->isPrepare = false;

            return $task;
        } else {
            $return = $this->addTask($task);
        }

        return $this->started ? $return : $task;
    }

    /**
     * Makes that the next fetched task (from the methods below)
     * is not added to the workflow.
     *
     * @return $this
     */
    public function prepare()
    {
        $this->isPrepare = true;

        return $this;
    }

    /**
     * Execute the next fetched task before given $task.
     *
     * @param Task|string $task Task or task name
     *
     * @return $this
     */
    public function before($task)
    {
        $this->isPrepare = ['before', $task];

        return $this;
    }

    /**
     * Execute the next fetched task after given $task.
     *
     * @param Task|string $task Task or task name
     *
     * @return $this
     */
    public function after($task)
    {
        $this->isPrepare = ['after', $task];

        return $this;
    }

    // Following are shortcuts to create and eventually run tasks

    /**
     * Answer a question.
     *
     * @param string $question The question
     * @param string $default  The default value
     *
     * @return mixed|\Netresearch\Kite\Task
     */
    public function answer($question, $default = null)
    {
        return $this->createAndAddTask(__FUNCTION__, compact('question', 'default'));
    }

    /**
     * Run a callback.
     *
     * @param callable $callback The callback
     *
     * @return mixed|\Netresearch\Kite\Task\CallbackTask
     */
    public function callback($callback)
    {
        return $this->createAndAddTask(__FUNCTION__, compact('callback'));
    }

    /**
     * Ask a selection question.
     *
     * @param string $question The question
     * @param array  $choices  The choices
     * @param mixed  $default  Default value
     *
     * @return mixed|\Netresearch\Kite\Task\ChooseTask
     */
    public function choose($question, array $choices, $default = null)
    {
        return $this->createAndAddTask(__FUNCTION__, compact('question', 'choices', 'default'));
    }

    /**
     * Clear the TYPO3 cache.
     *
     * @param string $cmd The clearcache command
     *
     * @return null|\Netresearch\Kite\Task\IncludeTask
     */
    public function clearCache($cmd = null)
    {
        return $this->createAndAddTask(__FUNCTION__, $cmd ? compact('cmd') : []);
    }

    /**
     * Ask a confirmation question.
     *
     * @param string $question The question
     * @param bool   $default  Default value
     *
     * @return bool|\Netresearch\Kite\Task\ConfirmTask
     */
    public function confirm($question, $default = true)
    {
        return $this->createAndAddTask(__FUNCTION__, compact('question', 'default'));
    }

    /**
     * Run a composer command.
     *
     * @param string            $command         The command to execute
     * @param array|string|null $optArg          Options and arguments
     *                                           {@see \Netresearch\Kite\Task\ShellTask}
     * @param array             $processSettings Settings for symfony process class
     *
     * @return string|\Netresearch\Kite\Task\ComposerTask
     */
    public function composer($command, $optArg = null, array $processSettings = [])
    {
        return $this->createAndAddTask(__FUNCTION__, compact('command', 'optArg', 'processSettings'));
    }

    /**
     * Evaluate an expression.
     *
     * {@see http://symfony.com/doc/current/components/expression_language/syntax.html}
     *
     * @param string $expression The expression
     *
     * @return mixed|\Netresearch\Kite\Task\EvaluateTask
     */
    public function evaluate($expression)
    {
        return $this->createAndAddTask(__FUNCTION__, compact('expression'));
    }

    /**
     * Break a tasks loop.
     *
     * @param string $message The message
     *
     * @return void
     */
    public function doBreak($message = '')
    {
        throw new BreakException($message);
    }

    /**
     * Exit - return code is return code of application
     * (thus, when it's not 0 the message will be rendered as exception).
     *
     * @param string $message The message
     * @param int    $code    The code
     *
     * @return void
     */
    public function doExit($message = '', $code = 0)
    {
        throw new ExitException($message, $code);
    }

    /**
     * Run a git command.
     *
     * @param string            $command         The command to execute
     * @param string|null       $cwd             The directory to change into before execution
     * @param array|string|null $optArg          Options and arguments
     *                                           {@see \Netresearch\Kite\Task\ShellTask}
     * @param array             $processSettings Settings for symfony process class
     *
     * @return Task\GitTask|string
     */
    public function git($command, $cwd = null, $optArg = null, array $processSettings = [])
    {
        return $this->createAndAddTask(__FUNCTION__, compact('command', 'cwd', 'optArg', 'processSettings'));
    }

    /**
     * Run a workflow for each of the $array's values.
     *
     * @param array|\Traversable $array    The object to iterate over
     * @param string|array       $as       Either string for
     *                                     foreach ($array as $as)
     *                                     or array for
     *                                     foreach($array as key($as) => current($as))
     * @param string|null        $workflow Optional workflow class name
     *
     * @return array|\Netresearch\Kite\Task\IterateTask
     */
    public function iterate($array, $as, $workflow = null)
    {
        $options = compact('array', 'as');
        if ($workflow) {
            $options['workflow'] = $workflow;
        }

        return $this->createAndAddTask(__FUNCTION__, $options);
    }

    /**
     * Do something on the filesystem.
     *
     * @return \Netresearch\Kite\Task\FsTask
     */
    public function fs()
    {
        return $this->createAndAddTask(__FUNCTION__);
    }

    /**
     * Output a message.
     *
     * @param string   $message           The message
     * @param int|bool $severityOrNewLine Severity or whether to print a newline
     * @param bool     $newLine           Whether to print a newline
     *
     * @return mixed|\Netresearch\Kite\Task\OutputTask
     */
    public function output($message, $severityOrNewLine = OutputInterface::VERBOSITY_NORMAL, $newLine = true)
    {
        if (is_bool($severityOrNewLine)) {
            $newLine = $severityOrNewLine;
            $severity = OutputInterface::VERBOSITY_NORMAL;
        } else {
            $severity = $severityOrNewLine;
        }

        return $this->createAndAddTask(__FUNCTION__, compact('message', 'severity', 'newLine'));
    }

    /**
     * Execute a command remote.
     *
     * @param string            $command         The command to execute
     * @param string|null       $cwd             The directory to change into before execution
     * @param array|string|null $optArg          Options and arguments
     *                                           {@see \Netresearch\Kite\Task\ShellTask}
     * @param array             $processSettings Settings for symfony process class
     *
     * @return Task\RemoteShellTask|string
     */
    public function remoteShell($command, $cwd = null, $optArg = null, array $processSettings = [])
    {
        return $this->createAndAddTask(__FUNCTION__, compact('command', 'cwd', 'optArg', 'processSettings'));
    }

    /**
     * Rsync from/to somewhere - prefix $from/$to with {node}: to rsync from/to nodes.
     *
     * @param string $from    From
     * @param string $to      To
     * @param array  $options Options for rsync
     * @param array  $exclude Files/dirs to exclude
     * @param array  $include Files/dirs to explicitely include
     *
     * @return string|\Netresearch\Kite\Task\RsyncTask
     */
    public function rsync($from, $to, array $options = [], array $exclude = [], array $include = [])
    {
        return $this->createAndAddTask(__FUNCTION__, compact('from', 'to', 'options', 'exclude', 'include'));
    }

    /**
     * Migrate the TYPO3 schema definitions from ext_table.sql files.
     *
     * @return string|SchemaMigrationTask
     */
    public function schemaMigration()
    {
        return $this->createAndAddTask(__FUNCTION__);
    }

    /**
     * Upload a file via scp.
     *
     * @param string $from File to upload (prefix with {node}: to download)
     * @param string $to   Path to upload to (prefix with {node}: to upload)
     *
     * @return mixed|\Netresearch\Kite\Task
     */
    public function scp($from, $to)
    {
        return $this->createAndAddTask(__FUNCTION__, compact('from', 'to'));
    }

    /**
     * Execute a command locally.
     *
     * @param string            $command         The command to execute
     * @param string|null       $cwd             The directory to change into before execution
     * @param array|string|null $optArg          Options and arguments
     *                                           {@see \Netresearch\Kite\Task\ShellTask}
     * @param array             $processSettings Settings for symfony process class
     *
     * @return Task\ShellTask|string
     */
    public function shell($command, $cwd = null, $optArg = null, array $processSettings = [])
    {
        return $this->createAndAddTask(__FUNCTION__, compact('command', 'cwd', 'optArg', 'processSettings'));
    }

    /**
     * Run tasks as sub tasks.
     *
     * @param string|array $workflowOrOptions Options for the class factory or workflow class name
     * @param array        $workflowOptions   Options for the workflow (when $workflowOrOptions is string)
     *
     * @return Task\SubTask|Workflow
     */
    public function sub($workflowOrOptions = [], array $workflowOptions = [])
    {
        if (is_string($workflowOrOptions)) {
            return $this->createAndAddTask($workflowOrOptions, $workflowOptions);
        }

        return $this->createAndAddTask($workflowOrOptions);
    }

    /**
     * Run tasks as sub tasks and catch exceptions.
     *
     * @param string $errorMessage Error message to display on failure
     *
     * @return \Netresearch\Kite\Task\TryCatchTask
     */
    public function tryCatch($errorMessage = null)
    {
        return $this->createAndAddTask(__FUNCTION__, compact('errorMessage'));
    }

    /**
     * Create a Tar archive from the file or files in $file.
     *
     * @param string|array $files  File(s) to tar
     * @param string       $toFile Path to tar file
     *
     * @return mixed|\Netresearch\Kite\Task\TarTask
     */
    public function tar($files, $toFile)
    {
        return $this->createAndAddTask(__FUNCTION__, compact('files', 'toFile'));
    }
}
