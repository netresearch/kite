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

namespace Netresearch\Kite\Service;

use Netresearch\Kite\Exception;

/**
 * Central config class.
 *
 * @category Netresearch
 *
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 *
 * @link     http://www.netresearch.de
 */
class Config extends \ArrayObject
{
    /**
     * Config constructor.
     */
    public function __construct()
    {
        parent::__construct(
            [
                'jobs' => [],
            ]
        );
    }

    /**
     * Include the main or any other config file.
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
     * Load a preset.
     *
     * @param string $name Basename of the file (lowerCamelCase)
     *
     * @return void
     */
    public function loadPreset($name)
    {
        include dirname(dirname(__DIR__)).'/presets/'.$name.'.php';
    }

    /**
     * Get config of a particular or all jobs.
     *
     * @param string $job The job name
     *
     * @return array
     */
    public function getJobConfiguration($job = null)
    {
        if ($job) {
            if (!array_key_exists($job, $this['jobs'])) {
                throw new Exception("Job $job is not configured");
            }

            return $this['jobs'][$job];
        }

        return $this['jobs'];
    }

    /**
     * Configure a job.
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

    /**
     * Recursively merge an array into another.
     *
     * @param array $to   To (by reference)
     * @param array $from From
     *
     * @return void
     */
    public function merge(array &$to, array $from)
    {
        foreach ($from as $key => $fromValue) {
            if (is_numeric($key)) {
                $to[] = $fromValue;
            } elseif (isset($to[$key]) && is_array($to[$key]) && is_array($fromValue)) {
                $this->merge($to[$key], $fromValue);
            } else {
                $to[$key] = $fromValue;
            }
        }
        reset($to);
    }
}
