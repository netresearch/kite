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

namespace Netresearch\Kite\Service;

use Netresearch\Kite\Exception;
use Netresearch\Kite\Job;
use Netresearch\Kite\Service\Composer\Package;
use Netresearch\Kite\Tasks;

/**
 * Composer service.
 *
 * @category   Netresearch
 *
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 *
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
     * Invalidate the packages.
     *
     * @return void
     */
    public function invalidatePackages()
    {
        $this->tasksInvalid = true;
    }

    /**
     * Get a variable (second argument can be a default value).
     *
     * @param string $name The variable name
     *
     * @return mixed
     */
    public function &offsetGet($name)
    {
        if ($this->tasksInvalid && in_array($name, ['packages', 'rootPackage'], true)) {
            $this->tasksInvalid = false;
            $packages = $this->getPackages();
            parent::offsetSet('packages', $packages);
            parent::offsetSet('rootPackage', reset($packages));
        }

        return parent::offsetGet($name);
    }

    /**
     * Determine if a variable is available on this very object.
     *
     * @param mixed $offset Variable name
     *
     * @internal See {@see Variables::offsetGet()}
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return in_array($offset, ['packages', 'rootPackage']) || parent::offsetExists($offset);
    }

    /**
     * Get all packages.
     *
     * @return array
     */
    protected function getPackages()
    {
        $packages = [];

        $this->output('<step>Gathering composer package information</step>');

        $composerJson = new Package($this, 'composer.json', true);
        if (!isset($composerJson->name)) {
            throw new Exception('No name for project found in composer.json');
        }
        $packages[$composerJson->name] = $composerJson;

        if (!file_exists('composer.lock')) {
            throw new Exception('Please install application first');
        }
        $composerLock = json_decode(file_get_contents('composer.lock'));

        $packagePaths = $this->composer('show', '--installed --path --no-ansi', ['pt' => false]);
        foreach (explode("\n", $packagePaths) as $line) {
            list($packageName, $packagePath) = preg_split('/\s+/', $line, 2);
            foreach ($composerLock->packages as $package) {
                if ($package->name === $packageName && $package->type !== 'metapackage') {
                    $package->path = $packagePath;
                    $packages[$packageName] = new Package($this, $package);
                }
            }
        }

        return $packages;
    }
}
