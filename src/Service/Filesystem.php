<?php
/**
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * PHP Version 5
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Service
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    https://github.com/composer/composer/blob/master/LICENSE Composer license
 * @link       http://www.netresearch.de
 */

namespace Netresearch\Kite\Service;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * A shell command service
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Service
 * @author     Jordi Boggiano <j.boggiano@seld.be>
 * @author     Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    https://github.com/composer/composer/blob/master/LICENSE Composer license
 * @link       http://www.netresearch.de
 */
class Filesystem
{
    /**
     * @var Console
     */
    protected $console;

    /**
     * Construct Filesystem
     *
     * @param Console $console The console
     */
    public function __construct(Console $console)
    {
        $this->console = $console;
    }


    /**
     * Remove a file
     *
     * @param string $file The file or dir
     *
     * @return bool
     */
    public function remove($file)
    {
        if (is_dir($file)) {
            return $this->removeDirectory($file);
        }

        if (file_exists($file)) {
            return unlink($file);
        }

        return false;
    }

    /**
     * Checks if a directory is empty
     *
     * @param string $dir The dir
     *
     * @return bool
     */
    public function isDirEmpty($dir)
    {
        $dir = rtrim($dir, '/\\');

        return count(glob($dir . '/*') ?: array()) === 0 && count(glob($dir . '/.*') ?: array()) === 2;
    }

    /**
     * Recursively remove a directory
     *
     * Uses the process component if proc_open is enabled on the PHP
     * installation.
     *
     * @param string $directory The dir
     *
     * @return bool
     */
    public function removeDirectory($directory)
    {
        if (!is_dir($directory)) {
            return true;
        }

        if (preg_match('{^(?:[a-z]:)?[/\\\\]+$}i', $directory)) {
            throw new \RuntimeException('Aborting an attempted deletion of ' . $directory . ', this was probably not intended, if it is a real use case please report it.');
        }

        if (!function_exists('proc_open')) {
            return $this->removeDirectoryPhp($directory);
        }

        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $cmd = sprintf('rmdir /S /Q %s', escapeshellarg(realpath($directory)));
        } else {
            $cmd = sprintf('rm -rf %s', escapeshellarg($directory));
        }

        $this->console->createProcess($cmd)->run();

        return !is_dir($directory);
    }

    /**
     * Recursively delete directory using PHP iterators.
     *
     * Uses a CHILD_FIRST RecursiveIteratorIterator to sort files
     * before directories, creating a single non-recursive loop
     * to delete files/directories in the correct order.
     *
     * @param string $directory The dir
     *
     * @return bool
     */
    public function removeDirectoryPhp($directory)
    {
        $it = new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS);
        $ri = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($ri as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        return rmdir($directory);
    }

    /**
     * Ensures a dir exists
     *
     * @param string $directory The dir
     *
     * @return void
     */
    public function ensureDirectoryExists($directory)
    {
        if (!is_dir($directory)) {
            if (file_exists($directory)) {
                throw new \RuntimeException(
                    $directory . ' exists and is not a directory.'
                );
            }
            if (!@mkdir($directory, 0777, true)) {
                throw new \RuntimeException(
                    $directory . ' does not exist and could not be created.'
                );
            }
        }
    }

    /**
     * Copy then delete is a non-atomic version of {@link rename}.
     *
     * Some systems can't rename and also don't have proc_open,
     * which requires this solution.
     *
     * @param string $source The source
     * @param string $target The target
     *
     * @return void
     */
    public function copyThenRemove($source, $target)
    {
        $this->copy($source, $target);
        $this->removeDirectoryPhp($source);
    }

    /**
     * Copy a file or folder to a target destination
     *
     * @param string $source The source
     * @param string $target The target
     *
     * @return void
     */
    public function copy($source, $target)
    {
        $path = realpath($source);
        if (is_dir($path)) {
            $it = new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS);
            $ri = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::SELF_FIRST);

            $this->ensureDirectoryExists($target);

            foreach ($ri as $file) {
                $targetPath = $target . DIRECTORY_SEPARATOR . $ri->getSubPathName();
                if ($file->isDir()) {
                    $this->ensureDirectoryExists($targetPath);
                } else {
                    copy($file->getPathname(), $targetPath);
                }
            }
        } else {
            $this->ensureDirectoryExists(dirname($target));
            copy($source, $target);
        }
    }

    /**
     * Rename a file or folder to a target destination
     *
     * @param string $source The source
     * @param string $target The target
     *
     * @return void
     */
    public function rename($source, $target)
    {
        if (true === @rename($source, $target)) {
            return;
        }

        if (!function_exists('proc_open')) {
            return $this->copyThenRemove($source, $target);
        }

        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            // Try to copy & delete - this is a workaround for random "Access denied" errors.
            $command = sprintf('xcopy %s %s /E /I /Q', escapeshellarg($source), escapeshellarg($target));
            $result = $this->console->createProcess($command)->run();

            // clear stat cache because external processes aren't tracked by the php stat cache
            clearstatcache();

            if (0 === $result) {
                $this->remove($source);

                return;
            }
        } else {
            // We do not use PHP's "rename" function here since it does not support
            // the case where $source, and $target are located on different partitions.
            $command = sprintf('mv %s %s', escapeshellarg($source), escapeshellarg($target));
            try {
                $this->console->createProcess($command)->run();
                // clear stat cache because external processes aren't tracked by the php stat cache
                clearstatcache();
                return;
            } catch (\Exception $e) {
                // do copyThenRemove
            }
        }

        return $this->copyThenRemove($source, $target);
    }

    /**
     * Returns the shortest path from $from to $to
     *
     * @param string $from        From
     * @param string $to          To
     * @param bool   $directories if true, the source/target are considered to be directories
     *
     * @throws \InvalidArgumentException
     *
     * @return string
     */
    public function findShortestPath($from, $to, $directories = false)
    {
        if (!$this->isAbsolutePath($from) || !$this->isAbsolutePath($to)) {
            throw new \InvalidArgumentException(sprintf('$from (%s) and $to (%s) must be absolute paths.', $from, $to));
        }

        $from = lcfirst($this->normalizePath($from));
        $to = lcfirst($this->normalizePath($to));

        if ($directories) {
            $from .= '/dummy_file';
        }

        if (dirname($from) === dirname($to)) {
            return './' . basename($to);
        }

        $commonPath = $to;
        while (strpos($from . '/', $commonPath . '/') !== 0 && '/' !== $commonPath && !preg_match('{^[a-z]:/?$}i', $commonPath)) {
            $commonPath = strtr(dirname($commonPath), '\\', '/');
        }

        if (0 !== strpos($from, $commonPath) || '/' === $commonPath) {
            return $to;
        }

        $commonPath = rtrim($commonPath, '/') . '/';
        $sourcePathDepth = substr_count(substr($from, strlen($commonPath)), '/');
        $commonPathCode = str_repeat('../', $sourcePathDepth);

        return ($commonPathCode . substr($to, strlen($commonPath))) ?: './';
    }

    /**
     * Returns PHP code that, when executed in $from, will return the path to $to
     *
     * @param string $from        From
     * @param string $to          To
     * @param bool   $directories if true, the source/target are considered to be directories
     *
     * @throws \InvalidArgumentException
     *
     * @return string
     */
    public function findShortestPathCode($from, $to, $directories = false)
    {
        if (!$this->isAbsolutePath($from) || !$this->isAbsolutePath($to)) {
            throw new \InvalidArgumentException(sprintf('$from (%s) and $to (%s) must be absolute paths.', $from, $to));
        }

        $from = lcfirst($this->normalizePath($from));
        $to = lcfirst($this->normalizePath($to));

        if ($from === $to) {
            return $directories ? '__DIR__' : '__FILE__';
        }

        $commonPath = $to;
        while (strpos($from . '/', $commonPath . '/') !== 0 && '/' !== $commonPath && !preg_match('{^[a-z]:/?$}i', $commonPath) && '.' !== $commonPath) {
            $commonPath = strtr(dirname($commonPath), '\\', '/');
        }

        if (0 !== strpos($from, $commonPath) || '/' === $commonPath || '.' === $commonPath) {
            return var_export($to, true);
        }

        $commonPath = rtrim($commonPath, '/') . '/';
        if (strpos($to, $from . '/') === 0) {
            return '__DIR__ . ' . var_export(substr($to, strlen($from)), true);
        }
        $sourcePathDepth = substr_count(substr($from, strlen($commonPath)), '/') + $directories;
        $commonPathCode = str_repeat('dirname(', $sourcePathDepth) . '__DIR__' . str_repeat(')', $sourcePathDepth);
        $relTarget = substr($to, strlen($commonPath));

        return $commonPathCode . (strlen($relTarget) ? '.' . var_export('/' . $relTarget, true) : '');
    }

    /**
     * Checks if the given path is absolute
     *
     * @param string $path Path
     *
     * @return bool
     */
    public function isAbsolutePath($path)
    {
        return substr($path, 0, 1) === '/' || substr($path, 1, 1) === ':';
    }

    /**
     * Returns size of a file or directory specified by path. If a directory is
     * given, it's size will be computed recursively.
     *
     * @param string $path Path to the file or directory
     *
     * @throws \RuntimeException
     *
     * @return int
     */
    public function size($path)
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("$path does not exist.");
        }
        if (is_dir($path)) {
            return $this->directorySize($path);
        }

        return filesize($path);
    }

    /**
     * Normalize a path. This replaces backslashes with slashes, removes ending
     * slash and collapses redundant separators and up-level references.
     *
     * @param string $path Path to the file or directory
     *
     * @return string
     */
    public function normalizePath($path)
    {
        $parts = array();
        $path = strtr($path, '\\', '/');
        $prefix = '';
        $absolute = false;

        if (preg_match('{^([0-9a-z]+:(?://(?:[a-z]:)?)?)}i', $path, $match)) {
            $prefix = $match[1];
            $path = substr($path, strlen($prefix));
        }

        if (substr($path, 0, 1) === '/') {
            $absolute = true;
            $path = substr($path, 1);
        }

        $up = false;
        foreach (explode('/', $path) as $chunk) {
            if ('..' === $chunk && ($absolute || $up)) {
                array_pop($parts);
                $up = !(empty($parts) || '..' === end($parts));
            } elseif ('.' !== $chunk && '' !== $chunk) {
                $parts[] = $chunk;
                $up = '..' !== $chunk;
            }
        }

        return $prefix . ($absolute ? '/' : '') . implode('/', $parts);
    }

    /**
     * Get directory size
     *
     * @param string $directory The dir
     *
     * @return int
     */
    protected function directorySize($directory)
    {
        $it = new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS);
        $ri = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);

        $size = 0;
        foreach ($ri as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }
}
?>
