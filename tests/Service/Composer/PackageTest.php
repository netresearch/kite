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

namespace Netresearch\Kite\Test\Service\Composer;

use Netresearch\Kite\Service\Composer\Package;
use Netresearch\Kite\Test\TestCase;

/**
 * Class PackageTest.
 *
 * @category Netresearch
 *
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 *
 * @link     http://www.netresearch.de
 */
class PackageTest extends TestCase
{
    /**
     * Get package.
     *
     * @param \Netresearch\Kite\Test\Package $package The test package
     * @param bool                           $isRoot  If package is root
     *
     * @return Package
     */
    protected function getPackage($package, $isRoot = false)
    {
        $job = $this->getJobMock();
        $mock = $this->getMock('\Netresearch\Kite\Service\Composer', null, [$job]);

        return new Package($mock, $package->path.'/composer.json', $isRoot);
    }

    /**
     * Test basic package information.
     *
     * @return void
     */
    public function testBasicInfo()
    {
        $project = $this->getProject(0);
        $package = $this->getPackage($project, true);

        $this->assertEquals($project->path, $package->path);
        $this->assertEquals('master', $package->branch);
        $this->assertEquals(['master', 'origin/master'], $package->branches);
        $this->assertEquals(['master' => 'origin/master'], $package->upstreams);
        $this->assertEquals($project->name, $package->name);
        $this->assertEquals($project->remote, $package->remote);
        $this->assertTrue($package->isRoot);
        $this->assertTrue($package->git);
        $this->assertEquals([], $package->requires);
    }

    /**
     * Test local only, remote only and remotely and locally available branches.
     *
     * @return void
     */
    public function testAdditionalBranches()
    {
        $project = $this->getProject(0);

        $this->cmd('git push origin master:remote', $project->path);
        $this->cmd('git checkout -b remoteAndLocal; git push -u origin remoteAndLocal; git checkout master', $project->path);
        $this->cmd('git checkout -b local', $project->path);

        $package = $this->getPackage($project);

        $this->assertFalse($package->isRoot);
        $this->assertEquals('local', $package->branch);
        $this->assertEquals(['local', 'master', 'remoteAndLocal', 'origin/master', 'origin/remote', 'origin/remoteAndLocal'], $package->branches);
        $this->assertEquals(['master' => 'origin/master', 'remoteAndLocal' => 'origin/remoteAndLocal'], $package->upstreams);
    }

    /**
     * Assert that a checked out tag is recognized.
     *
     * @return void
     */
    public function testTag()
    {
        $project = $this->getProject(0);

        $this->cmd("git tag -a 1.0.0 -m 'Tagging 1.0.0'", $project->path);
        $this->cmd('git checkout 1.0.0', $project->path);

        $package = $this->getPackage($project);

        $this->assertEquals('1.0.0', $package->tag);
        $this->assertNull($package->branch);
    }

    /**
     * Assert that a checked out tag is recognized.
     *
     * @return void
     */
    public function testRemoteTag()
    {
        $project = $this->getProject(0);

        $this->cmd("git tag -a 1.0.0 -m 'Tagging 1.0.0'", $project->remote);
        $this->cmd('git fetch; git checkout 1.0.0', $project->path);

        $package = $this->getPackage($project);

        $this->assertEquals('1.0.0', $package->tag);
        $this->assertNull($package->branch);
    }
}
