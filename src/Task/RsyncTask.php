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
 * RSync from/to the current node or all nodes if no current
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Task
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */
class RsyncTask extends \Netresearch\Kite\Task\ScpTask
{
    /**
     * @var array
     */
    protected $defaultOptions = array(
        'compress' => true,
        'rsh' => 'ssh{node.sshOptions}',
        'recursive' => true,
        'times' => true,
        // 'perms',
        'links' => true
        /* 'delete',
        'delete-excluded' */
    );

    /**
     * @var array
     */
    protected $defaultRules = array(
        'exclude' => array(
            '.*'
        ),
        'include' => array(
            '.htaccess'
        )
    );

    /**
     * @var
     */
    protected $options;

    /**
     * Configure the options
     *
     * @return array
     */
    protected function configureVariables()
    {
        return array(
            'exclude' => array(
                'type' => 'array',
                'default' => array(),
                'label' => 'Array with files/dirs to explicitely exclude'
            ),
            'include' => array(
                'type' => 'array',
                'default' => array(),
                'label' => 'Array with files/dirs to explicitely include'
            ),
            'options' => array(
                'type' => 'array',
                'default' => array(),
                'label' => 'Array with options for rsync: Elements with numeric keys or bool true values will be --switches.'
            ),
            '--'
        ) + parent::configureVariables();
    }

    /**
     * Run the task
     *
     * @return array|mixed
     */
    public function run()
    {
        if ($this->console->getOutput()->isQuiet()) {
            $this->defaultOptions['quiet'] = true;
        }
        if ($this->console->getOutput()->isVeryVerbose()) {
            $this->defaultOptions['verbose'] = true;
        }
        $this->options = array_merge($this->defaultOptions, $this->get('options'));

        if (!$this->shouldExecute()) {
            $this->options['dry-run'] = true;
        }

        $this->preview();
        return $this->execute();
    }

    /**
     * Get the rsync command
     *
     * @return string
     */
    protected function getScpCommand()
    {
        $rsyncCommand = 'rsync' . $this->renderOptions($this->options);

        foreach (array('include', 'exclude') as $type) {
            $rules = $this->getMergedArrayOption($type, $this->defaultRules[$type]);
            foreach ($rules as $rule) {
                $rsyncCommand .= ' --' . $type . '=' . escapeshellarg($rule);
            }
        }

        return $rsyncCommand;
    }

    /**
     * Merge an (optional) array option into defaults
     *
     * Given f.e. that the option has the following value:
     * array(
     *  'debug',
     *  'include' => '*',
     *  'quiet' => null,
     * )
     *
     * and the defaults are:
     * array(
     *  'compress',
     *  'quiet'
     * )
     *
     * the result would be:
     * array(
     *  'compress',
     *  'debug',
     *  'include' => '*'
     * )
     *
     * @param string $name     The name
     * @param array  $defaults The defaults
     *
     * @return array
     */
    protected function getMergedArrayOption($name, array $defaults)
    {
        $options = $defaults;
        foreach ($this->get($name) as $option => $value) {
            if (is_numeric($option)) {
                if (!in_array($value, $options)) {
                    $options[] = $value;
                }
            } else {
                if (!$value) {
                    if (array_key_exists($option, $options)) {
                        unset($options[$option]);
                    } else {
                        $i = array_search($option, $options);
                        if (is_numeric($i)) {
                            unset($options[$i]);
                        }
                    }
                } else {
                    $options[$option] = $value;
                }
            }
        }
        return $options;
    }
}
?>
