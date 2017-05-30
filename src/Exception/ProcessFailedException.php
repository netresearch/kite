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

namespace Netresearch\Kite\Exception;

use Netresearch\Kite\Exception;
use Netresearch\Kite\Service\Process;

/**
 * Class ProcessFailedException.
 *
 * @category Netresearch
 *
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 *
 * @link     http://www.netresearch.de
 */
class ProcessFailedException extends Exception
{
    /**
     * @var Process
     */
    protected $process;

    /**
     * ProcessFailedException constructor.
     *
     * @param Process $process The process
     */
    public function __construct(Process $process)
    {
        if ($process->isSuccessful()) {
            throw new Exception('Expected a failed process, but the given process was successful.');
        }

        $error = sprintf(
            'The command "%s" failed.'."\n\nExit Code: %s(%s)\n\nWorking directory: %s",
            $process->getCommandLine(),
            $process->getExitCode(),
            $process->getExitCodeText(),
            $process->getWorkingDirectory()
        );

        if (!$process->isOutputDisabled()) {
            $error .= sprintf(
                "\n\nOutput:\n================\n%s\n\nError Output:\n================\n%s",
                $process->getOutput(),
                $process->getErrorOutput()
            );
        }

        parent::__construct($error);

        $this->process = $process;
    }

    /**
     * Get the process.
     *
     * @return Process
     */
    public function getProcess()
    {
        return $this->process;
    }
}
