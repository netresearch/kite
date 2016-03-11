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
 *                                  If package is on a tag this will always be null
 * @property string|null $tag       The currently checked out tag, if any
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

        $this->requires = isset($composerJson->require) ? get_object_vars($composerJson->require) : array();
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
        if (in_array($name, ['branches', 'upstreams', 'branch', 'tag', 'git'], true)) {
            $this->loadGitInformation();
            return $this->$name;
        }
        throw new Exception('Invalid property ' . $name);
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
        $this->tag = null;
        $gitDir = $this->path . '/.git';
        $this->git = file_exists($gitDir) && is_dir($gitDir);
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
            if (!self::$forEachRefHeadSupported && !($this->tag = $this->getTag())) {
                $this->branch = $this->composer->git('rev-parse', $this->path, ['abbrev-ref' => true, 'HEAD']) ?: null;
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
            if (self::$forEachRefHeadSupported && !$this->branch) {
                $this->tag = $this->getTag();
            }
        }
    }

    /**
     * Get  the currently checked out tag
     *
     * @return string|null
     */
    protected function getTag()
    {
        try {
            return trim($this->composer->git('describe', $this->path, array('exact-match' => true, 'tags' => true)));
        } catch (Exception\ProcessFailedException $e) {
            return null;
        }

    }
}

?>
