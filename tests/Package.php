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

namespace Netresearch\Kite\Test;

/**
 * Class Package.
 *
 * @category Netresearch
 *
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 *
 * @link     http://www.netresearch.de
 */
class Package
{
    /**
     * @var string package name
     */
    public $name;

    /**
     * @var string Path to installed package
     */
    public $path;

    /**
     * @var string Path to remote repo
     */
    public $remote;

    /**
     * @var Package[]
     */
    public $dependencies = [];
}
