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

namespace Netresearch\Kite\Console;
use Netresearch\Kite\Console\Command\LogCommand;
use Netresearch\Kite\Console\Output\ConsoleOutput;
use Netresearch\Kite\Console\Output\Output;
use Netresearch\Kite\Exception;
use Netresearch\Kite\Service\Config;
use Netresearch\Kite\Console\Command\JobCommand;
use Netresearch\Kite\Service\Factory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Kite application
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Console
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */
class Application extends \Symfony\Component\Console\Application
{
    /**
     * @var \Netresearch\Kite\Service\Config
     */
    protected $config;

    /**
     * @var Output
     */
    protected $output;

    /**
     * Application constructor.
     */
    public function __construct()
    {
        $this->output = new ConsoleOutput();
        $this->output->setTerminalDimensions($this->getTerminalDimensions());

        $this->loadConfig();

        parent::__construct('Kite');
    }

    /**
     * Load the configuration
     *
     * @return void
     */
    protected function loadConfig()
    {
        $this->config = new Config();

        $expectedFile = 'kite.php';
        $app = null;
        $indicators = [
            'TYPO3' => ['typo3conf', 'Kite.php'],
            'Magento' => ['app/etc', 'kite.php'],
        ];
        foreach ($indicators as $appName => $dirAndFile) {
            list($dir, $file) = $dirAndFile;
            if (is_dir($dir)) {
                $app = $appName;
                $expectedFile = $dir . '/' . $file;
                break;
            }
        }

        try {
            $this->config->loadConfigFile($expectedFile);
        } catch (\Exception $e) {
            if ($app) {
                $message = [
                    'You appear to be in a ' . $app . ' root directory but',
                    'there is no kite config file at the expected',
                    'location (' . $expectedFile . ').'
                ];
            } else {
                $message = [
                    'You are either not in an application root directory or',
                    'you have no appropriate config file yet.',
                    '',
                    'The config file path is expected to be:',
                ];
                foreach ($indicators as $appName => $dirAndFile) {
                    $message[] = '  - "' . implode('/', $dirAndFile) . '" for ' . $appName . ' applications or';
                }
                $message[] = '  - "' . $expectedFile . '" for any other application';
            }
            $lMax = 0;
            foreach ($message as $line) {
                if (($l = strlen($line)) > $lMax) {
                    $lMax = $l;
                }
            }
            $this->output->writeln('<warning>' . str_repeat(' ', $lMax + 4) . '</warning>');
            foreach ($message as $line) {
                $line = str_pad($line, $lMax + 2);
                $this->output->writeln("<warning>  $line</warning>");
            }
            $this->output->writeln('<warning>' . str_repeat(' ', $lMax + 4) . '</warning>');
        }
    }

    /**
     * Gets the help message.
     *
     * @return string A help message.
     */
    public function getHelp()
    {
        $help
            = " _  ___ _       \n"
            . "| |/ /_| |_ ___\n"
            . "| ' /| | __/ _ \\\n"
            . "| . \\| | |_| __/\n"
            . "|_|\\_|_|\\__\\___|\n\n"
            . $this->getLongVersion() . "\n\n"
            . $this->getSelfPackage()->description;

        return $help;
    }

    /**
     * Returns the long version of the application.
     *
     * @return string The long application version
     */
    public function getLongVersion()
    {
        $package = $this->getSelfPackage();
        $v = "<info>Kite</info> version <comment>{$package->version}</comment>";
        if (isset($package->source)) {
            $v .= " <comment>({$package->source->reference})</comment>";
        }
        $v .= ' ' . $package->time;
        return $v;
    }

    /**
     * Get package info from composer.lock
     *
     * @return object
     */
    protected function getSelfPackage()
    {
        static $package = null;
        if (!$package) {
            $files = [__DIR__ . '/../../../../composer/installed.json', __DIR__ . '/vendor/composer/installed.json'];
            foreach ($files as $file) {
                if (file_exists($file)) {
                    $installed = json_decode(file_get_contents($file));
                    foreach ($installed as $candidate) {
                        if (substr($candidate->name, -5) === '/kite') {
                            $package = $candidate;
                            break 2;
                        }
                    }
                }
            }
            if (!$package) {
                $kitePath = dirname(dirname(__DIR__));
                $process = new Process('git symbolic-ref -q --short HEAD || git describe --tags --exact-match; git rev-parse HEAD; git show -s --format=%ct HEAD', $kitePath);
                $process->run();
                if ($output = $process->getOutput()) {
                    $package = json_decode(file_get_contents($kitePath . '/composer.json'));
                    list($name, $revision, $tstamp) = explode("\n", trim($output), 3);
                    $package->version = preg_match('/^v?[0-9]+\.[0-9]+\.[0-9]+(-[a-z0-9]+)?$/i', $name) ? $name : 'dev-' . $name;
                    $package->source = (object) ['reference' => $revision];
                    $package->time = date('Y-m-d H:i:s', $tstamp);
                } else {
                    throw new Exception('Could not determine self version');
                }
            }
        }
        return $package;
    }

    /**
     * Add workflow option
     *
     * @return \Symfony\Component\Console\Input\InputDefinition
     */
    protected function getDefaultInputDefinition()
    {
        $definition = parent::getDefaultInputDefinition();
        $definition->addOption(new InputOption('workflow', null, InputOption::VALUE_OPTIONAL, 'Run a workflow on the fly'));

        if (isset($_SERVER['HOME']) && is_writable($_SERVER['HOME'])) {
            $debugDir = $_SERVER['HOME'] . '/.kite/log';
        } else {
            $debugDir = false;
        }
        $definition->addOption(
            new InputOption(
                'debug-dir', null, InputOption::VALUE_OPTIONAL,
                "Path to directory to which to dump the debug output file",
                $debugDir
            )
        );
        return $definition;
    }

    /**
     * Runs the current application.
     *
     * @param InputInterface $input An Input instance
     *
     * @return int 0 if everything went fine, or an error code
     */
    public function run(InputInterface $input = null)
    {
        return parent::run($input, $this->output);
    }


    /**
     * Create job on the fly when workflow option is present
     *
     * @param InputInterface  $input  The input
     * @param OutputInterface $output The output
     *
     * @return int
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        if ($input->hasParameterOption('--workflow')) {
            $strInput = (string) $input;
            if ($this->getCommandName($input) === 'help') {
                $strInput = str_replace('help ', '', $strInput);
                $strInput .= ' --help';
            }
            $workflow = $input->getParameterOption('--workflow');
            $jobName = 'generic:workflow:' . $workflow;
            $this->config->configureJob($jobName, array('workflow' => $workflow));
            $command = new JobCommand($jobName, $this->config);
            $this->add($command);

            $parameterOption = '--workflow=' . $workflow;
            $input = new StringInput(
                rtrim(
                    $jobName . ' ' .
                    str_replace(
                        array(
                            $parameterOption . ' ',
                            ' ' . $parameterOption,
                            $parameterOption
                        ), '', $strInput
                    )
                )
            );
        }
        return parent::doRun($input, $output);
    }


    /**
     * Gets the default commands that should always be available.
     *
     * @return Command[] An array of default Command instances
     */
    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();
        $commands[] = new LogCommand();

        foreach ($this->config->getJobConfiguration() as $name => $configuration) {
            $commands[] = new JobCommand($name, $this->config);
        }

        return $commands;
    }
}
?>
