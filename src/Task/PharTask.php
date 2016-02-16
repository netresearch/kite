<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category Netresearch
 * @package  Netresearch\Kite\Task
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */

namespace Netresearch\Kite\Task;
use Netresearch\Kite\Task;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\PhpProcess;

/**
 * Class PharTask
 *
 * @category Netresearch
 * @package  Netresearch\Kite\Task
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */
class PharTask extends Task
{
    /**
     * Configures the available options
     *
     * @return array
     */
    protected function configureVariables()
    {
        return array(
            'from' => array(
                'type' => 'string',
                'required' => true,
                'label' => 'The path to the directory to create the phar from'
            ),
            'to' => array(
                'type' => 'string',
                'required' => true,
                'label' => 'Path and filename of the resulting phar file'
            ),
            'filter' => array(
                'type' => 'string',
                'label' => 'Only file paths matching this pcre regular expression will be included in the archive'
            ),
            'cliStub' => array(
                'type' => 'string',
                'label' => 'Path to cli index file, relative to <info>comment</info>'
            ),
            'webStub' => array(
                'type' => 'string',
                'label' => 'Path to web index file, relative to <info>comment</info>'
            ),
            'alias' => array(
                'type' => 'string',
                'label' => 'Alias with which this Phar archive should be referred to in calls to stream functionality'
            ),
            'metadata' => array(
                'type' => 'mixed',
                'label' => 'Anything containing information to store that describes the phar archive'
            )
        ) + parent::configureVariables();
    }

    /**
     * Actually execute this task
     *
     * @return \Phar
     */
    public function execute()
    {
        $to = $this->get('to');
        if (file_exists($to)) {
            $this->console->getFilesystem()->remove($to);
        }

        $executableFinder = new PhpExecutableFinder();
        $php = $executableFinder->find();
        $php .= ' -d phar.readonly=0';

        $code = "<?php\n";
        foreach (array('to', 'from', 'filter', 'cliStub', 'webStub', 'alias', 'metadata') as $var) {
            $value = $this->get($var);
            $code .= '$' . $var . " = unserialize('" . serialize($value) . "');\n";
        }
        $code .= '
        $phar = new \Phar($to);
        $phar->buildFromDirectory($from, $filter);
        if ($cliStub || $webStub) {
            $phar->setStub($phar->createDefaultStub($cliStub, $webStub));
        }
        if ($alias) {
            $phar->setAlias($alias);
        }
        if ($metadata) {
            $phar->setMetadata($metadata);
        }
        ?>';

        $process = $this->console->createProcess($php);
        $process->setInput($code);
        $process->run();

        return new \Phar($to);
    }
}
?>
