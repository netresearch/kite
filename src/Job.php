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


use Netresearch\Kite\Service\Console;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * Job - the outermost task object
 *
 * @category Netresearch
 * @package  Netresearch\Kite
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */
class Job extends Tasks
{
    /**
     * @var array
     */
    protected $definitions = array();

    /**
     * @var bool
     */
    protected $dryRun = false;

    /**
     * @var bool
     */
    private $started = false;

    /**
     * @var bool
     */
    private $initialized = false;

    /**
     * Job constructor.
     *
     * @param Console $console Console
     */
    public function __construct(Console $console)
    {
        static $kite, $composer;

        $this->console = $console;

        $this->offsetSet('job', $this);
        $this->offsetSet('config', $console->getConfig());
        if (!$kite) {
            $kite = array(
                'path' => $path = dirname(__DIR__),
                'dir' => $console->getFilesystem()->findShortestPath(getcwd(), $path)
            );
        }
        $this->offsetSet('kite', $kite);

        parent::__construct($this);

        if (!$composer) {
            $composer = $this->factory->createTask('Netresearch\\Kite\\Service\\Composer', $this);
        }

        $this->offsetSet('composer', $composer);
    }

    /**
     * Run an array of tasks
     *
     * @return $this
     */
    public function run()
    {
        $this->started = true;

        $this->console->getFilesystem()->ensureDirectoryExists($this->expand('{config["workspace"]}'));

        $input = $this->console->getInput();
        $this->dryRun = $input->getOption('dry-run');
        foreach ($this->definitions as $from => $info) {
            $config = $info['config'];
            if ($info['type'] === 'option') {
                if ($input->hasOption($from)) {
                    $value = $input->getOption($from);
                    if (($value === null || is_array($value) && !isset($value[0]) && count($value) <= 1)
                        && preg_match('/(^|\|)bool(ean)?($|\|)/', $config['type']) && strpos($config['type'], '|')
                    ) {
                        if ($input->hasParameterOption($opt = '--' . $from)
                            || array_key_exists('shortcut', $config)
                            && $input->hasParameterOption($opt = '-' . $config['shortcut'])
                        ) {
                            // Set value to true, when option has no value
                            $value = true;
                        }
                    }
                    $info['context']->set($info['variable'], $value);
                }
            } elseif ($input->hasArgument($from)) {
                $info['context']->set($info['variable'], $input->getArgument($from));
            }
        }

        return parent::run();
    }

    /**
     * Override get the input options and arguments for the JobCommand
     *
     * @param \Netresearch\Kite\Task $task The task
     *
     * @return $this|mixed $this or the task return value when this is running
     */
    public function addTask(Task $task)
    {
        if (!$this->started) {
            $this->addVariablesFromTask($task);
        }

        return parent::addTask($task);
    }

    /**
     * Add variables from the task to the job
     *
     * @param Task      $task             The task
     * @param Variables $context          Context to set the variable to, when job is run.
     *                                    When null, variable is set on task
     * @param bool|null $overrideExisting Whether to override existing args (true),
     *                                    don't override them (false) or throw an
     *                                    exception (null)
     *
     * @return void
     */
    public function addVariablesFromTask(Task $task, $context = null, $overrideExisting = null)
    {
        if ($task instanceof Job) {
            foreach ($task->definitions as $from => $definition) {
                if ($context) {
                    $definition['context'] = $context;
                }
                if (!array_key_exists($from, $this->definitions)) {
                    $this->definitions[$from] = $definition;
                }
            }
            return;
        }

        $context = $context ?: $task;
        foreach ($task->get('_variableConfiguration') as $variable => $config) {
            if (!is_array($config)) {
                continue;
            }
            $option = array_key_exists('option', $config) && $config['option'];
            $argument = array_key_exists('argument', $config) && $config['argument'];
            if ($argument && $option) {
                throw new Exception('Variable can not be option and argument at the same time');
            }
            if ($argument || $option) {
                $setting = $argument ?: $option;
                $from = is_string($setting) ? $setting : $this->camelCaseToLowerCaseDashed($variable);
                if (array_key_exists($from, $this->definitions)) {
                    if ($overrideExisting === null) {
                        throw new Exception('Argument/option definitions must be unique');
                    } elseif ($overrideExisting === false) {
                        continue;
                    }
                }
                $this->definitions[$from] = array(
                    'context' => $context,
                    'variable' => $variable,
                    'type' => $option ? 'option' : 'argument',
                    'config' => $config
                );
            }
        }
    }

    /**
     * camelCase to lower-case-dashed
     *
     * @param string $string String
     *
     * @return string
     */
    protected function camelCaseToLowerCaseDashed($string)
    {
        $string = preg_replace('/(?<=\\w)([A-Z])/', '-\\1', $string);

        // Converts string to lowercase
        // The function converts all Latin characters (A-Z, but no accents, etc) to
        // lowercase. It is safe for all supported character sets (incl. utf-8).
        // Unlike strtolower() it does not honour the locale.
        return strtr($string, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /**
     * Add the retrieved options and arguments to a definition
     *
     * @return InputDefinition
     */
    public function getDefinition()
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        $definition = new InputDefinition();

        foreach ($this->definitions as $from => $info) {
            $config = $info['config'];
            $required = array_key_exists('required', $config) && $config['required'];
            if ($info['type'] === 'option') {
                $mode = array_key_exists('mode', $config) ? $config['mode'] : InputOption::VALUE_OPTIONAL;
                if ($required) {
                    $mode |= InputOption::VALUE_REQUIRED;
                }
                if (in_array('array', explode('|', $config['type']), true)) {
                    $mode |= InputOption::VALUE_IS_ARRAY;
                }
                if (preg_match('/(^|\|)bool(ean)?($|\|)/', $config['type'])) {
                    if (strpos($config['type'], '|')) {
                        $mode |= InputOption::VALUE_NONE;
                    } else {
                        $mode = InputOption::VALUE_NONE;
                    }
                }
                $definition->addOption(
                    new InputOption(
                        $from,
                        array_key_exists('shortcut', $config) ? $config['shortcut'] : null,
                        $mode,
                        $config['label'],
                        array_key_exists('default', $config) ? $config['default'] : null
                    )
                );
            } else {
                $mode = array_key_exists('mode', $config) ? $config['mode'] : InputArgument::OPTIONAL;
                if ($required) {
                    $mode |= InputArgument::REQUIRED;
                }
                if (in_array('array', explode('|', $config['type']), true)) {
                    $mode |= InputArgument::IS_ARRAY;
                }
                $definition->addArgument(
                    new InputArgument($from, $mode, $config['label'], array_key_exists('default', $config) ? $config['default'] : null)
                );
            }
        }

        return $definition;
    }

    /**
     * Determine wether this job is dry run
     *
     * @return boolean
     */
    public function isDryRun()
    {
        return $this->dryRun;
    }
}
?>
