<?php
/**
 * See class comment.
 *
 * PHP Version 5
 *
 * @category   Netresearch
 *
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 *
 * @link       http://www.netresearch.de
 */

namespace Netresearch\Kite\Task;

use Netresearch\Kite\Exception;
use Netresearch\Kite\Task;

/**
 * Run each task for each of an arrays element.
 *
 * @category   Netresearch
 *
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 *
 * @link       http://www.netresearch.de
 */
class IterateTask extends SubTask
{
    /**
     * @var string
     */
    protected $defaultAs = null;

    /**
     * @var string
     */
    protected $defaultKey = null;

    /**
     * @var array
     */
    private $array;

    /**
     * @var array
     */
    private $as;

    private $running = false;

    /**
     * Configure the options.
     *
     * @return array
     */
    protected function configureVariables()
    {
        return [
            'array' => [
                'type'     => 'array',
                'required' => true,
                'label'    => 'The array to iterate over',
            ],
            'as' => [
                'type'    => 'string|array',
                'default' => $this->defaultAs,
                'label'   => 'String with variable name to set the VALUEs to or array which\'s key to set the KEYs  and which\'s value to set the VALUEs to',
            ],
            'key' => [
                'type'    => 'string',
                'default' => $this->defaultKey,
                'label'   => 'Variable name to set the KEYs to (ignored when "as" doesn\'t provide both',
            ],
            '--',
        ] + parent::configureVariables();
    }

    /**
     * Get the array to iterate over.
     *
     * @return array
     */
    protected function getArray()
    {
        if ($this->array === null) {
            $this->array = $this->get('array');
            if (!is_array($this->array) && !$this->array instanceof \Traversable) {
                throw new Exception('Invalid array');
            }
        }

        return $this->array;
    }

    /**
     * Get the as (key => value) config.
     *
     * @return array
     */
    protected function getAs()
    {
        if ($this->as === null) {
            $as = $this->get('as', $this->defaultAs);
            if (is_array($as)) {
                $key = key($as);
                $value = $as[$key];
            } else {
                $key = $this->get('key', $this->defaultKey);
                $value = $as;
            }
            $this->as = [];
            foreach (['key', 'value'] as $name) {
                if ($$name) {
                    if ($this->has($$name)) {
                        throw new Exception('Variable '.$$name.' is already present');
                    }
                    $this->as[$name] = $$name;
                }
            }
        }

        return $this->as;
    }

    /**
     * Run the task.
     *
     * @param Task $task The task
     *
     * @return mixed|null The task return value or null when if failed or dry run
     */
    protected function runTask(Task $task)
    {
        if ($this->running) {
            return parent::runTask($task);
        }

        $this->running = true;

        $as = $this->getAs();

        foreach ($this->getArray() as $key => $value) {
            foreach ($as as $type => $name) {
                $this->set($name, $$type);
            }
            try {
                parent::runTask($task);
            } catch (\Exception $exception) {
                break;
            }
        }

        if (isset($exception) && $exception instanceof Exception\BreakException) {
            $message = $exception->getMessage();
            if ($message) {
                $this->console->output($task->expand($message));
            }
            unset($exception);
        }

        if (isset($key)) {
            foreach ($as as $name) {
                $this->remove($name);
            }
        }

        $this->running = false;

        if (isset($exception)) {
            throw $exception;
        }
    }
}
