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

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Execute a callback.
 *
 * @category   Netresearch
 *
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 *
 * @link       http://www.netresearch.de
 */
class CallbackTask extends \Netresearch\Kite\Task
{
    /**
     * Configures the options.
     *
     * @return array
     */
    protected function configureVariables()
    {
        return [
            'callback' => [
                'type'     => 'callable|string',
                'required' => true,
                'label'    => 'The callback or user function to run (@see GeneralUtility::callUserFunction())',
            ],
            '--',
        ] + parent::configureVariables();
    }

    /**
     * Execute the task.
     *
     * @return mixed
     */
    public function execute()
    {
        $callback = $this->get('callback');
        if (!is_array($callback) && !$callback instanceof \Closure) {
            if (strpos($callback, '::')) {
                $callback = explode('::', $callback);
            } elseif (strpos($callback, '->')) {
                $parts = explode('->', $callback);
                $instance = new $parts[0]();
                $callback = [$instance, $parts[1]];
            }
        }

        return call_user_func($callback, $this->getParent());
    }

    /**
     * Show an information of what will happen.
     *
     * @return void
     */
    public function preview()
    {
        parent::preview();
        $fn = $this->get('callback');
        if (is_string($fn)) {
            $name = $fn;
        } elseif (is_array($fn)) {
            $name = (is_object($fn[0]) ? get_class($fn[0]).'->' : $fn[0].'::').$fn[1];
        } else {
            $name = 'anonymous function';
        }
        $this->console->output('Calling '.$name, OutputInterface::VERBOSITY_DEBUG);
    }
}
