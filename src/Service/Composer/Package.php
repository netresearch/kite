<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category Netresearch
 * @package  Netresearch\Kite\Service\Composer
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */

namespace Netresearch\Kite\Service\Composer;
use Netresearch\Kite\Exception;
use Netresearch\Kite\Service\Composer;

/**
 * Class Package
 *
 * // Composer properties, not necessarily present:
 *
 * @property string      $name      Package name
 * @property string      $version   Package version
 * @property object      $source    Information about the source
 *                                  (may have f.i. "reference")
 *
 * // Additional properties
 *
 * @property string      $path      Package path
 * @property bool        $isRoot    Whether package is root (project)
 * @property array       $requires  The packages from $require as array
 * @property bool        $git       Whether installed package is a get repository
 * @property array       $branches  Git branches (including remote branches)
 * @property array       $upstreams Upstream branches (values) for local branches (keys)
 * @property string|null $branch    The currently checked out branch, if any
 * @property string|null $tag       The currently checked out tag, if any
 * @property string|null $remote    The remote url (no multiple urls supported)
 *
 *
 * @category Netresearch
 * @package  Netresearch\Kite\Service\Composer
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */
class Package
{
    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var bool
     */
    protected static $forEachRefHeadSupported = true;

    /**
     * Package constructor.
     *
     * @param Composer      $composer     The parent object
     * @param string|object $composerJson The composer json
     * @param bool          $isRoot       Whether package is root
     */
    public function __construct(Composer $composer, $composerJson, $isRoot = false)
    {
        $this->composer = $composer;

        if (is_string($composerJson)) {
            $path = realpath($composerJson);
            if (!$path) {
                throw new Exception('Could not find ' . $composerJson);
            }
            $this->path = dirname($path);
            $composerJson = json_decode(file_get_contents($path));
            if (!is_object($composerJson)) {
                throw new Exception('Could not load ' . $path);
            }
        }
        foreach (get_object_vars($composerJson) as $key => $value) {
            $this->$key = $value;
        }

        $this->isRoot = $isRoot;
    }

    /**
     * Load lazy properties
     *
     * @param string $name The property name
     *
     * @return mixed
     */
    public function __get($name)
    {
        switch ($name) {
        case 'git':
            $gitDir = $this->path . '/.git';
            $this->git = file_exists($gitDir) && is_dir($gitDir);
            break;
        case 'branches':
        case 'upstreams':
        case 'branch':
            $this->loadGitInformation();
            break;
        case 'tag':
            $this->loadTag();
            break;
        case 'remote':
            $this->loadRemote();
            break;
        case 'requires':
            $this->loadRequires();
            break;
        default:
            throw new Exception('Invalid property ' . $name);

        }
        return $this->$name;
    }

    public function getNewVersionAlias($requiredPackage, $newVersionName, $aliases = false)
    {
        $currentVersion = $this->requires[$requiredPackage];

        if ($aliases) {
            $pos = strpos($currentVersion, ' as ');

            if ($pos) {
                $currentVersion = substr($currentVersion, $pos + 4);
            }

            $newVersionName .= ' as ' . $currentVersion;
        }

        return $newVersionName;
    }

    /**
     * Mark lazy properties as present
     *
     * @param string $name The name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return in_array($name, ['branches', 'upstreams', 'branch', 'tag', 'git', 'remote', 'requires'], true);
    }

    /**
     * Load the requires - removes inline aliases
     *
     * @return void
     */
    protected function loadRequires()
    {
        $this->requires = isset($this->requires) ? get_object_vars($this->requires) : array();
        foreach ($this->requires as $package => $constraint) {
            if ($pos = strpos($constraint, ' as ')) {
                if ($hashPos = strpos($constraint, '#')) {
                    // dev-master#old-hash isn't treated by composer, so we don't as well
                    $pos = $hashPos;
                }
                $this->requires[$package] = substr($constraint, 0, $pos);
            }
        }
    }

    /**
     * Reload requires from composer.json
     *
     * @return $this
     */
    public function reloadRequires()
    {
        $file = $this->path . '/composer.json';
        if (file_exists($file)) {
            $composerJson = json_decode(file_get_contents($file));
            unset($this->require);
            if (isset($composerJson->require)) {
                $this->require = $composerJson->require;
            }
            $this->loadRequires();
        }
        return $this;
    }

    /**
     * Get the remote
     *
     * @return void
     */
    protected function loadRemote()
    {
        $this->remote = null;
        if ($this->git) {
            $remote = null;
            $remotesString = $this->composer->git('remote', $this->path, ['verbose' => true]);
            $lines = explode("\n", trim($remotesString));
            foreach ($lines as $line) {
                preg_match('/^([^\s]+)\s+(.+) \((fetch|push)\)$/', $line, $match);
                array_shift($match);
                list($name, $url) = $match;
                if ($remote && $remote !== $url) {
                    $this->composer->output("<warning>Can not handle multiple remote urls - using $remote</warning>");
                    break;
                } else {
                    $remote = $url;
                }
            }
            $this->remote = $remote;
        }
    }

    /**
     * Load 'branches', 'upstreams', 'branch', 'tag', 'git'
     *
     * @throws Exception\ProcessFailedException
     *
     * @return void
     */
    protected function loadGitInformation()
    {
        $this->branches = array();
        $this->upstreams = array();
        $this->branch = null;
        if ($this->git) {
            $this->composer->git('fetch', $this->path, array('p' => true, 'origin'));
            try {
                $format = (self::$forEachRefHeadSupported ? '%(HEAD)' : '') . '|%(refname:short)|%(upstream:short)';
                $gitBr = $this->composer->git('for-each-ref', $this->path, ['format' => $format, 'refs/heads/', 'refs/remotes/origin']);
            } catch (Exception\ProcessFailedException $e) {
                if (trim($e->getProcess()->getErrorOutput()) === 'fatal: unknown field name: HEAD') {
                    self::$forEachRefHeadSupported = false;
                    $this->loadGitInformation();
                    return;
                } else {
                    throw $e;
                }
            }
            if (!self::$forEachRefHeadSupported) {
                $this->branch = $this->composer->git('rev-parse', $this->path, ['abbrev-ref' => true, 'HEAD']) ?: null;
                if ($this->branch === 'HEAD') {
                    $this->branch = null;
                }
            }
            foreach (explode("\n", trim($gitBr)) as $line) {
                list($head, $branch, $upstream) = explode('|', $line);
                if ($branch === 'origin/HEAD') {
                    continue;
                }
                $this->branches[] = $branch;
                if ($head === '*') {
                    $this->branch = $branch;
                }
                if ($upstream) {
                    $this->upstreams[$branch] = $upstream;
                }
            }
        }
    }

    /**
     * Load the currently checked out tag
     *
     * @return void
     */
    protected function loadTag()
    {
        try {
            $this->tag = trim($this->composer->git('describe', $this->path, array('exact-match' => true, 'tags' => true)));
        } catch (Exception\ProcessFailedException $e) {
            $this->tag =  null;
        }
    }
}

?>
