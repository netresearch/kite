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

/**
 * Run a composer command.
 *
 * @category   Netresearch
 *
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 *
 * @link       http://www.netresearch.de
 */
class ComposerTask extends ShellTask
{
    /**
     * Configure the options.
     *
     * @return array
     */
    protected function configureVariables()
    {
        return [
            // Override to set pt to true by default
            'processSettings' => [
                'type'    => 'array',
                'default' => ['pt' => true],
                'label'   => 'Settings for symfony process class',
            ],
            '--',
        ] + parent::configureVariables();
    }

    /**
     * Override to infer command to composerCommand.
     *
     * @param string $name  The name
     * @param mixed  $value The value
     *
     * @return $this
     */
    public function offsetSet($name, $value)
    {
        if ($name === 'command') {
            parent::offsetSet('composerCommand', $value);
            $value = 'composer '.$value;
        }
        parent::offsetSet($name, $value);
    }

    /**
     * Run the task.
     *
     * @return mixed
     */
    public function run()
    {
        $this->preview();
        if ($this->shouldExecute() && in_array($this->get('composerCommand'), ['update', 'install'], true)) {
            $this->expand('{composer.invalidatePackages()}');
        }

        return $this->execute();
    }

    /**
     * Get the command with options and arguments.
     *
     * @return string
     */
    protected function getCommand()
    {
        $cmd = $this->get('command');
        $argv = substr(parent::getCommand(), strlen($cmd)).' ';
        if (strpos($argv, ' --no-ansi ') === false
            && strpos($argv, ' --ansi ') === false
            && $this->console->getOutput()->isDecorated()
        ) {
            $argv = ' --ansi'.$argv;
        }
        if (!$this->shouldExecute()
            && strpos($argv, ' --dry-run ') === false
        ) {
            $argv = ' --dry-run'.$argv;
        }

        return $cmd.rtrim($argv);
    }
}
