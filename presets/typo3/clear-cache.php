<?php
/**
 * Clear TYPO3 Cache
 *
 * PHP Version 5
 *
 * @category Netresearch
 * @package  Kite
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @author   Torsten Fink <torsten.fink@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */

require __DIR__ . '/bootstrap.php';

$dataHandler = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Core\DataHandling\DataHandler');
$dataHandler->stripslashes_values = 0;

if (!$typo3VersionIsMinimum7) {
    $dataHandler->start(array(), array());
}

$dataHandler->admin = true;

foreach (explode(',', 'system,all,pages') as $cmd) {
    echo "Clearing cache <info>$cmd</info>\n";
    $dataHandler->clear_cacheCmd($cmd);
}

?>
