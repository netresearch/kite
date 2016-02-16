<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Workflow
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */

namespace Netresearch\Kite\Workflow;
use Netresearch\Kite\Service\Factory;
use Netresearch\Kite\Task;

use Netresearch\Kite\Workflow;
use Netresearch\Kite\Exception;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Run a task for each stage until the selected stage
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Workflow
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */
class StageSelect extends Workflow
{
    protected $message;

    /**
     * @var Task
     */
    protected $task;

    /**
     * Configures the arguments/options
     *
     * @return array
     */
    protected function configureVariables()
    {
        return array(
            'stage' => array(
                'type' => 'string',
                'argument' => true,
                'label' => 'Preselect a stage - otherwise you\'ll be asked'
            ),
            'stages' => array(
                'type' => 'array',
                'required' => true,
                'label' => 'Array of stages - keys are the stages names and the values are arrays which\'s contain variables that will be set when the according stage was selected'
            ),
            'sliding' => array(
                'type' => 'bool',
                'label' => 'Whether all stages until the selected should be used'
            ),
            'task' => array(
                'type' => 'array',
                'required' => true,
                'label' => 'The task to invoke for each selected stage'
            ),
            'message' => array(
                'type' => 'string',
                'label' => 'Message to output before each executed stage - %s will be replaced with stage name'
            ),
            'question' => array(
                'type' => 'string',
                'default' => 'Select stage',
                'label' => 'Question to ask before stage select'
            ),
            '--'
        ) + parent::configureVariables();
    }

    /**
     * Don't show message before all stages but before each stage
     *
     * @return void
     */
    public function preview()
    {
    }

    /**
     * Override to create the tasks from the according options
     *
     * @param string $name  Variable name
     * @param mixed  $value Variable value
     *
     * @return void
     */
    public function offsetSet($name, $value)
    {

        if ($name === 'task') {
            $this->task = $value;
            return;
        }
        parent::offsetSet($name, $value);
    }


    /**
     * Called from parent task as soon as task is ready to run - which doesn't
     * necessarely mean that it'll be run.
     *
     * @return void
     */
    protected function initialize()
    {
        parent::initialize();

        $this->task = $this->prepare()->sub($this->expand($this->task));
        $this->job->addVariablesFromTask($this->task);
    }


    /**
     * Assemble this workflow
     *
     * @return void
     */
    public function assemble()
    {
        $this->callback(
            function () {
                $stages = $this->get('stages');
                $stageOptions = array();
                // Begin keys from 1
                foreach (array_keys($stages) as $i => $stage) {
                    $stageOptions[$i + 1] = $stage;
                }

                $selectedStage = $this->get('stage');
                if ($selectedStage !== null) {
                    if (!in_array($selectedStage, $stageOptions, true)) {
                        throw new Exception('Invalid stage');
                    }
                } else {
                    if (count($stageOptions) === 1) {
                        $selectedStage = $stageOptions[1];
                    } else {
                        $selectedStage = $this->choose($this->get('question'), $stageOptions);
                    }
                }

                $this->console->output("Selected stage <comment>$selectedStage</comment>", OutputInterface::VERBOSITY_VERBOSE);

                $selectedStages = array();
                if ($this->get('sliding')) {
                    foreach ($stageOptions as $stage) {
                        $selectedStages[] = $stage;
                        if ($stage === $selectedStage) {
                            break;
                        }
                    }
                } else {
                    $selectedStages[] = $selectedStage;
                }

                $message = $this->get('message');
                foreach ($selectedStages as $stage) {
                    if ($message) {
                        $this->console->output(
                            sprintf($message, "<comment>$stage</comment>")
                        );
                    }
                    $task = clone $this->task;
                    foreach ($stages[$stage] as $key => $value) {
                        // Avoid variables overriding parent variables, by prefixing with this
                        $task->set('this.' . $key, $value);
                    }
                    $this->addTask($task);
                }
            }
        );
    }
}
?>
