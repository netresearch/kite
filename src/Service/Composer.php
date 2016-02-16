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
use Netresearch\Kite\Job;
use Netresearch\Kite\Task;
use Netresearch\Kite\Exception;
use Netresearch\Kite\Tasks;

/**
 * Composer service
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Service
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */
class Composer extends Tasks
{
    /**
     * @var bool
     */
    protected $tasksInvalid = true;

    /**
     * Job constructor.
     *
     * @param Job $job The job
     */
    public function __construct(Job $job)
    {
        parent::__construct($job);
        $this->run();
    }

    /**
     * Invalidate the packages
     *
     * @return void
     */
    public function invalidatePackages()
    {
        $this->tasksInvalid = true;
    }

    /**
     * Get a variable (second argument can be a default value)
     *
     * @param string $name The variable name
     *
     * @return mixed
     */
    public function &offsetGet($name)
    {
        if ($this->tasksInvalid && in_array($name, array('packages', 'rootPackage'), true)) {
            $this->tasksInvalid = false;
            $packages = $this->getPackages();
            parent::offsetSet('packages', $packages);
            parent::offsetSet('rootPackage', reset($packages));
        }
        return parent::offsetGet($name);
    }

    /**
     * Determine if a variable is available on this very object
     *
     * @param mixed $offset Variable name
     *
     * @internal See {@see Variables::offsetGet()}
     *
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return in_array($offset, array('packages', 'rootPackage')) || parent::offsetExists($offset);
    }


    /**
     * Get all packages
     *
     * @return array
     */
    protected function getPackages()
    {
        $packages = array();

        $this->output('<step>Gathering composer package information</step>');

        if (!file_exists('composer.json')) {
            throw new Exception('Could not find composer.json');
        }
        $composerJson = json_decode(file_get_contents('composer.json'));
        if (!$composerJson->name) {
            throw new Exception('No name for project found in composer.json');
        }
        $packages[$composerJson->name] = $composerJson;
        $composerJson->path = realpath(getcwd());
        $composerJson->isRoot = true;
        $composerJson->requires = isset($composerJson->require) ? get_object_vars($composerJson->require) : array();

        if (!file_exists('composer.lock')) {
            throw new Exception('Please install application first');
        }
        $composerLock = json_decode(file_get_contents('composer.lock'));

        $packagePaths = $this->composer('show', '--installed --path --no-ansi', array('pt' => false));
        foreach (explode("\n", $packagePaths) as $line) {
            list($packageName, $packagePath) = preg_split('/\s+/', $line, 2);
            foreach ($composerLock->packages as $package) {
                if ($package->name === $packageName && $package->type !== 'metapackage') {
                    $package->path = $packagePath;
                    $package->isRoot = false;
                    $package->requires = isset($package->require) ? get_object_vars($package->require) : array();
                    $packages[$package->name] = $package;
                }
            }
        }

        foreach ($packages as $package) {
            $package->branches = array();
            $package->upstreams = array();
            $package->branch = null;
            $package->tag = null;
            $gitDir = $package->path . '/.git';
            $package->git = file_exists($gitDir) && is_dir($gitDir);
            if ($package->git) {
                $this->git('fetch', $package->path, array('p' => true, 'origin'));
                $gitBr = $this->git(
                    'for-each-ref', $package->path,
                    array('format' => '%(HEAD)|%(refname:short)|%(upstream:short)', 'refs/heads/', 'refs/remotes/origin')
                );
                foreach (explode("\n", trim($gitBr)) as $line) {
                    list($head, $branch, $upstream) = explode('|', $line);
                    $package->branches[] = $branch;
                    if ($head === '*') {
                        $package->branch = $branch;
                    }
                    if ($upstream) {
                        $package->upstreams[$branch] = $upstream;
                    }
                }
                if (!$package->branch) {
                    try {
                        $package->tag = trim($this->git('describe', $package->path, array('exact-match' => true, 'tags' => true)));
                    } catch (\Exception $e) {
                    }
                }
            }
        }

        return $packages;
    }
}
?>
