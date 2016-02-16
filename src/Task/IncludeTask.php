<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Task
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */

namespace Netresearch\Kite\Task;

/**
 * Include a file
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Task
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */
class IncludeTask extends \Netresearch\Kite\Task
{
    /**
     * Configure the options
     *
     * @return array
     */
    protected function configureVariables()
    {
        return array(
            'file' => array(
                'type' => 'string',
                'required' => 'true',
                'label' => 'The file to include'
            ),
            '--'
        ) + parent::configureVariables();
    }

    /**
     * Clear the caches
     *
     * @return void
     */
    public function execute()
    {
        ob_start(
            function ($output) {
                $this->console->output($output, false);
                return '';
            }, 2, 0
        );
        include $this->get('file');
        ob_end_flush();
    }
}
?>
