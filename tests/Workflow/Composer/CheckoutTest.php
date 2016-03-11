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
        $project = $this->getProject();
        $allPackages = [];
        $lastPackage = $project;
        while ($lastPackage->dependencies) {
            $allPackages[] = $lastPackage;
            $lastPackage = current($lastPackage->dependencies);
        }

        call_user_func($scenario, $lastPackage);

        $job = $this->getJobMock(
            function (InputInterface $input, OutputInterface $output, Question $question) {
                if (substr(strip_tags($question->getQuestion()), 0, 4) === 'Fix ') {
                    return true;
                } else {
                    $this->fail('Unknown question');
                }
                return null;
            }
        );
        $workflow = new Checkout($job);
        $workflow->set('branch', $branch);
        $workflow->assemble();
        $workflow->run();

        foreach ($allPackages as $package) {
            if ($package->dependencies) {
                $composerJson = json_decode(file_get_contents($package->path . '/composer.json'), true);
                $this->assertEquals('dev-' . $branch, current($composerJson['require']));
            }
            $this->assertContains(
                '* ' . $branch,
                explode("\n", $this->cmd('git br -a', $package->path))
            );
            $this->assertContains(
                '  ' . $branch,
                explode("\n", $this->cmd('git br -a', $package->remote))
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
}

?>
