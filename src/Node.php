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

namespace Netresearch\Kite;

/**
 * Node - node (and nodes) is a special variable name, which is casted to this class.
 *
 * @category Netresearch
 *
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 *
 * @link     http://www.netresearch.de
 */
class Node extends Variables
{
    /**
     * Node constructor.
     *
     * @param Variables $parent Parent object (Task/Job/Workflow)
     */
    public function __construct(Variables $parent)
    {
        parent::__construct(
            $parent,
            [
                'user' => '',
                'pass' => '',
                'port' => '',
                // SCP/SSH URL
                'url'        => '{(this.user ? this.user ~ "@" : "") ~ this.host}',
                'sshOptions' => ' -A{this.port ? " -p " ~ this.port : ""}{this.pass ? " -o PubkeyAuthentication=no" : ""}',
                'scpOptions' => '{this.port ? " -P " ~ this.port : ""}{this.pass ? " -o PubkeyAuthentication=no" : ""}',
                'php'        => 'php', // PHP executable
                'webRoot'    => '{this.deployPath}/current',
                // commented out to trigger exceptions when those are empty:
                // 'webUrl' => 'http://example.com',
                // 'host' => 'example.com',
                // 'deployPath' => '/var/www'
            ]
        );
    }

    /**
     * Cast this to string - returns the url.
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->get('url');
    }
}
