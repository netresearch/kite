<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category Netresearch
 * @package  Netresearch\Kite\Workflow
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */

namespace Netresearch\Kite\Workflow;
use Netresearch\Kite\Workflow;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ClearCodeCaches
 *
 * @category Netresearch
 * @package  Netresearch\Kite\Workflow
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */
class ClearCodeCaches extends Workflow
{
    /**
     * Variable configuration
     *
     * @return array
     */
    protected function configureVariables()
    {
        return array(
            'webUrl' => array(
                'type' => 'string',
                'label' => 'URL to the current web root. Set this if you want to clear caches locally - otherwise this WF will clear the node(s) caches'
            ),
            'baseDir' => array(
                'type' => 'string',
                'default' => '{config["workspace"]}',
                'label' => 'Path relative to current application root and webUrl, where the temp script will be stored'
            )
        ) + parent::configureVariables();
    }


    /**
     * Override to assemble the tasks
     *
     * @return void
     */
    public function assemble()
    {
        $this->callback(
            function () {
                $scriptPath = $this->createScript();
                if ($webUrl = $this->get('webUrl')) {
                    $this->callScript($webUrl, $scriptPath);
                } else {
                    $this->scp($scriptPath, '{node}:{node.webRoot}/' . $scriptPath);
                    foreach ($this->get('nodes') as $node) {
                        /* @var \Netresearch\Kite\Node $node */
                        if ($webUrl = $node->get('webUrl', null)) {
                            $this->callScript($webUrl, $scriptPath);
                        } else {
                            $this->console->output("<warning>Node $node ({$node->get('id')}) has no webUrl set</warning>");
                        }
                    }
                }
            }
        );
    }

    /**
     * Create the script
     *
     * @return string The relative script path
     */
    protected function createScript()
    {
        $scriptPath = $this->get('baseDir') . '/ccc-' . uniqid() . '.php';
        file_put_contents(
            $scriptPath,
            '<?' . "php
            if (function_exists('clearstatcache')) {
                echo 'statcache|';
                clearstatcache(true);
            }
            if (function_exists('opcache_reset')) {
                echo 'opcache|';
                opcache_reset();
            }
            if (function_exists('apc_clear_cache')) {
                echo 'apc|';
                apc_clear_cache();
            }
            unlink(__FILE__);
            echo 'SUCCESS';
            ?>\n"
        );
        return $scriptPath;
    }

    /**
     * Call the script via web URL
     *
     * @param string $baseUrl    URL to web root
     * @param string $scriptPath Path to script (relative to baseUrl)
     *
     * @return void
     */
    protected function callScript($baseUrl, $scriptPath)
    {
        $url = rtrim($baseUrl, '/') . '/' . ltrim($scriptPath, '/');

        $this->console->output('Calling ' . $url, OutputInterface::VERBOSITY_DEBUG);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        $content = substr($response, curl_getinfo($ch, CURLINFO_HEADER_SIZE));

        $caches = explode('|', $content);
        if (array_pop($caches) !== 'SUCCESS') {
            $this->console->output("<error>Clearcache not run successfully</error>");
            $this->console->indent();
            $this->console->output($response, OutputInterface::VERBOSITY_DEBUG);
            $this->console->outdent();
        } else {
            if ($caches) {
                $this->console->output('Cleared code caches (' . implode(', ', $caches) . ')');
            } else {
                $this->console->output('No code caches found');
            }
        }
    }
}

?>
