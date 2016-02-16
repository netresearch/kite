<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category Netresearch
 * @package  Netresearch\Kite
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */

namespace Netresearch\Kite\Service;
use Netresearch\Kite\Exception;

/**
 * Central config class
 *
 * @category Netresearch
 * @package  Netresearch\Kite
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */
class Config extends \ArrayObject
{
    /**
     * Include the main or any other config file
     *
     * @param string $path Path to file
     *
     * @return void
     */
    public function loadConfigFile($path)
    {
        $absPath = stream_resolve_include_path($path);
        if ($absPath === false) {
            throw new Exception("File $path could not be found");
        }
        include $absPath;
    }

    /**
     * Load a preset
     *
     * @param string $name Basename of the file (lowerCamelCase)
     *
     * @return void
     */
    public function loadPreset($name)
    {
        include dirname(dirname(__DIR__)) . '/presets/' . $name . '.php';
    }

    /**
     * Get config of a particular or all jobs
     *
     * @param string $job The job name
     *
     * @return array
     */
    public function getJobConfiguration($job = null)
    {
        if (!isset($this['jobs'])) {
            $this['jobs'] = array();
        }
        if ($job) {
            if (!array_key_exists($job, $this['jobs'])) {
                throw new Exception("Job $job is not configured");
            }
            return $this['jobs'][$job];
        }
        return $this['jobs'];
    }

    /**
     * Configure a job
     *
     * @param string $name   The job name
     * @param array  $config The config
     *
     * @return void
     */
    public function configureJob($name, array $config)
    {
        $this['jobs'][$name] = $config;
    }
}
?>
