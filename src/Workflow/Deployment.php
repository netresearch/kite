<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Workflow
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */

namespace Netresearch\Kite\Workflow;
use Netresearch\Kite\Task;

use Netresearch\Kite\Workflow;
use Netresearch\Kite\Exception;

use Symfony\Component\Console\Input\InputOption;

/**
 * Deploy the current application to a certain stage
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Workflow
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */
class Deployment extends Workflow
{
    /**
     * @var string Current release name
     */
    protected $release;

    /**
     * Configures the arguments/options
     *
     * @return array
     */
    protected function configureVariables()
    {
        return array(
            'rollback' => array(
                'type' => 'bool',
                'label' => 'Makes previous release current and current release next',
                'option' => true,
                'mode' => InputOption::VALUE_NONE,
                'shortcut' => 'r'
            ),
            'activate' => array(
                'type' => 'bool',
                'label' => 'Makes next release current and current release previous',
                'option' => true,
                'shortcut' => 'a',
                'mode' => InputOption::VALUE_NONE,
            ),
            'rsync' => array(
                'type' => 'array',
                'label' => 'Options for the rsync task (can contain keys options, exclude, and include - see rsync task for their descriptions)'
            ),
            'shared' => array(
                'type' => 'array',
                'label' => 'Array of files (in key "files") and directories (in key "dirs") to share between releases - share directory is in node.deployDir/shared',
                'default' => array()
            ),
            '--'
        ) + parent::configureVariables();
    }

    /**
     * Assemble this workflow
     *
     * @return void
     */
    public function assemble()
    {
        $rollback = $this->get('rollback');
        $restore = $this->get('activate');

        if (!$rollback && !$restore) {
            $this->checkout();
            $this->release = date($this->get('releaseFormat', 'YmdHis'));
            $this->set('releaseDir', 'releases/' . $this->release);
            $this->release();
            $this->shareResources();
        }
        if ($rollback) {
            $this->rollback();
        } else {
            $this->activate();
        }
    }

    /**
     * Checkout (forwards branch and merge in the stage configuration)
     *
     * @return \Netresearch\Kite\Workflow\Composer\Checkout
     */
    protected function checkout()
    {
        if (!$this->get('job.initialBranch', null)) {
            $this->set('job.initialBranch', $this->get('composer.rootPackage.branch'));

            // Assert a clean state and a valid lock...
            $this->sub('Netresearch\Kite\Workflow\Composer\Diagnose')
                ->message('<step>Diagnosing package states</step>')
                ->set('fix', true);

            // ...then backup this lock...
            $this->fs()->copy('composer.lock', '{config["workspace"]}/composer.lock.tmp');

            // ...restore it at the end of the job and install the clean state again
            $this->after('@all')->restoreInitialState()->force();
        } elseif ($this->get('merge', false)) {
            // We want to merge the initial branch and not the previously checked out
            $this->restoreInitialState();
        }

        return $this->sub(
            'Netresearch\\Kite\\Workflow\\Composer\\Checkout',
            array(
                'branch' => $this->get('branch', null),
                'merge' => $this->get('merge', false),
                'create' => $this->get('createBranch', false)
            )
        );
    }

    /**
     * Check out initial branch and install the state before the first checkout
     *
     * @return Task\SubTask
     */
    protected function restoreInitialState()
    {
        $cleanup = $this->sub()
            ->message('<step>Restoring initial state</step>')
            ->when('job.initialBranch != composer.rootPackage.branch');

        $cleanup->git('checkout', null, '{job.initialBranch}');
        $cleanup->fs()->copy('{config["workspace"]}/composer.lock.tmp', 'composer.lock');
        $cleanup->composer('install');

        return $cleanup;
    }

    /**
     * Create the next release from the current code base
     *
     * @return \Netresearch\Kite\Task\SubTask
     */
    protected function release()
    {
        $sub = $this->sub();
        $sub->message("<step>Preparing release <comment>{$this->release}</comment></step>");

        // Assert required directory structure
        $sub->remoteShell('mkdir -p {node.deployPath}/{releaseDir}');

        $sub->remoteShell(
            'if [ -h current ]; then rsync --recursive --links `readlink current`/ {releaseDir}; fi',
            '{node.deployPath}'
        );

        $sub->remoteShell('if [ -h next ]; then rm -rf `readlink next` next; fi', '{node.deployPath}');
        $sub->remoteShell('ln -s {releaseDir} next;', '{node.deployPath}');

        $sub->output('<step>Synchronizing sources</step>');
        $sub->rsync(
            '.', '{node}:{node.deployPath}/next',
            $this->get('rsync.options', array()),
            $this->get('rsync.exclude', array()),
            $this->get('rsync.include', array())
        );

        return $sub;
    }

    /**
     * Activate the next release
     *
     * @return \Netresearch\Kite\Task\SubTask
     */
    protected function activate()
    {
        $sub = $this->iterate('{nodes}', 'node');
        $sub->message('<step>Activating ' . ($this->release ? 'new' : 'latest') . ' release</step>');
        $sub->callback(
            function (Task\IterateTask $iterator) {
                $links = $iterator->remoteShell('echo "`readlink previous`;`readlink current`;`readlink next`"', '{node.deployPath}');
                list($previous, $current, $next) = explode(';', $links);

                if (!$next) {
                    $iterator->doBreak('<warning>Could not find next release on {node}</warning> - aborting');
                } else {
                    $nextRelease = basename($next);
                    if (!$this->release) {
                        $this->release = $nextRelease;
                    } elseif ($nextRelease !== $this->release) {
                        $iterator->doBreak("<warning>Next release on {node} is $nextRelease and not {$this->release} as expected</warning> - aborting");
                    }

                    $commands = array("ln -sfn $next current; rm next");
                    if ($current) {
                        $from = '<comment>' . basename($current) . '</comment>';
                        array_unshift($commands, "ln -s $current previous");
                        if ($previous) {
                            array_unshift($commands, "rm previous; rm -rf $previous");
                        }
                    } else {
                        $from = '<warning>none</warning>';
                    }

                    $iterator->output("<comment>{node}</comment>: $from -> <comment>$nextRelease</comment>");

                    $iterator->remoteShell($commands, '{node.deployPath}');
                }
            }
        );
    }

    /**
     * Rollback to the previous release (makes current next again)
     *
     * In general this is as easy as:
     *
     * <code>
     * $this->remoteShell('if [ -h current ] && [ -h previous ]; then ln -sfn `readlink current` next; fi', '{node.deployPath}');
     * $this->remoteShell('if [ -h previous ]; then ln -sfn `readlink previous` current; rm previous; fi', '{node.deployPath}');
     * </code>
     *
     * but we want to output which release was switched to which on each node,
     * thus the code is a little more complex
     *
     * @return \Netresearch\Kite\Task\SubTask
     */
    protected function rollback()
    {
        $firstPreviousRelease = null;

        $sub = $this->iterate('{nodes}', 'node');
        $sub->message('<step>Restoring previous release</step>');
        $sub->callback(
            function (Task\IterateTask $iterator) use (&$firstPreviousRelease) {
                $links = $iterator->remoteShell('echo "`readlink previous`;`readlink current`;`readlink next`"', '{node.deployPath}');
                list($previous, $current, $next) = explode(';', $links);
                if (!$previous) {
                    $this->doBreak('<warning>Could not find previous release on {node}</warning> - aborting');
                } else {
                    $previousRelease = basename($previous);

                    if (!$firstPreviousRelease) {
                        $firstPreviousRelease = $previousRelease;
                    } elseif ($previousRelease !== $firstPreviousRelease) {
                        $iterator->doBreak("<warning>Previous release on {node} is $previousRelease and not $firstPreviousRelease as on the previous node(s)</warning> - aborting");
                    }

                    $commands = array("ln -sfn $previous current; rm previous");
                    if ($current) {
                        array_unshift($commands, "ln -s $current next");
                        $from = '<comment>' . basename($current) . '</comment>';
                    } else {
                        $from = '<warning>none</warning>';
                    }

                    $iterator->output("<comment>{node}</comment>: $from -> <comment>$previousRelease</comment>");

                    $iterator->remoteShell($commands, '{node.deployPath}');
                }
            }
        );
    }

    /**
     * Setup shared resources
     *
     * @return void
     */
    protected function shareResources()
    {
        $sub = $this->iterate('{shared}', array('type' => 'entries'));
        $sub->message('<step>Linking shared resources</step>');
        $sub->callback(
            function (Task\IterateTask $iterator) {
                $type = $iterator->get('type');
                if (!in_array($type, array('dirs', 'files'), true)) {
                    $iterator->doExit('shared may only contain keys "dirs" or "files"', 1);
                }
                $isFile = substr($type, 0, 4) === 'file';
                $entries = (array) $iterator->get('entries');
                $shareDir = 'shared';
                foreach ($entries as $entry) {
                    $dirName = strpos($entry, '/') !== false ? dirname($entry) : null;
                    $subDirCount = substr_count($this->get('releaseDir'), '/') + 1;
                    $commands = array();
                    if ($isFile) {
                        $commands[] = "if [ ! -f $shareDir/$entry ]; then mkdir -p $shareDir/$dirName; touch $shareDir/$entry; fi";
                    } else {
                        $commands[] = "mkdir -p $shareDir/$entry";
                    }

                    $commands[] = 'cd {releaseDir}';
                    $commands[] = 'rm -rf ' . $entry;
                    if ($dirName) {
                        $commands[] = 'mkdir -p ' . $dirName;
                        $commands[] = 'cd ' . $dirName;
                        $subDirCount += substr_count($dirName, '/') + 1;
                    }
                    $commands[] = 'ln -s ' . str_repeat('../', $subDirCount) . $shareDir . '/' . $entry;

                    $iterator->remoteShell($commands, '{node.deployPath}');
                }
            }
        );
    }
}
?>
