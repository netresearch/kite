<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Service
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */

namespace Netresearch\Kite\Service;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A shell command service
 * (taken from TYPO3\Surf\Domain\Service\ShellCommandService)
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Service
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */
class Process extends \Symfony\Component\Process\Process
{
    /**
     * @var \Netresearch\Kite\Service\Console
     */
    protected $console;

    /**
     * @var bool
     */
    protected $dryRun = false;

    /**
     * @var bool
     */
    protected $shy = false;

    /**
     * @var bool (passthrough) whether to pass command output through to console output
     */
    protected $pt = false;

    /**
     * Constructor.
     *
     * @param Console     $console The console
     * @param string      $command The command line to run
     * @param string|null $cwd     The working directory or null to use the
     *                             working dir of the current PHP process
     */
    public function __construct(Console $console, $command, $cwd = null)
    {
        $this->console = $console;

        if (is_string($command)) {
            $command = trim($command);
        } elseif (is_array($command)) {
            $command = implode('; ', $command);
        } else {
            throw new \Netresearch\Kite\Exception('Command must be string or array, ' . gettype($command) . ' given.', 1312454906);
        }

        parent::__construct($command, $cwd, null, null, null);
    }


    /**
     * Set dry run
     *
     * @param boolean $dryRun Dry run
     *
     * @return void
     */
    public function setDryRun($dryRun)
    {
        $this->dryRun = $dryRun;
    }

    /**
     * Get if output should not be logged to debug
     *
     * @return boolean
     */
    public function isShy()
    {
        return $this->shy;
    }

    /**
     * Set if output should not be logged to debug
     *
     * @param boolean $shy Shyness
     *
     * @return void
     */
    public function setShy($shy)
    {
        $this->shy = $shy;
    }

    /**
     * Get whether to pass command output through to console output
     *
     * @return boolean
     */
    public function isPt()
    {
        return $this->pt;
    }

    /**
     * Set whether to pass command output through to console output
     *
     * @param boolean $pt Passthru?
     *
     * @return void
     */
    public function setPt($pt)
    {
        $this->pt = $pt;
    }

    /**
     * Execute a shell command
     *
     * @return mixed The output of the shell command or FALSE if the command returned a non-zero exit code and $ignoreErrors was enabled.
     */
    public function run()
    {
        $command = $this->getCommandLine();

        $this->console->output('<cmd>' . $this->getWorkingDirectory() . ' > ' . $command . '</cmd>', OutputInterface::VERBOSITY_VERBOSE);

        if ($this->dryRun) {
            return null;
        }

        parent::run(
            function ($type, $buffer) {
                if (!$this->shy) {
                    $this->console->output($buffer, $this->pt ? $this->console->getVerbosity() : OutputInterface::VERBOSITY_DEBUG, false, !$this->pt);
                }
            }
        );

        if ($this->getExitCode() !== 0) {
            if ($this->console->getVerbosity() <= OutputInterface::VERBOSITY_DEBUG && !$this->pt) {
                $this->console->getOutput()->write($this->getErrorOutput(), false, OutputInterface::OUTPUT_RAW);
            }
            throw new \Netresearch\Kite\Exception(
                $this->getErrorOutput() ?: $this->getExitCodeText(),
                $this->getExitCode()
            );
        }

        return trim($this->getOutput());
    }
}
?>
