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

namespace Netresearch\Kite\Test\Workflow\Composer;

use Netresearch\Kite\Test\Package;
use Netresearch\Kite\Test\TestCase;
use Netresearch\Kite\Workflow\Composer\Checkout;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Class CheckoutTest.
 *
 * @category Netresearch
 *
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 *
 * @link     http://www.netresearch.de
 */
class CheckoutTest extends TestCase
{
    const QUESTION_FIX_REQUIREMENTS = 'Fix that\?.+';

    const QUESTION_CREATE_BRANCH_FROM = 'Select branch to create new branch \'{branch}\'.+';

    const QUESTION_MERGE_COMMIT_MESSAGE = 'Enter commit message:.+merg(ed?|ing).+';

    /**
     * Provide scenarios for the checkout stuff.
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
                    self::cmd('git push origin master:?', $package->path, $branch);
                },
            ],
            [
                $branch,
                function (Package $package) use ($branch) {
                    // Branch is available locally but not checked out
                    self::cmd('git checkout -b ?; git checkout master', $package->path, $branch);
                },
            ],
            [
                $branch,
                function (Package $package) use ($branch) {
                    // Branch is available locally and checked out
                    self::cmd('git checkout -b ?', $package->path, $branch);
                },
            ],
        ];
    }

    /**
     * Run a workflow.
     *
     * @param array $options
     * @param array $answers
     *
     * @return void
     */
    protected function runWorkflow(array $options = [], array $answers = [])
    {
        $job = $this->getJobMock(
            function (InputInterface $input, OutputInterface $output, Question $question) use ($options, $answers) {
                $question = rtrim(strip_tags($question->getQuestion()));
                foreach ($answers as $pattern => $answer) {
                    foreach ($options as $option => $value) {
                        $placeHolder = '{'.$option.'}';
                        if (strpos($pattern, $placeHolder) !== false) {
                            if (!is_string($value) && !is_numeric($value)) {
                                $this->fail('Can only replace string and number options');
                            }
                            $pattern = str_replace($placeHolder, preg_quote($value, '#'), $pattern);
                        }
                    }
                    if (preg_match('#^'.$pattern.'$#i', $question)) {
                        return $answer;
                    }
                }
                $this->fail('Unexpected question: "'.$question.'"');
            }
        );
        $workflow = new Checkout($job);
        $workflow->setFromArray($options);
        $workflow->assemble();
        $workflow->run();
    }

    protected function getCurrentRevision($project)
    {
        return trim($this->cmd('git rev-parse HEAD', $project->path));
    }

    protected function assertBranch(Package $package, $branch, $checkedOut = true)
    {
        $this->assertContains(
            ($checkedOut ? '*' : ' ').' '.$branch,
            explode("\n", $this->cmd('git branch -a', $package->path))
        );
        $this->assertContains(
            '  '.$branch,
            explode("\n", $this->cmd('git branch -a', $package->remote))
        );
    }

    /**
     * Test the checkout: Checkout one package and assert, that all other packages
     * are checked out at the given branch and the dependencies have been changed.
     *
     * @param string   $branch   The branch
     * @param callable $scenario The scenario
     *
     * @dataProvider provideCheckoutScenarios
     */
    public function testCheckoutSimple($branch, $scenario)
    {
        $project = $lastPackage = $this->getProject();
        $allPackages = [];
        while ($lastPackage->dependencies) {
            $allPackages[] = $lastPackage;
            $lastPackage = current($lastPackage->dependencies);
        }

        call_user_func($scenario, $lastPackage);

        $this->runWorkflow(
            ['branch' => $branch],
            [self::QUESTION_FIX_REQUIREMENTS => true, self::QUESTION_CREATE_BRANCH_FROM.'(project|package-[1-2]).+' => 'master']
        );

        foreach ($allPackages as $package) {
            if ($package->dependencies) {
                $composerJson = json_decode(file_get_contents($package->path.'/composer.json'), true);
                $this->assertEquals('dev-'.$branch, current($composerJson['require']));
                $this->assertBranch($package, $branch);
            }
        }
    }

    /**
     * Test the checkout with merging the previous checked out branch into the branch
     * to check out.
     *
     * @param Package $project
     */
    public function testCheckoutWithMerge(Package $project = null)
    {
        $project = $project ?: $this->getProject();

        $heads = new \stdClass();
        $heads->master = $this->getCurrentRevision($project);

        foreach (['topicbranch', 'featurebranch'] as $branch) {
            $this->runWorkflow(
                ['branch' => $branch, 'create' => true, 'whitelistNames' => 'netresearch/(project|package-1)'],
                [self::QUESTION_FIX_REQUIREMENTS => true, self::QUESTION_CREATE_BRANCH_FROM => 'master']
            );
            $heads->$branch = $this->cmd('git log --pretty=%H master..', $project->path);
        }

        // featurebranch is checked out
        // going to checkout topicbranch and merge in featurebranch
        $this->runWorkflow(
            ['branch' => 'topicbranch', 'merge' => true],
            [self::QUESTION_MERGE_COMMIT_MESSAGE => 'merged featurebranch']
        );
        $heads->current = $this->getCurrentRevision($project);

        $n = "\n";
        $this->assertEquals(
            '*   '.$heads->current.$n
            .'|\\  '
            .str_replace($n, $n.'| * ', $n.trim($heads->featurebranch))
            .str_replace($n, $n.'* | ', $n.trim($heads->topicbranch)).$n
            .'|/  '.$n
            .'* '.trim($heads->master),
            $this->cmd('git log --graph --pretty=format:\'%H\' --no-color', $project->path)
        );
    }

    /**
     * Check that merging still works even when CRLFs were introduced in the feature
     * branch.
     *
     * @return void
     */
    public function testCheckoutWithMergeAndCrlf()
    {
        $project = $this->getProject();

        $this->cmd('git checkout -b featurebranch', $project->path);
        file_put_contents(
            $composer = $project->path.'/composer.json',
            str_replace("\n", "\r\n", file_get_contents($composer))
        );
        $this->cmd('git config core.autocrlf false', $project->path);
        $this->cmd('git commit -anm \'Changing EOL style to CRLF\'', $project->path);
        $this->cmd('git push origin featurebranch', $project->path);
        $this->cmd('git checkout master', $project->path);

        $this->testCheckoutWithMerge($project);
    }

    /**
     * Test that conflicts in composer.json outside the require object are detected
     * and an exception is thrown.
     *
     * @expectedException \Netresearch\Kite\Exception
     * @expectedExceptionCode 1458307516
     *
     * @return void
     */
    public function testCheckoutWithUnsolvableMergeConflictInComposerJson()
    {
        $project = $this->getProject();

        foreach (['topicbranch', 'featurebranch'] as $i => $branch) {
            $this->runWorkflow(
                ['branch' => $branch, 'create' => true, 'whitelistNames' => 'netresearch/(project|package-1)'],
                [self::QUESTION_FIX_REQUIREMENTS => true, self::QUESTION_CREATE_BRANCH_FROM => 'master']
            );
            file_put_contents(
                $project->path.'/composer.json',
                str_replace(
                    '"minimum-stability": "dev"',
                    '"minimum-stability": "'.($i ? 'stable' : 'beta').'"',
                    file_get_contents($project->path.'/composer.json')
                )
            );
            $this->cmd('git commit -anm "Introducing conflict in '.$branch.'"; git push', $project->path);
        }

        // featurebranch is checked out
        // going to checkout topicbranch and merge in featurebranch
        $this->runWorkflow(['branch' => 'topicbranch', 'merge' => true]);
    }

    /**
     * Test that conflicts in composer.json outside the require object are detected
     * and an exception is thrown.
     *
     * @expectedException \Netresearch\Kite\Exception
     * @expectedExceptionCode 1458307785
     *
     * @return void
     */
    public function testCheckoutWithConflictsBesidesComposerJson()
    {
        $project = $this->getProject();

        foreach (['topicbranch', 'featurebranch'] as $branch) {
            $this->runWorkflow(
                ['branch' => $branch, 'create' => true, 'whitelistNames' => 'netresearch/(project|package-1)'],
                [self::QUESTION_FIX_REQUIREMENTS => true, self::QUESTION_CREATE_BRANCH_FROM => 'master']
            );
            file_put_contents($project->path.'/conflictingFile.txt', $branch);
            $this->cmd('git add -A; git commit -nm "Introducing conflict in '.$branch.'"; git push', $project->path);
        }

        // featurebranch is checked out
        // going to checkout topicbranch and merge in featurebranch
        $this->runWorkflow(['branch' => 'topicbranch', 'merge' => true]);
    }

    /**
     * Provide configuration for testCheckoutWithWhitelists.
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
     * Test that only white listed packages are used.
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
        $this->runWorkflow(
            ['branch' => $branch, 'create' => true, 'whitelist'.ucfirst($type).'s' => $pattern],
            [self::QUESTION_FIX_REQUIREMENTS => true, self::QUESTION_CREATE_BRANCH_FROM => 'master']
        );

        foreach ($allPackages as $package) {
            $expectedBranch = in_array($package->name, $packageNames, true) ? $branch : 'master';
            $this->assertContains(
                '* '.$expectedBranch,
                explode("\n", $this->cmd('git branch -a', $package->path))
            );
        }
    }

    /**
     * Test that attempt to checkout packages not in white list fails.
     *
     * @expectedException \Netresearch\Kite\Exception
     * @expectedExceptionMessage Package netresearch/package-2 is not in white list
     *
     * @return void
     */
    public function testDependenciesNotInWhitelist()
    {
        $project = $this->getProject();
        $this->runWorkflow(
            ['branch' => 'testbranch', 'create' => true, 'whitelistNames' => 'netresearch/package-3'],
            [self::QUESTION_CREATE_BRANCH_FROM => 'master']
        );
    }
}
