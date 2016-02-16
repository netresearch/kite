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

use Netresearch\Kite\Console\Output\Output;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A shell command service
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Service
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */
class Console
{
    /**
     * @var Output
     */
    protected $output = null;

    /**
     * @var Output Second instance for debug output (all)
     */
    private $debugOutput;

    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input = null;

    /**
     * @var Application
     */
    protected $application;

    /**
     * @var int
     */
    private $outputType = OutputInterface::OUTPUT_NORMAL;

    private $previousVerbosities = array();

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var Factory
     */
    protected $factory;

    protected $config;

    /**
     * Console constructor.
     *
     * @param Config $config Config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Get the config
     *
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Get the factory
     *
     * @return Factory
     */
    public function getFactory()
    {
        if (!$this->factory) {
            $this->factory = new Factory($this);
        }
        return $this->factory;
    }

    /**
     * Get the filesystem service
     *
     * @return Filesystem
     */
    public function getFilesystem()
    {
        if (!$this->filesystem) {
            $this->filesystem = new Filesystem($this);
        }
        return $this->filesystem;
    }

    /**
     * Get a new process
     *
     * @param string $command The command to execute
     * @param string $cwd     The directory to execute the command in
     *
     * @return Process
     */
    public function createProcess($command, $cwd = null)
    {
        return new Process($this, $command, $cwd);
    }

    /**
     * Get the debug output
     *
     * @return Output
     */
    public function getDebugOutput()
    {
        return $this->debugOutput;
    }

    /**
     * Set debug output
     *
     * @param Output $debugOutput The output
     *
     * @return void
     */
    public function setDebugOutput(Output $debugOutput)
    {
        $this->debugOutput = $debugOutput;
    }

    /**
     * Set the application
     *
     * @param Application $application The application
     *
     * @return Console
     */
    public function setApplication($application)
    {
        $this->application = $application;
        return $this;
    }

    /**
     * Get the application
     *
     * @return Application
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * Set the output
     *
     * @param BufferedOutput|OutputInterface $output The output
     *
     * @return Console
     */
    public function setOutput(Output $output)
    {
        $this->output = $output;
        return $this;
    }

    /**
     * Get the output
     *
     * @return \Symfony\Component\Console\Output\Output
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Set the input
     *
     * @param InputInterface $input The input
     *                              
     * @return Console
     */
    public function setInput($input)
    {
        $this->input = $input;
        return $this;
    }

    /**
     * Get the input
     *
     * @return \Symfony\Component\Console\Input\InputInterface
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * Get a console helper
     *
     * @param string $name Helper name
     *
     * @return Helper\HelperInterface
     */
    public function getHelper($name)
    {
        return $this->application->getHelperSet()->get($name);
    }

    /**
     * Increase indention for all following lines
     *
     * @param int $tabs The tabs
     *
     * @return void
     */
    public function indent($tabs = 1)
    {
        if ($this->output) {
            $this->output->indent($tabs);
        }
        if ($this->debugOutput) {
            $this->debugOutput->indent($tabs);
        }
    }

    /**
     * Decrease indention for all following lines
     *
     * @param int $tabs The tabs
     *
     * @return void
     */
    public function outdent($tabs = 1)
    {
        if ($this->output) {
            $this->output->outdent($tabs);
        }
        if ($this->debugOutput) {
            $this->debugOutput->outdent($tabs);
        }
    }

    /**
     * Set a new minimum severity for messages to be shown
     *
     * @param int $verbosity The verbosity
     *
     * @return $this
     */
    public function setVerbosity($verbosity)
    {
        $this->previousVerbosities[] = $this->output->getVerbosity();
        $this->output->setVerbosity($verbosity);
        return $this;
    }

    /**
     * Get verbosity for messages to be shown
     *
     * @return int
     */
    public function getVerbosity()
    {
        return $this->output->getVerbosity();
    }

    /**
     * Restore the verbosity that was set before the last call to setSeverity
     *
     * @return $this
     */
    public function restoreVerbosity()
    {
        if ($this->previousVerbosities) {
            $this->output->setVerbosity(array_pop($this->previousVerbosities));
        }
        return $this;
    }

    /**
     * Output a string
     *
     * @param string   $message            The message
     * @param int|bool $verbosityOrNewLine Severity or whether to print a newline
     * @param bool     $newLine            Whether to print a newline
     * @param bool     $raw                If true, don't style the output
     *
     * @return void
     */
    public function output($message, $verbosityOrNewLine = OutputInterface::VERBOSITY_NORMAL, $newLine = true, $raw = false)
    {
        if (is_bool($verbosityOrNewLine)) {
            $newLine = $verbosityOrNewLine;
            $verbosityOrNewLine = OutputInterface::VERBOSITY_NORMAL;
        }

        if ($this->debugOutput) {
            $this->debugOutput->write($message, $newLine, $raw ? OutputInterface::OUTPUT_RAW : $this->outputType);
        }

        $verbosity = $this->output->getVerbosity();

        if ($verbosity !== 0 && $verbosityOrNewLine <= $verbosity) {
            $this->output->write($message, $newLine, $raw ? OutputInterface::OUTPUT_RAW : $this->outputType);
        }
    }
}
?>
