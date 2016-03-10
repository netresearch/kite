<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category Netresearch
 * @package  Netresearch\Kite\Test
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */

namespace Netresearch\Kite\Test;
use Netresearch\Kite\Console\Output\ConsoleOutput;
use Netresearch\Kite\Job;
use Netresearch\Kite\Service\Config;
use Netresearch\Kite\Service\Console;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Process\Process;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/Package.php';

/**
 * Base TestCase
 *
 * @category Netresearch
 * @package  Netresearch\Kite\Test
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */
abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string Path to /tmp workspace
     */
    private static $workspacePath;

    /**
     * Delete the workspace if it exists
     *
     * @return void
     */
    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        if (self::$workspacePath) {
            self::cmd('rm -rf ?', null, self::$workspacePath);
            self::$workspacePath = null;
        }
    }

    /**
     * Get a job mock
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|Job
     */
    protected function getJobMock($questionCallback = null)
    {
        $application = new Application();
        if ($questionCallback) {
            $questionHelperMock = $this->getMock('\Symfony\Component\Console\Helper\QuestionHelper', ['ask']);
            $questionHelperMock->expects($this->any())->method('ask')->willReturnCallback($questionCallback);
            $application->getHelperSet()->set($questionHelperMock);
        }

        $console = new Console(new Config());
        $console->setInput(new ArrayInput([]));
        $console->setOutput(new ConsoleOutput(ConsoleOutput::VERBOSITY_QUIET));
        $console->setDebugOutput(new ConsoleOutput(ConsoleOutput::VERBOSITY_QUIET));
        $console->setApplication($application);

        return new \Netresearch\Kite\Job($console);
    }

    /**
     * Create a project with single dependencies
     *
     * @param int $dependenciesDepth Depth of dependency tree to generate
     *
     * @return Package
     */
    protected function getProject($dependenciesDepth = 3)
    {
        static $project;

        $wsPath = self::$workspacePath ?: sys_get_temp_dir() . '/' .  uniqid('kite-checkout-test-');

        $projectPath = $wsPath . '/project';
        $remotesPath = $wsPath . '/remotes';
        $templatePath = $wsPath . '/.template';

        if (self::$workspacePath) {
            $this->cmd('rm -rf ? ?', null, $projectPath, $remotesPath);
            $this->cmd('cp -r ? ?', $templatePath, basename($projectPath), $wsPath);
            $this->cmd('cp -r ? ?', $templatePath, basename($remotesPath), $wsPath);
        } else {
            self::$workspacePath = $wsPath;
            mkdir($wsPath);
            mkdir($remotesPath);
            $project = $this->createProject($dependenciesDepth, $projectPath, $remotesPath);
            mkdir($templatePath);
            $this->cmd('cp -r ? ?', null, $projectPath, $templatePath);
            $this->cmd('cp -r ? ?', null, $remotesPath, $templatePath);
        }

        return $this->clonePackage($project);
    }

    /**
     * Clone a package and it's dependent packages
     *
     * @param Package $package The package
     *
     * @return Package
     */
    private function clonePackage(Package $package)
    {
        $clone = clone $package;
        $clonedDependencies = array();
        foreach ($package->dependencies as $dependentPackage) {
            $clonedDependencies[] = $this->clonePackage($dependentPackage);
        }
        $clone->dependencies = $clonedDependencies;
        return $clone;
    }

    /**
     * Create the project
     *
     * @param int $dependenciesDepth Depth of dependencies
     * @param string $projectPath    Path to project
     * @param string $remotesPath    Path to remotes
     *
     * @return Package
     */
    private function createProject($dependenciesDepth, $projectPath, $remotesPath)
    {
        $i = $dependenciesDepth;
        /** @var Package $lastPackage */
        $lastPackage = null;
        while ($i >= 0) {
            $isProject = $i === 0;
            $package = new Package();
            $package->name = 'netresearch/' . ($isProject ? 'project' : 'package-' . $i);
            $package->path = $projectPath . ($isProject ? '' : '/vendor/' . $package->name);
            $package->remote = $remotesPath . '/' . basename($package->name);
            mkdir($package->remote);

            $this->cmd('git init', $package->remote);
            $composerJson = "{\n" . '    "name": "' . $package->name . '"';
            if ($isProject) {
                file_put_contents($package->remote . '/kite.php', '<?php $this->loadPreset("common"); $this["workspace"] = "kite-workspace"; ?>');
                file_put_contents($package->remote . '/.gitignore', '/kite-workspace');
                $composerJson .= ",\n    \"type\": \"project\"";
                $composerJson .= ",\n    \"config\": {\"cache-files-ttl\": 0}";
                $composerJson .= ",\n    \"minimum-stability\": \"dev\"";
                $composerJson .= ",\n    \"repositories\": [";
                $dependentPackage = $lastPackage;
                do {
                    $composerJson .= "\n        {\"type\": \"git\", \"url\": \"{$dependentPackage->remote}\"},";
                } while ($dependentPackage = current($dependentPackage->dependencies));
                $composerJson = rtrim($composerJson, ',') . "\n    ]";
            }
            if ($lastPackage) {
                $composerJson .= ",\n" . '    "require": {';
                $composerJson .= "\n        \"{$lastPackage->name}\": \"dev-master\"";
                $composerJson = rtrim($composerJson, ',') . "\n    }";
                $package->dependencies[] = $lastPackage;
            }
            $composerJson .= "\n}";
            file_put_contents($package->remote . '/composer.json', $composerJson);
            $this->cmd('git add -A; git commit -nm \'Initial import\'', $package->remote);

            $lastPackage = $package;
            $i--;
        }

        $this->cmd('git clone ? ?', null, $lastPackage->remote, $lastPackage->path);
        $this->cmd('composer install --no-autoloader', $lastPackage->path);

        return $lastPackage;
    }

    /**
     * Execute a command
     *
     * @param string $cmd The command
     * @param string $cwd The working directory (workspacePath when null)
     *
     * @return string
     */
    protected function cmd($cmd, $cwd = null)
    {
        if (func_num_args() > 2) {
            $args = func_get_args();
            array_shift($args);
            array_shift($args);
            foreach ($args as $arg) {
                $cmd = preg_replace('/\?/', escapeshellarg($arg), $cmd, 1);
            }
        }
        if ($cwd && !is_dir($cwd)) {
            $this->fail("Dir $cwd doesn't exist");
        }
        $process = new Process($cmd, $cwd);
        //$process->setTty(true);
        $process->mustRun();
        return $process->getOutput();
    }
}

?>