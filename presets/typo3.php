<?php
/**
 * TYPO3 specific configuration
 *
 * PHP Version 5
 *
 * @category Netresearch
 * @package  Kite
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */

/* @var $this \Netresearch\Kite\Service\Config */

$this->loadPreset('common');

$this['workspace'] = 'typo3temp/Kite';

$this['webUrl'] = null;

// Override this to use another php binary or set env vars
$this['php'] = 'php';

$this['jobs']['cc'] = [
    'description' => 'Clear caches',
    'tasks' => [
        [
            'type' => 'output',
            'message' => '<comment>Set $this[\'webUrl\'] in your kite config to clear code caches</comment>',
            'if' => '!config["webUrl"]'
        ],
        [
            'type' => 'shell',
            'command' => '{config["php"]} ' . __DIR__ . '/typo3/clear-cache.php',
            'processSettings' => ['pt' => true]
        ],
        [
            'workflow' => 'clearCodeCaches',
            'webUrl' => '{config["webUrl"]}',
            'if' => 'config["webUrl"]'
        ]
    ]
];

$this['jobs']['ccr'] = [
    'description' => 'Clear remote caches and migrate DB',
    'workflow' => 'stageSelect',
    'stages' => '{config["stages"]}',
    'task' => [
        'type' => 'sub',
        'tasks' => [
            ['workflow' => 'clearCodeCaches'],
            [
                'type' => 'scp',
                'from' => __DIR__ . '/typo3',
                'to' => '{node}:{node.deployPath}/current/{config["workspace"]}/typo3'
            ],
            [
                'type' => 'remoteShell',
                'command' => [
                    '{node.php} {config["workspace"]}/typo3/clear-cache.php',
                    '{node.php} {config["workspace"]}/typo3/schema-migration.php',
                    'rm -rf {config["workspace"]}'
                ],
                'cwd' => '{node.webRoot}',
                'processSettings' => ['pt' => true]
            ]
        ]
    ]
];

$this->merge(
    $this['jobs']['deploy']['task'],
    [
        'onReady' => '{config["jobs"]["ccr"]["task"]}',
        'rsync' => [
            'exclude' => [
                '/typo3temp/*',
                '/fileadmin',
                '/uploads',
                'build.xml',
                'dynamicReturnTypeMeta.json',
                '.git',
                'node_modules',
                'Gruntfile.js',
                'package.json',
                'composer.lock',
                // TYPO3 needs this file in the packages:
                // 'composer.json'
            ]
        ],
        'shared' => [
            'dirs' => [
                'fileadmin',
                'uploads'
            ]
        ]
    ]
);
?>
