<?php
/**
 * See class comment.
 *
 * PHP Version 5
 *
 * @category Netresearch
 *
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 *
 * @link     http://www.netresearch.de
 */

namespace Netresearch\Kite\Workflow;

use Netresearch\Kite\Exception;
use Netresearch\Kite\Service\Descriptor;
use Netresearch\Kite\Task;
use Netresearch\Kite\Workflow;

/**
 * Renders the Task and Workflows reference.
 *
 * @internal Used to render the kite reference
 *
 * @category Netresearch
 *
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 *
 * @link     http://www.netresearch.de
 */
class RenderReference extends Workflow
{
    /**
     * @var \Composer\Autoload\ClassLoader[]
     */
    protected $loaders = [];

    /**
     * Configure variables.
     *
     * @return array
     */
    protected function configureVariables()
    {
        return [
            'file' => [
                'type'     => 'string',
                'option'   => true,
                'shortcut' => 'f',
                'required' => true,
                'label'    => 'File to write to',
            ],
        ] + parent::configureVariables();
    }

    /**
     * Assemble the task.
     *
     * @return void
     */
    public function assemble()
    {
        $this->callback(
            function () {
                $this->findLoaders();
                $file = $this->get('file');
                $this->console->getFilesystem()->ensureDirectoryExists(dirname($file));
                file_put_contents($file, $this->render());
            }
        );
    }

    /**
     * Render the reference.
     *
     * @return string
     */
    public function render()
    {
        $descriptor = new Descriptor();
        $lines = [
            '.. header::',
            '',
            '   .. image:: ../res/logo/logo.png',
            '      :width: 200 px',
            '      :alt: Kite',
            '',
            '****************************',
            'Kite: Make your projects fly',
            '****************************',
            '',
            '===========================',
            'Task and Workflow reference',
            '===========================',
            '',
            '.. sidebar:: Navigation',
            '',
            '   `Back to manual <../README.rst>`_',
            '',
            '   .. contents::',
            '      :depth: 2',
            '',
        ];
        $commonVars = $this->getCommonVariables();
        $lines[] = 'Common options';
        $lines[] = '==============';
        $lines[] = 'The following options are available on the most tasks and workflows (unless they deactivated them):';
        $lines[] = '';
        $this->renderVariables($lines, $commonVars, 'common');

        foreach (['task', 'workflow'] as $type) {
            $lines[] = '';
            $lines[] = ucfirst($type).'s';
            $lines[] = str_repeat('=', strlen($type) + 1);
            $lines[] = '';
            $taskObjects = $this->loadTaskObjects($type);
            foreach ($taskObjects as $name => $taskObject) {
                if ($taskObject instanceof self) {
                    continue;
                }
                $lines[] = '';
                $lines[] = $name;
                $lines[] = str_repeat('-', strlen($name));
                $lines[] = '';
                $lines[] = str_replace("\n", "\n\n", $descriptor->describeTask($taskObject));
                $lines[] = '';
                $variableConfig = $taskObject->get('_variableConfiguration');
                $taskVariables = [];
                $taskCommonVariables = [];
                foreach ($variableConfig as $configName => $config) {
                    if (is_array($config)) {
                        if (array_key_exists($configName, $commonVars) && $commonVars[$configName] === $config) {
                            $taskCommonVariables[] = $configName;
                        } else {
                            $taskVariables[$configName] = $config;
                        }
                    }
                }
                if ($taskVariables) {
                    $lines[] = 'Options';
                    $lines[] = '```````';
                    $lines[] = '';
                    $this->renderVariables($lines, $taskVariables, $type.'-'.$name);
                }
                if ($taskCommonVariables) {
                    $lines[] = 'Common options';
                    $lines[] = '``````````````';
                    $links = [];
                    foreach ($taskCommonVariables as $configName) {
                        $links[] = "|common-$configName|_";
                    }
                    $lines[] = implode(', ', $links);
                    $lines[] = '';
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Render the variables.
     *
     * @param array  $lines        The lines
     * @param array  $variables    The variables
     * @param string $anchorPrefix The anchor prefix
     *
     * @return void
     */
    protected function renderVariables(&$lines, $variables, $anchorPrefix)
    {
        $lines[] = '.. list-table::';
        $lines[] = '   :header-rows: 1';
        $lines[] = '   :widths: 5 5 5 5 80';
        $lines[] = '';
        $keys = ['type', 'default', 'required', 'label'];
        $lines[] = '   * - Name';
        foreach ($keys as $key) {
            $lines[] = '     - '.ucfirst($key);
        }

        foreach ($variables as $configName => $config) {
            if (!is_array($config)) {
                continue;
            }
            $lines[] = '   * - ';
            $lines[] = '';
            $lines[] = '       .. |'.$anchorPrefix.'-'.$configName.'| replace:: '.$configName;
            $lines[] = '';
            $lines[] = '       .. _'.$anchorPrefix.'-'.$configName.':';
            $lines[] = '';
            $lines[] = '       '.$configName;
            $lines[] = '';
            foreach ($keys as $key) {
                if (!array_key_exists($key, $config)) {
                    $value = '\\-';
                } elseif ($key === 'default') {
                    $v = $config[$key];
                    $value = $v === null ? 'null' : ($v === true ? 'true' : ($v === false ? 'false' : $v));
                    if (is_string($value)) {
                        $value = ':code:`'.$value.'`';
                    }
                } elseif ($key === 'required') {
                    $value = $config[$key] === true ? 'X' : $config[$key];
                } else {
                    $value = $config[$key];
                }
                if (is_array($value)) {
                    $value = "\n\n       .. code::php\n\n           ".str_replace("\n", "\n\n           ", call_user_func('print'.'_r', $value, true))."\n\n";
                } else {
                    $value = str_replace("\n", "\n\n       ", $value);
                }
                $value = preg_replace('/\{@see\s+('.implode('|', array_keys($variables)).')\}/', '|'.$anchorPrefix.'-$1|_', $value);
                $lines[] = '     - '.$value;
            }
        }
        $lines[] = '';
    }

    /**
     * Get the common variables.
     *
     * @return mixed
     */
    protected function getCommonVariables()
    {
        static $commonVars;
        if (!is_array($commonVars)) {
            $className = 'NetresearchKiteTask'.uniqid();
            eval('class '.$className.' extends \\Netresearch\\Kite\\Task {}');
            $instance = new $className($this);
            $commonVars = $instance->get('_variableConfiguration');
        }

        return $commonVars;
    }

    /**
     * Load task objects.
     *
     * @param string $type workflow or task
     *
     * @return Task[]
     */
    protected function loadTaskObjects($type)
    {
        $objects = [];
        $requiredType = 'Netresearch\\Kite\\'.ucfirst($type);
        foreach ($this->factory->getNamespaces($type) as $namespace) {
            $namespaceLength = strlen($namespace);
            if ($dir = $this->findDirectoryForNamespace($namespace)) {
                foreach ($this->loadFilesInDirectory($dir) as $file) {
                    if (substr($file, -4) !== '.php') {
                        continue;
                    }
                    $class = $namespace.'\\'.substr(strtr($file, '/', '\\'), 0, -4);
                    $reflectionClass = new \ReflectionClass($class);
                    if (!$reflectionClass->isSubclassOf($requiredType) || !$reflectionClass->isInstantiable()) {
                        continue;
                    }
                    $namePart = substr($reflectionClass->getName(), $namespaceLength + 1);
                    $parts = [];
                    foreach (explode('\\', $namePart) as $part) {
                        $parts[] = lcfirst($part);
                    }
                    $name = implode('-', $parts);
                    if ($type === 'task') {
                        $name = substr($name, 0, -4);
                    }
                    if (!array_key_exists($name, $objects)) {
                        $objects[$name] = new $class($this);
                    }
                }
            } else {
                $this->output("<error>No {$type}s found in namespace $namespace</error>");
            }
        }
        ksort($objects);

        return $objects;
    }

    /**
     * Find the loaders.
     *
     * @return void
     */
    protected function findLoaders()
    {
        $autoloadFunctions = spl_autoload_functions();
        if (!is_array($autoloadFunctions)) {
            throw new Exception('No autoloaders registered');
        }
        foreach ($autoloadFunctions as $autoloadFunction) {
            if (!is_array($autoloadFunction) || !$autoloadFunction[0] instanceof \Composer\Autoload\ClassLoader) {
                throw new Exception('Can only work with composer autoloaders');
            }
            $this->loaders[] = $autoloadFunction[0];
        }
    }

    /**
     * Get the files within a directory.
     *
     * @param string $dir The directory
     *
     * @return array
     */
    private function loadFilesInDirectory($dir)
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        $files = [];
        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isFile()) {
                // Add a new element to the array of the current file name.
                $files[] = $iterator->getSubPathName();
            }
        }

        return $files;
    }

    /**
     * Find the base directory of a namespace.
     *
     * @param string $namespace The namespace
     *
     * @return string
     */
    private function findDirectoryForNamespace($namespace)
    {
        foreach ($this->loaders as $loader) {
            // PSR-4 lookup
            $logicalPathPsr4 = trim(strtr($namespace, '\\', DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);

            foreach ($loader->getPrefixesPsr4() as $prefix => $dirs) {
                $length = strlen($prefix);
                if (substr($namespace, 0, $length) !== $prefix) {
                    continue;
                }
                foreach ($dirs as $dir) {
                    if (file_exists($file = $dir.DIRECTORY_SEPARATOR.substr($logicalPathPsr4, $length))) {
                        return $file;
                    }
                }
            }

            // PSR-4 fallback dirs
            foreach ($loader->getFallbackDirsPsr4() as $dir) {
                if (file_exists($file = $dir.DIRECTORY_SEPARATOR.$logicalPathPsr4)) {
                    return $file;
                }
            }

            // PSR-0 lookup
            if (false !== $pos = strrpos($namespace, '\\')) {
                // namespaced class name
                $logicalPathPsr0 = substr($logicalPathPsr4, 0, $pos + 1)
                    .strtr(substr($logicalPathPsr4, $pos + 1), '_', DIRECTORY_SEPARATOR);
            } else {
                // PEAR-like class name
                $logicalPathPsr0 = strtr($namespace, '_', DIRECTORY_SEPARATOR);
            }

            foreach ($loader->getPrefixes() as $prefix => $dirs) {
                if (0 === strpos($namespace, $prefix)) {
                    foreach ($dirs as $dir) {
                        if (file_exists($file = $dir.DIRECTORY_SEPARATOR.$logicalPathPsr0)) {
                            return $file;
                        }
                    }
                }
            }

            // PSR-0 fallback dirs
            foreach ($loader->getFallbackDirs() as $dir) {
                if (file_exists($file = $dir.DIRECTORY_SEPARATOR.$logicalPathPsr0)) {
                    return $file;
                }
            }

            // PSR-0 include paths.
            if ($file = stream_resolve_include_path($logicalPathPsr0)) {
                return $file;
            }
        }
    }
}
