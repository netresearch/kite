<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category Netresearch
 * @package  Netresearch\Kite\Test\Workflow\Composer
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */

namespace Netresearch\Kite\Test\Workflow\Composer;
use Netresearch\Kite\Test\Package;
use Netresearch\Kite\Test\TestCase;
use Netresearch\Kite\Workflow\Composer\Checkout;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Class CheckoutTest
 *
 * @category Netresearch
 * @package  Netresearch\Kite\Test\Workflow\Composer
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */
class CheckoutTest extends TestCase
{
    /**
     * Provide scenarios for the checkout stuff
     *
     * @return array
     */
    public static function provideCheckoutScenarios()
    {
        $branch = 'testbranch';
        return [
            [
                $branch,
                function (Package $package) use ($branch) {
                    // Branch is available on remote
                    self::cmd('git checkout -b ?; git checkout master', $package->remote, $branch);
                }
            ],
            [
                $branch,
                function (Package $package) use ($branch) {
                    // Branch is available locally but not checked out
                    self::cmd('git checkout -b ?; git checkout master', $package->path, $branch);
                }
            ],
            [
                $branch,
                function (Package $package) use ($branch) {
                    // Branch is available locally and checked out
                    self::cmd('git checkout -b ?', $package->path, $branch);
                }
            ]
        ];
    }

    protected function runWorkflow(array $options = [])
    {
        $job = $this->getJobMock(
            function (InputInterface $input, OutputInterface $output, Question $question) {
                $question = strip_tags($question->getQuestion());
                if (substr($question, 0, 4) === 'Fix ') {
                    return true;
                } elseif (strpos($question, 'Select branch to create new branch') === 0) {
                    return 'master';
                } else {
                    $this->fail('Unknown question');
                }
                return null;
            }
        );
        $workflow = new Checkout($job);
        $workflow->setFromArray($options);
        $workflow->assemble();
        $workflow->run();
    }

    /**
     * Test the checkout: Checkout one package and assert, that all other packages
     * are checked out at the given branch and the dependencies have been changed
     *
     * @param string   $branch   The branch
     * @param callable $scenario The scenario
     *
     * @dataProvider provideCheckoutScenarios
     *
     */
    public function testCheckout($branch, $scenario)
    {
        $project = $lastPackage = $this->getProject();
        $allPackages = [];
        while ($lastPackage->dependencies) {
            $allPackages[] = $lastPackage;
            $lastPackage = current($lastPackage->dependencies);
        }

        call_user_func($scenario, $lastPackage);

        $this->runWorkflow(['branch' => $branch]);

        foreach ($allPackages as $package) {
            if ($package->dependencies) {
                $composerJson = json_decode(file_get_contents($package->path . '/composer.json'), true);
                $this->assertEquals('dev-' . $branch, current($composerJson['require']));
            }
            $this->assertContains(
                '* ' . $branch,
                explode("\n", $this->cmd('git branch -a', $package->path))
            );
            $this->assertContains(
                '  ' . $branch,
                explode("\n", $this->cmd('git branch -a', $package->remote))
            );
        }
    }

    /**
     * Test the checkout with merging the previous checked out branch into the branch
     * to check out
     *
     * @return void
     */
    public function testCheckoutWithMerge()
    {
        $this->markTestIncomplete('Test of the merge option is still missing');
    }

    /**
     * Provide configuration for testCheckoutWithWhitelists
     *
     * @return array
     */
    public static function provideWhitelists()
    {
        return [
            ['path', '\.', ['netresearch/project']],
            ['path', '(\.|vendor/netresearch/package-1)', ['netresearch/project', 'netresearch/package-1']],
            ['name', 'netresearch/(project|package-[1-2])', ['netresearch/project', 'netresearch/package-1', 'netresearch/package-2']],
            ['remote', '.+(project|package-[1-3])', ['netresearch/project', 'netresearch/package-1', 'netresearch/package-2', 'netresearch/package-3']],
        ];
    }

    /**
     * Test that only white listed packages are used
     *
     * @param string $type         The type
     * @param string $pattern      The pattern
     * @param array  $packageNames The expected package names
     *
     * @dataProvider provideWhitelists
     *
     * @return void
     */
    public function testCheckoutWithWhitelists($type, $pattern, $packageNames)
    {
        $project = $lastPackage = $this->getProject();
        $allPackages = [];
        while ($lastPackage->dependencies) {
            $allPackages[$lastPackage->name] = $lastPackage;
            $lastPackage = current($lastPackage->dependencies);
        }

        $branch = 'testbranch';
        $this->runWorkflow(['branch' => $branch, 'create' => true, 'whitelist' . ucfirst($type) . 's' => $pattern]);

        foreach ($allPackages as $package) {
            $expectedBranch = in_array($package->name, $packageNames, true) ? $branch : 'master';
            $this->assertContains(
                '* ' . $expectedBranch,
                explode("\n", $this->cmd('git branch -a', $package->path))
            );
        }
    }

    /**
     * Test that attempt to checkout packages not in white list fails
     *
     * @expectedException \Netresearch\Kite\Exception
     * @expectedExceptionMessage Package netresearch/package-2 is not in white list
     *
     * @return void
     */
    public function testDependenciesNotInWhitelist()
    {
        $project = $this->getProject();
        $this->runWorkflow(['branch' => 'testbranch', 'create' => true, 'whitelistNames' => 'netresearch/package-3']);
    }
}

?>
