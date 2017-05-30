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

use Netresearch\Kite\Task;

/**
 * Filesystem task - calls methods on {@see \Netresearch\Kite\Service\Filesystem}.
 *
 * @method Task|void delete($file) {@see \Netresearch\Kite\Service\Filesystem::remove()}
 * @method Task|bool isDirEmpty($dir) {@see \Netresearch\Kite\Service\Filesystem::isDirEmpty()}
 * @method Task|bool removeDirectory($directory) {@see \Netresearch\Kite\Service\Filesystem::removeDirectory()}
 * @method Task|bool removeDirectoryPhp($directory) {@see \Netresearch\Kite\Service\Filesystem::removeDirectoryPhp()}
 * @method Task|void ensureDirectoryExists($directory) {@see \Netresearch\Kite\Service\Filesystem::ensureDirectoryExists()}
 * @method Task|void copyThenRemove($source, $target) {@see \Netresearch\Kite\Service\Filesystem::copyThenRemove()}
 * @method Task|void copy($source, $target) {@see \Netresearch\Kite\Service\Filesystem::copy()}
 * @method Task|void rename($source, $target) {@see \Netresearch\Kite\Service\Filesystem::rename()}
 * @method Task|string findShortestPath($from, $to, $directories = false) {@see \Netresearch\Kite\Service\Filesystem::findShortestPath()}
 * @method Task|string findShortestPathCode($from, $to, $directories = false) {@see \Netresearch\Kite\Service\Filesystem::findShortestPathCode()}
 * @method Task|bool isAbsolutePath($path) {@see \Netresearch\Kite\Service\Filesystem::isAbsolutePath()}
 * @method Task|int size($path) {@see \Netresearch\Kite\Service\Filesystem::size()}
 * @method Task|string normalizePath($path) {@see \Netresearch\Kite\Service\Filesystem::normalizePath()}
 *
 * @category   Netresearch
 *
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 *
 * @link       http://www.netresearch.de
 */
class FsTask extends Task
{
    protected $started = false;

    /**
     * Configure the options.
     *
     * @return array
     */
    protected function configureVariables()
    {
        return [
            'action' => [
                'type'     => 'string',
                'required' => true,
                'label'    => 'Method of \Netresearch\Kite\Service\Filesystem to execute',
            ],
            'arguments' => [
                'type'    => 'array',
                'default' => [],
                'label'   => 'Arguments for action method',
            ],
        ] + parent::configureVariables();
    }

    /**
     * Call the action method or set it for later execution.
     *
     * @param string $name      The method name
     * @param array  $arguments The arguments
     *
     * @return $this|mixed
     */
    public function __call($name, $arguments)
    {
        if (!$this->started) {
            $this->offsetSet('action', $name);
            $this->offsetSet('arguments', $arguments);

            return $this;
        }

        foreach ($arguments as &$argument) {
            $argument = $this->expand($argument);
        }

        return call_user_func_array(
            [$this->console->getFilesystem(), $name],
            $arguments
        );
    }

    /**
     * Call the method or return $this for later method execution.
     *
     * @return $this|mixed
     */
    public function execute()
    {
        $this->started = true;

        if (!$this->offsetExists('action')) {
            // Likely called during execution and __call is still pending
            return $this;
        }

        return $this->__call($this->get('action'), $this->offsetGet('arguments'));
    }
}
