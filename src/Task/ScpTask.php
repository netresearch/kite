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
use Netresearch\Kite\Node;

/**
 * Up/download via SCP.
 *
 * @category   Netresearch
 *
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 *
 * @link       http://www.netresearch.de
 */
class ScpTask extends \Netresearch\Kite\Task\RemoteShellTask
{
    /**
     * Configure the options.
     *
     * @return array
     */
    protected function configureVariables()
    {
        return [
            'from' => [
                'type'     => 'string',
                'required' => true,
                'label'    => 'Path to the source (prefix with {node}: to download from a node)',
            ],
            'to' => [
                'type'     => 'string',
                'required' => true,
                'label'    => 'Path to the target (prefix with {node}: to upload to a node)',
            ],
            'options'   => null,
            'arguments' => null,
            'optArg'    => null,
            '--',
        ] + parent::configureVariables();
    }

    /**
     * Create all necessary commands.
     *
     * @return array|string
     */
    protected function getCommand()
    {
        $to = $this->get('to');
        $from = $this->get('from');

        $toNode = $this->getNodeFromPath($to);
        $fromNode = $this->getNodeFromPath($from);
        $node = $toNode ?: $fromNode;

        if (!$node) {
            throw new Exception('This task is intended to be used with nodes - use shell task for other sources/targets');
        } elseif ($toNode === $fromNode) {
            throw new Exception('Transfer on same node not supported');
        } elseif ($toNode && $fromNode && $toNode !== $fromNode) {
            throw new Exception('Transfer between nodes not (yet) supported');
        }

        $cwd = $this->expand($this->cwd);
        $cwdCmd = $cwd ? 'cd '.escapeshellarg($cwd).'; ' : null;
        $toDirParent = dirname(array_pop(explode(':', $to, 2)));
        if ($toDirParent !== '/') {
            $prepareDirCmd = 'mkdir -p '.escapeshellarg($toDirParent);
            if ($toNode) {
                $prepareDirCmd = $this->getSshCommand($prepareDirCmd, $toNode);
            } elseif ($cwd && $toDirParent[0] !== '/') {
                $prepareDirCmd = $cwdCmd.$prepareDirCmd;
            }
        }

        $scpCommand = $this->getScpCommand($node).' '.escapeshellarg($from).' '.escapeshellarg($to);

        if ($node) {
            $this->addExpect($scpCommand, $node);
        }

        if ($cwdCmd) {
            $scpCommand = $cwdCmd.$scpCommand;
        }

        return isset($prepareDirCmd) ? [$prepareDirCmd, $scpCommand] : $scpCommand;
    }

    /**
     * Create the scp command.
     *
     * @param Node $node The node
     *
     * @return string
     */
    protected function getScpCommand(Node $node)
    {
        return rtrim('scp -r '.trim($node->get('scpOptions')));
    }

    /**
     * Get the node from a path (by it's url).
     *
     * @param string $path The path
     *
     * @return Node
     */
    protected function getNodeFromPath($path)
    {
        if (strpos($path, ':')) {
            /* @var Node[] $nodes */
            $nodes = $this->get('nodes', []);
            $url = array_shift(explode(':', $path, 2));
            foreach ($nodes as $node) {
                if ($node->get('url') === $url) {
                    return $node;
                }
            }
        }
    }
}
