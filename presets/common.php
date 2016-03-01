<?php
/**
 * Common configuration
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

$this['stages'] = [
    'staging' => [
        'branch' => '{replace("/", "-", composer.rootPackage.name)}-staging',
        'merge' => true,
        // add nodes or node in your config
    ],
    'production' => [
        'branch' => 'master',
        // add nodes or node in your config
    ]
];

$this['jobs'] = [
    'foreach-package' => [
        'description' => 'Execute a command for each package',
        'options' => [
            'git' => [
                'type' => 'boolean',
                'label' => 'Whether to work only on git packages'
            ]
        ],
        'arguments' => [
            'cmd' => [
                'type' => 'string',
                'required' => true,
                'label' => 'The command to execute'
            ]
        ],
        'task' => [
            'type' => 'callback',
            'callback' => function (\Netresearch\Kite\Job $job) {
                $git = $job->get('git');
                $command = $job->get('cmd');
                foreach ($job->get('composer.packages') as $package) {
                    if (!$git || $package->git) {
                        $job->console->output("<info>Entering <comment>{$package->path}</comment></info>");
                        $job->shell($command, $package->path, null, ['tty' => true]);
                    }
                }
            }
        ]
    ],
    'merge' => [
        'description' => 'Merge all current git packages into current project branch',
        'workflow' => 'Netresearch\Kite\Workflow\Composer\Merge'
    ],
    'checkout' => [
        'description' => 'Checkout all packages with the given branch at this branch and update the dependencies',
        'workflow' => 'Netresearch\Kite\Workflow\Composer\Checkout'
    ],
    'diagnose' => [
        'description' => 'Show status of packages',
        'workflow' => 'Netresearch\Kite\Workflow\Composer\Diagnose'
    ],
    'deploy' => [
        'description' => 'Deploy to a stage',
        'workflow' => 'stageSelect',
        'stages' => '{config["stages"]}',
        'task' => [
            'workflow' => 'Netresearch\Kite\Workflow\Deployment',
        ]
    ],
    'rollout' => [
        'description' => 'Roll out along stages',
        'workflow' => 'stageSelect',
        'stages' => '{config["stages"]}',
        'question' => 'Select stage until which to rollout',
        'message' => '<step>Deploying to %s</step>',
        'task' => '{config["jobs"]["deploy"]["task"]}',
        'sliding' => true
    ]
] + $this['jobs'];
?>
