<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Task
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */

namespace Netresearch\Kite\Task;

/**
 * Create a tar archive a set of files
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Task
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */
class TarTask extends \Netresearch\Kite\Task
{
    /**
     * Configure the options
     *
     * @return array
     */
    protected function configureVariables()
    {
        return array(
            'command' => array(
                'type' => 'string',
                'label' => 'Name of the task',
                'required' => true,
            ),
            'cwd' => array(
                'type' => 'string',
                'label' => 'The directory to change to before running the command'
            ),
            'options' => array(
                'type' => 'array',
                'default' => array(),
                'label' => 'Array with options: Elements with numeric keys or bool true values will be --switches.'
            ),
            'arguments' => array(
                'type' => 'array',
                'default' => array(),
                'label' => 'Arguments to pass to the cmd'
            ),
            'optArg' => array(
                'type' => 'array',
                'label' => 'Arguments and options in one array. Elements with numeric keys will be arguments, elems. with bool true values will be --switches, all other options'
            )
        ) + parent::configureVariables();
    }

    /**
     * Get the files
     *
     * @return array|mixed
     */
    protected function getFiles()
    {
        $files = $this->get('files');
        if (is_string($files)) {
            $files = array_filter(preg_split('#[\n' . PATH_SEPARATOR . ',]#', $files));
        }
        if (!is_array($files) && !$files instanceof \Traversable) {
            throw new \Netresearch\Kite\Exception('files must be traversable');
        }

        return $files;
    }

    /**
     * Execute the task
     *
     * @return void
     */
    public function execute()
    {
        $filesystem = $this->console->getFilesystem();
        $dir = dirname($this->get('toFile'));
        $filesystem->ensureDirectoryExists($dir);
        $cmd = 'tar -rf ' . escapeshellarg($this->get('toFile')) . ' ';

        if ($this->get('tmpCopy', true)) {
            $tmpFolder = basename($this->get('toFile')) . '-' . uniqid();
            $filesystem->ensureDirectoryExists($tmpFolder);

            foreach ($this->getFiles() as $file) {
                $filesystem->copy($file, $tmpFolder . '/' . $file);
            }

            $this->console->createProcess($cmd . ' -C ' . escapeshellarg($tmpFolder) . ' .')->run();

            $filesystem->remove($tmpFolder);
        } else {
            foreach ($this->getFiles() as $file) {
                $this->console->createProcess($cmd . escapeshellarg($file))->run();
            }
        }
    }
}
?>
