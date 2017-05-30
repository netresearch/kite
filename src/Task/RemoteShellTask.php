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

use Netresearch\Kite\Node;

/**
 * Execute a shell command on either the current node or all nodes.
 *
 * @category   Netresearch
 *
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 *
 * @link       http://www.netresearch.de
 */
class RemoteShellTask extends ShellTask
{
    /**
     * @var string
     */
    protected $cwd;

    /**
     * Hide the cwd from ShellTask.
     *
     * @param string $option Option
     * @param mixed  $value  Value
     *
     * @return void
     */
    public function offsetSet($option, $value)
    {
        if ($option === 'cwd') {
            $this->cwd = $value;

            return;
        }
        parent::offsetSet($option, $value);
    }

    /**
     * Execute the task for each of the available nodes or the current node if set.
     *
     * @return array|mixed Return value(s) of the command on the node(s)
     */
    public function execute()
    {
        if ($this->has('node')) {
            return $this->executeCommand();
        }

        /* @var \Netresearch\Kite\Node[] $nodes */
        $nodes = $this->get('nodes');
        if (!$nodes) {
            throw new \Netresearch\Kite\Exception('No nodes to work on');
        }
        $returnValues = [];
        foreach ($nodes as $node) {
            $this->set('node', $node);
            try {
                $returnValues[$node->get('id')] = $this->executeCommand();
            } catch (\Exception $e) {
                $this->remove('node');
                throw $e;
            }
        }
        $this->remove('node');

        return $returnValues;
    }

    /**
     * Get the command to execute.
     *
     * @return string
     */
    protected function getCommand()
    {
        return $this->getSshCommand(
            parent::getCommand(),
            $this->get('node'), $this->expand($this->cwd)
        );
    }

    /**
     * Wrap the command in a ssh command.
     *
     * @param string $command The command
     * @param Node   $node    The node
     * @param string $cwd     The dir to change into (on the node)
     *
     * @return string
     */
    protected function getSshCommand($command, Node $node, $cwd = null)
    {
        if ($cwd && !preg_match('#^cd\s+[\'"]?/#', $command)) {
            $command = 'cd '.escapeshellarg($cwd).'; '.$command;
        }

        $sshCommand
            = rtrim('ssh'.$node->get('sshOptions'))
            .' '.escapeshellarg($node->get('url'))
            .' '.escapeshellarg($command);

        $this->addExpect($sshCommand, $node);

        return $sshCommand;
    }

    /**
     * Wrap the command in an expect command.
     *
     * @param string $sshCommand The command
     * @param Node   $node       The node
     *
     * @return void
     */
    protected function addExpect(&$sshCommand, Node $node)
    {
        $pass = $node->get('pass');
        if ($pass) {
            $expectFile = dirname(dirname(__DIR__));
            $expectFile .= '/res/script/password.expect';
            $sshCommand = sprintf('expect %s %s %s', escapeshellarg($expectFile), escapeshellarg($pass), $sshCommand);
        }
    }
}
