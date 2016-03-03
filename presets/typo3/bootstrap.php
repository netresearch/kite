<?php
/**
 * Bootstrap TYPO3
 *
 * PHP Version 5
 *
 * @category Netresearch
 * @package  Kite
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */

define('TYPO3_MODE', 'BE');
define('TYPO3_cliMode', true);

$GLOBALS['MCONF']['name'] = '_CLI_lowlevel';

if (PHP_SAPI !== 'cli') {
    die('Access denied');
}

$currentDir = getcwd();
$selfDir = realpath(dirname($_SERVER['PHP_SELF']));
$subDir = '';
$selfDirPrev = '';

while ($selfDir !== $currentDir) {
    $subDir = basename($selfDir) . '/' . $subDir;
    $selfDirPrev = $selfDir;
    $selfDir = dirname($selfDir);
    if ($selfDirPrev === $selfDir) {
        define('PATH_site', getcwd() . DIRECTORY_SEPARATOR);
        $subDir = '';
        break;
    }
}

require 'typo3/sysext/core/Classes/Core/CliBootstrap.php';
\TYPO3\CMS\Core\Core\CliBootstrap::checkEnvironmentOrDie();

require 'typo3/sysext/core/Classes/Core/Bootstrap.php';
\TYPO3\CMS\Core\Core\Bootstrap::getInstance()
    ->baseSetup($subDir)
    ->loadConfigurationAndInitialize()
    ->loadTypo3LoadedExtAndExtLocalconf(true)
    ->applyAdditionalConfigurationSettings()
    ->initializeTypo3DbGlobal()
    ->loadExtensionTables(true)
    ->initializeBackendUser()
    ->initializeBackendAuthentication()
    ->initializeBackendUserMounts()
    ->initializeLanguageObject();

// Make sure output is not buffered, so command-line output and interaction can take place
\TYPO3\CMS\Core\Utility\GeneralUtility::flushOutputBuffers();
?>
