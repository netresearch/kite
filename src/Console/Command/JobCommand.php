<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Console
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */

namespace Netresearch\Kite\Console\Command;
use Netresearch\Kite\Console\Output\Output;
use Netresearch\Kite\Service\Config;
use Netresearch\Kite\Exception;
use Netresearch\Kite\Exception\ExitException;
use Netresearch\Kite\Job;
use Netresearch\Kite\Service\Console;
use Netresearch\Kite\Service\Descriptor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to execute a job
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Console
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */
class JobCommand extends Command
{
    /**
     * @var \Netresearch\Kite\Job
     */
    protected $job;

    /**
     * @var \Netresearch\Kite\Service\Console
     */
    protected $console;

    protected $jobDefinitionMerged = false;

    /**
     * Constructor.
     *
     * @param string $name   The name of the job
     * @param Config $config Config
     *
     * @api
     */
    public function __construct($name, Config $config)
    {
        parent::__construct($name);
        $this->console = new Console($config);
    }

    /**
     * Remove workflow option
     *
     * @param bool $mergeArgs mergeArgs
     *
     * @return void
     */
    public function mergeApplicationDefinition($mergeArgs = true)
    {
        parent::mergeApplicationDefinition($mergeArgs);
        $options = array();
        foreach ($this->getDefinition()->getOptions() as $option) {
            if ($option->getName() !== 'workflow') {
                $options[] = $option;
            }
        }
        $this->getDefinition()->setOptions($options);
    }

    /**
     * Merge in job definition
     *
     * @param bool $short Whether to return short synopsis
     *
     * @return string
     */
    public function getSynopsis($short = false)
    {
        if (!$this->jobDefinitionMerged) {
            $definition = $this->getJob()->getDefinition();
            $this->getDefinition()->addOptions($definition->getOptions());
            $this->getDefinition()->addArguments($definition->getArguments());
            $this->jobDefinitionMerged = true;
        }

        return preg_replace('/^generic:([^:]+):([^ ]+)/', '--$1=$2', parent::getSynopsis($short));
    }

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        parent::configure();

        $this->addOption('dry-run', null, null, 'Show what would happen');
        $this->addOption('no-debug-file', null, null, 'Never put debug output to a file');
    }

    /**
     * Get the description
     *
     * @return string
     */
    public function getDescription()
    {
        $description = parent::getDescription();
        if ($description === null) {
            $descriptor = new Descriptor();
            $description = (string) $descriptor->describeTask($this->getJob());
            parent::setDescription($description);
        }
        return $description;
    }

    /**
     * Display the help - doing this here, because in configure() the helpers are not
     * yet available.
     *
     * @return string
     */
    public function getHelp()
    {
        return "\n"
            . "The <info>%command.name%</info> command executes the according job\n"
            . "from kite configuration:\n\n"
            . $this->getHelper('formatter')->formatBlock($this->getDescription(), 'fg=black;bg=green', true)
            . "\n\nThe canonicalized command is:\n\n"
            . "  <info>php " . $_SERVER['PHP_SELF'] . ' ' . preg_replace('/^generic:([^:]+):([^ ]+)/', '--$1=$2', $this->getName()) . "</info>\n";
    }

    /**
     * Create and return the job
     *
     * @return Job
     */
    public function getJob()
    {
        if (!$this->job) {
            $this->job = $this->console->getFactory()->createJob($this->getName());
        }
        return $this->job;
    }

    /**
     * Initialize the environment
     *
     * @param InputInterface  $input  Input
     * @param OutputInterface $output Output
     *
     * @return void
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->console
            ->setApplication($this->getApplication())
            ->setInput($input)
            ->setOutput($output);

        if (!$input->getOption('no-debug-file') && $debugDir = $input->getOption('debug-dir')) {
            $this->console->getFilesystem()->ensureDirectoryExists($debugDir);
            // keep max 20 logs
            $files = glob($debugDir . '/*');
            while (count($files) > 19) {
                $this->console->getFilesystem()->remove(array_shift($files));
            }
            $logFile = date('YmdHis');
            $debugOutput = new Output(
                fopen(rtrim($debugDir, '\\/') . '/' . $logFile, 'w'),
                Output::VERBOSITY_VERY_VERBOSE,
                true
            );
            $this->console->setDebugOutput($debugOutput);
            $debugOutput->setTerminalDimensions($this->getApplication()->getTerminalDimensions());
            $debugOutput->writeln(
                $this->getHelper('formatter')->formatBlock(
                    implode(' ', $_SERVER['argv']), 'fg=black;bg=white', true
                ) . "\n"
            );
        }
    }

    /**
     * Executes the current command.
     *
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return null|int null or 0 if everything went fine, or an error code
     *
     * @throws \LogicException When this abstract method is not implemented
     *
     * @see setCode()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $job = $this->getJob();
        try {
            $job->run();
        } catch (\Exception $e) {
            if ($e instanceof ExitException && $e->getCode() === 0) {
                if ($e->getMessage()) {
                    $output->writeln('<info>' . $e->getMessage() . '</info>');
                }
                return 0;
            }

            // This doesn't go to the debug log, as $output->writeln and not $console->output is used:
            $this->getApplication()->renderException($e, $output instanceof ConsoleOutput ? $output->getErrorOutput() : $output);
            // But this one:
            $this->getApplication()->renderException($e, $this->console->getDebugOutput());

            $exitCode = $e->getCode();
            if (is_numeric($exitCode)) {
                $exitCode = (int) $exitCode;
                if (0 === $exitCode) {
                    $exitCode = 1;
                }
            } else {
                $exitCode = 1;
            }
            return $exitCode;
        }
    }
}

?>
