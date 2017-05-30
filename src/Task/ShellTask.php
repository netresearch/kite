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

namespace Netresearch\Kite\Task;

use Netresearch\Kite\Exception;

/**
 * Executes a command locally and returns the output.
 *
 * @category   Netresearch
 *
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 *
 * @link       http://www.netresearch.de
 */
class ShellTask extends \Netresearch\Kite\Task
{
    /**
     * Configure the options.
     *
     * @return array
     */
    protected function configureVariables()
    {
        return [
            'command' => [
                'type'     => 'string|array',
                'label'    => 'Command(s) to execute',
                'required' => true,
            ],
            'cwd' => [
                'type'  => 'string',
                'label' => 'The directory to change to before running the command',
            ],
            'argv' => [
                'type'  => 'array|string',
                'label' => 'String with all options and arguments for the command or an array in the same format as $argv. '
                    .'Attention: Values won\'t be escaped!',
            ],
            'options' => [
                'type'    => 'array',
                'default' => [],
                'label'   => 'Array with options: Elements with numeric keys or bool true values will be --switches.',
            ],
            'arguments' => [
                'type'    => 'array',
                'default' => [],
                'label'   => 'Arguments to pass to the cmd',
            ],
            'optArg' => [
                'type'  => 'array|string',
                'label' => 'Arguments and options in one array. '
                    .'When array, elements with numeric keys will be added as {@see arguments} and elements with string keys will be added as {@see options}. '
                    .'When string, {@see argv} will be set to this value',
            ],
            'errorMessage' => [
                'type'  => 'string',
                'label' => 'Message to display when the command failed',
            ],
            'processSettings' => [
                'type'    => 'array',
                'default' => [],
                'label'   => 'Settings for symfony process class',
            ],
            '--',
        ] + parent::configureVariables();
    }

    /**
     * Handle arguments, options and optArg.
     *
     * @param string $option Option name
     * @param mixed  $value  Option value
     *
     * @return void
     */
    public function offsetSet($option, $value)
    {
        if ($option === 'processSettings') {
            $value = array_merge($this->offsetGet('processSettings'), $value);
        }
        if (in_array($option, ['arguments', 'options', 'optArg'], true)) {
            if ($value === null) {
                if ($option === 'optArg' || $option === 'options') {
                    parent::offsetSet('options', []);
                }
                if ($option === 'optArg' || $option === 'arguments') {
                    parent::offsetSet('arguments', []);
                }

                return;
            }
            if ($option === 'optArg' && is_string($value)) {
                parent::offsetSet('argv', $value);

                return;
            }
            $arguments = $this->get('arguments');
            $options = $this->get('options');
            if ($option == 'arguments') {
                $arguments = array_merge($arguments, $value);
            } elseif ($option == 'options') {
                foreach ($value as $k => $v) {
                    if (is_numeric($k)) {
                        $options[$v] = true;
                    } else {
                        $options[$k] = $v;
                    }
                }
            } elseif ($option == 'optArg') {
                foreach ($value as $k => $v) {
                    if (is_numeric($k)) {
                        $arguments[] = $v;
                    } else {
                        $options[$k] = $v;
                    }
                }
            }
            parent::offsetSet('arguments', $arguments);
            parent::offsetSet('options', $options);

            return;
        }
        parent::offsetSet($option, $value);
    }

    /**
     * Execute the command.
     *
     * @return mixed
     */
    protected function executeCommand()
    {
        $process = $this->console->createProcess($this->getCommand(), $this->get('cwd'));
        $process->setDryRun(!$this->shouldExecute());
        foreach ($this->get('processSettings') as $key => $value) {
            $process->{'set'.ucfirst($key)}($value);
        }

        return $process->run();
    }

    /**
     * Get the command with options and arguments.
     *
     * @return string
     */
    protected function getCommand()
    {
        $cmd = $this->get('command');
        $argv = $this->get('argv');
        $options = $this->get('options');
        $arguments = $this->get('arguments');
        if (is_array($cmd)) {
            if ($argv || $options || $arguments) {
                throw new Exception('Can not use argv, options or arguments on multiple commands');
            }
            $cmd = $this->expand(implode('; ', $cmd));
        } else {
            if ($argv) {
                if ($options || $arguments) {
                    throw new Exception('Can not combine argv with options or arguments');
                }
                $cmd .= ' '.(is_array($argv) ? implode(' ', $argv) : $argv);
            } else {
                $cmd .= $this->renderOptions($options);
                $cmd .= $this->renderArguments($arguments);
            }
        }

        return $cmd;
    }

    /**
     * Render options as options for the command.
     *
     * @param array $options The options
     *
     * @return string
     */
    protected function renderOptions(array $options)
    {
        $optionString = '';
        foreach ($options as $option => $value) {
            $value = $this->expand($value);
            if ($value === false) {
                continue;
            }
            $l = strlen($option);
            $optionString .= ' ';
            if ($option[0] !== '-') {
                $optionString .= ($l === 1) ? '-' : '--';
            }
            $optionString .= $option;
            if ($value !== true) {
                if ($l > 1 && ($option[0] !== '-' || $option[1] === '-')) {
                    $optionString .= '=';
                } else {
                    $optionString .= ' ';
                }
                $optionString .= escapeshellarg($value);
            }
        }

        return $optionString;
    }

    /**
     * Render arguments for the command.
     *
     * @param array $arguments The arguments
     *
     * @return string
     */
    protected function renderArguments(array $arguments)
    {
        $argumentString = '';
        foreach ($arguments as $argument) {
            $value = $this->expand($argument);
            $argumentString .= ' '.escapeshellarg($value);
        }

        return $argumentString;
    }

    /**
     * Run the command.
     *
     * @return string
     */
    public function run()
    {
        $this->preview();
        $res = $this->execute();

        return $res;
    }

    /**
     * Execute the command.
     *
     * @return string
     */
    public function execute()
    {
        return $this->executeCommand();
    }
}
