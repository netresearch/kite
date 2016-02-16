<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category Netresearch
 * @package  Netresearch\Kite\Console\Command
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */

namespace Netresearch\Kite\Console\Command;
use Netresearch\Kite\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class LogCommand
 *
 * @category Netresearch
 * @package  Netresearch\Kite\Console\Command
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */
class LogCommand extends Command
{
    /**
     * Configure the command
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('log')
            ->setDescription('Show the debug log of a previous kite job execution')
            ->setDefinition(
                array(
                    new InputOption('list', 'l', null, 'List available log entries'),
                    new InputArgument('entry', null, 'The log entry - either full name, as in <info>--list</info> or last nth in the form <comment>^n</comment>')
                )
            );
        ;
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
        $dir = $input->getOption('debug-dir');
        if (!is_dir($dir)) {
            throw new Exception("Debug dir $dir doesn't exist");
        }
        $files = glob($dir . '/*');
        if ($input->getOption('list')) {
            foreach ($files as $file) {
                $output->writeln(basename($file));
            }
            return;
        }
        if ($entry = $input->getArgument('entry')) {
            if ($entry[0] === '^') {
                $back = ((int) substr($entry, 1)) + 1;
                while ($back--) {
                    $show = array_pop($files);
                }
            } else {
                foreach ($files as $file) {
                    if (basename($file) === $entry) {
                        $show = $file;
                        break;
                    }
                }
            }
        } else {
            $show = array_pop($files);
        }
        if (!isset($show)) {
            throw new Exception("Couldn't find suitable entry");
        }
        $output->write(file_get_contents($show), false, OutputInterface::OUTPUT_RAW);
    }
}

?>
