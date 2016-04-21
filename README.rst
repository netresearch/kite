.. header::

    .. image:: res/logo/logo.png
       :width: 200 px
       :alt: Kite

****************************
Kite: Make your projects fly
****************************

.. image:: http://img.shields.io/travis/netresearch/kite.svg?style=flat-square
    :target: https://travis-ci.org/netresearch/kite
.. image:: https://img.shields.io/packagist/v/netresearch/kite.svg?style=flat-square
    :target: https://packagist.org/packages/netresearch/kite
.. image:: https://img.shields.io/scrutinizer/g/netresearch/kite.svg?style=flat-square
    :target: https://scrutinizer-ci.com/g/netresearch/kite/?branch=master

.. role:: php(code)
    :language: php

Kite is a build and automation tool inspired by TYPO3.Surf, written in PHP and utilizing PHP for configuration.
It's...

- easy to use
    - Kite ships with several preconfigured tasks, workflows and presets - just use a preset and be done.
    - Jobs and workflows are available on the command line and there's also :code:`--help` available for each of them.
- flexible
    - The configuration can be completely done by arrays, fiddling your tasks together or by workflow classes that have all the tasks available as methods or both.
    - Jobs, workflows and tasks can easily be reused at any point - new jobs can be composed of any of them.
    - The variable system provides a JavaScript like variable inheritance: Sub tasks can access variables from parent but can set them on their own as well
    - Jobs and workflows can expose variables as command line options and arguments.
    - Advanced logic during execution possible by using `Symfony Expression Language <http://symfony.com/doc/current/components/expression_language/index.html>`_
- node based
    - Unlimited number of remote targets possible
    - Nodes can be set globally or only for specific (sub) tasks
    - Remote tasks operate on all current nodes
- safe
    - Everything can be :code:`--dry-run` to preview what happens (yet the tasks to include need to be configured)
    - The complete debug output of previous tasks can be viewed with :code:`kite log`

.. contents::
    :backlinks: top

.. topic:: Appendix

    - `Task and workflow reference <docs/reference.rst>`_
    
============
Installation
============

You can install kite globally (recommended) or per project

Prerequesites
=============

Locally
-------
- PHP 5.4+ (for the composer installation, the application can have lower versions)
- Linux shell (windows might work but is untested)
- SSH/SCP installed (for working on nodes)
- git installed (if you use git tasks)
- composer installed (if you use composer tasks)

Remote
------
- PHP
- SSH access

Global installation
===================

.. code:: bash

    composer global require "kite=^1.2.0"
    ~/.composer/vendor/bin/kite -V

Per project installation
========================

.. code:: bash

    cd /var/www/project
    composer require "kite=^1.2.0"
    vendor/bin/kite -V

=============
Configuration
=============

Concepts
========
- Tasks
    - Smallest, predefined steps
    - See the `task reference <docs/reference.rst#tasks>`_ for tasks shipped with kite
- Workflows
    - Special kind of task that allows to composer it's subtasks in a class
    - Top level workflows can expose command line arguments and options
    - See the `workflow reference <docs/reference.rst#workflows>`_ for workflows shipped with kite
- Jobs
    - Outermost kind of task
    - Available as commands on command line
    - Set of tasks and/or workflows defined in arrays (in arbitrary depth)
    - Configurable command line arguments and options
- Presets
    - Configuration presets (including f.i. common jobs)
- Configuration file (typo3conf/Kite.php, app/etc/kite.php, kite.php)
    - Defines the jobs; can load and override presets

Variables
=========
The fact that all of the configured tasks are to be ran automated, introduces the
need for a variable system that allows you to read from dynamic configurations or
change it. Kite provides a basic syntax to access those variables from within
strings (all options of tasks, nodes etc.):

Each string *inside curly braces* inside an option string are evaluated as
`Symfony Expression Language <http://symfony.com/doc/current/components/expression_language/index.html>`_
expressions - f.i.

.. code:: php

    <?php
    $this['messages'] = (object) ['default' => 'Hello world'];
    $this['jobs']['echo'] = [
        'description' => 'Output the default message from (\{config["messages"].default\})',
        'task' => [
            'type' => 'output',
            'message' => '{config["messages"].default}'
        ]
    ];

As you see above, by quoting the braces, you can avoid that the expression is evaluated.
Please see the `Symfony Expression Language Syntax <http://symfony.com/doc/current/components/expression_language/syntax.html>`_
for help on how to use the expressions.

Variable scopes
---------------
The variable scopes are very similar to those in JavaScript. This means that you can
access all variables from the parent scope within the current scope unless you have
a variable in the current scope that's name is the same. To disambiguate you can use
the special variables :code:`this` and :code:`parent`.

.. topic:: `Task or workflow options <docs/reference.rst>`_ are always bound to the scope of the task

    This means, that they have to explicitly be set for the task or workflow and can not be read
    from parent tasks (like jobs or workflows). However sub tasks of those tasks can
    access those options without prefix when they don't have an option with the same
    name or with :code:`parent` prefix otherwise.

Global variables
----------------
Additionally to the options from the current and parent tasks there are some global variables available:

- :code:`job`
    - The current job object (instance of :code:`\Netresearch\Kite\Job`)
- :code:`kite`
    - Object with some information about the kite package (:code:`path` and relative :code:`dir`)
- :code:`config`
    - The config array object (as in configuration file)
- :code:`composer`
    - Composer service object providing keys :code:`packages` and :code:`rootPackage`

Special variables
-----------------
- :code:`this`
    - By using :code:`this` you can force the variable to be not looked up in the parent scopes
    - but only within the current.
- :code:`parent`
    - Points to the parent object

Available functions
-------------------
Kite ships with the following `expression language functions <http://symfony.com/doc/current/components/expression_language/syntax.html#component-expression-functions>`_:

- :code:`isset(variable)` and :code:`empty(variable)`
    - Behave just like their PHP equivalents. Only available for variable objects, such as
    - :code:`tasks`, :code:`nodes`, :code:`workflows` or :code:`jobs` and their objects (f.i. not for configuration
    - arrays)
- :code:`get(variableName, variableValue)` and :code:`set(variableName, variableValue)`
    - Get or set the variables (f.i. :code:`set('job.owner', node.user)`
- :code:`answer(question)`
    - Let the (command line) user answer a question and return the result
- :code:`answer(question)`
    - Let the (command line) user answer a confirmation question and return the result
- :code:`select(question, options)`
    - Let the (command line) select from an array of options
- **any PHP function**
    - Lets you call PHP functions as you are used to in PHP - e.g. :code:`str_replace('\\\\', '/', config['somePath'])` (Note the four back slashes which are required to pass a single escaped backslash to Expression Language)


Kite configuration file
=======================
You need a file called "Kite.php" to set up config (where to deploy).
For TYPO3 projects it should be placed here: :code:`typo3conf/Kite.php`,
for Magento :code:`app/etc/kite.php` and for all other applications just :code:`kite.php`.
A basic example could be

.. code:: php

    <?php
    // Example for a project without a staging environment

    // This loads configuration with common jobs
    $this->loadPreset('common');

    // This configuration is loaded on execution of deploy or rollout job
    $this['stages']['staging']['node'] = array(
        'host' => 'set host here',
        'deployPath' => 'set path on host here',
        'webUrl' => 'set url here',
        'php' => 'php56',
    );

    // no staging is available
    unset($this['stages']['production']);

    ?>

Jobs
====
Jobs are to be configured in the key :code:`jobs` in the configuration. They can contain
a single :code:`task`, an array of :code:`tasks` or a :code:`workflow` (always only one of them).

.. code:: php

    <?php
    // Job, running a single task
    $this['jobs']['echo'] = [
        'description' => 'Output a message',
        'arguments' => [
            'message' => [
                'type' => 'string',
                'required' => true,
                'label' => 'The message to output'
            ]
        ],
        'task' => [
            'type' => 'output',
            'message' => '{job.message}'
        ]
    ];

    // Job, running a workflow
    $this['jobs']['diagnose'] = [
        'description' => 'Show status of packages',
        'workflow' => 'Netresearch\Kite\Workflow\Composer\Diagnose'
        // can written as follows also:
        // 'workflow' => 'composer-diagnose'
    ];

Nodes
=====
Whenever you set a key named :code:`node` or :code:`nodes` on a job, workflow or task
it's value will be mapped to an aggregate of node models. Those models have the
following default configuration:

.. code:: php

    <?php
    array(
        'user' => '',
        'pass' => '',
        'port' => '',
        'url' => '{(this.user ? this.user ~ "@" : "") ~ this.host}', // SCP/SSH URL
        'sshOptions' => ' -A{this.port ? " -p " ~ this.port : ""}{this.pass ? " -o PubkeyAuthentication=no" : ""}',
        'scpOptions' => '{this.port ? " -P " ~ this.port : ""}{this.pass ? " -o PubkeyAuthentication=no" : ""}',
        'php' => 'php', // PHP executable
        'webRoot' => '{this.deployPath}/current',
        // No default values, required to be set:
        // 'webUrl' => 'http://example.com',
        // 'host' => 'example.com',
        // 'deployPath' => '/var/www'
    );

Deployment configuration
========================

Stages
------
As you saw in the example in `Kite configuration file`_, there is a top level configuration
element named :code:`stages`. They are set by the :code:`common` preset and hold configuration
only used for each of it's keys (such as :code:`staging` and :code:`production` by default). They
are evaluated by workflows based on the :code:`stage-select` workflow, which takes the
stage(s) to use from either command line or a select question. After a stage was
selected ALL of it's values are set to the corresponding task (such as :code:`deploy`).

The stages have no special meaning and are not handled in a special way - they only
play together with the stage based tasks (:code:`deploy` and :code:`rollout` from the :code:`common`
preset and :code:`ccr` from the :code:`typo3` preset) because those are configured so.

Deployment
----------
The :code:`deployment` workflow deploys your application to exactly one stage (whereas the
:code:`rollout` just runs the :code:`deployment` workflow for each until the selected stage).
Thereby it does the following steps:

#. Run :code:`kite composer diagnose` to assert that your application is at a defined state (nothing uncommited, unpushed, unpulled, lock file up to date etc.)
#. Run :code:`composer checkout` with the parameters you provided for the stage:
    #. :code:`branch` - The branch to checkout. In :code:`common` preset they are configured as follows:

        .. code:: php

            <?php
            $this['stages'] = [
                'staging' => [
                    'branch' => '{replace("/", "-", composer.rootPackage.name)}-staging',
                    'merge' => true,
                    'createBranch' => '{config["composer"]["whitelistNames"] || config["composer"]["whitelistRemotes"] || config["composer"]["whitelistPaths"]}'
                    // add nodes or node in your config
                ],
                'production' => [
                    'branch' => 'master',
                    // add nodes or node in your config
                ]
            ];

    #. :code:`merge` - Whether to merge the currently checked out branch into the branch to checkout
    #. :code:`createBranch` - Whether to create the branch if it doesn't exist. This is by
       default set to true for the staging stage, when no whitelists for composer tasks
       are configured. You can configure whitelists for composer like that

        .. code:: php

            <?php
            // The following whitelist types are available (evaluated by OR)
            // ... for the package names
            $this['composer']['whitelistNames'] = 'netresearch/.*';
            // ... for the git remote urls
            $this['composer']['whitelistRemotes'] = 'git@github.com:netresearch/.*';
            // ... for the package paths
            $this['composer']['whitelistPaths'] = 'vendor/netresearch/.*';

    #. :code:`rsync` - configuration for rsync task invoked (f.i. with :code:`excludes` option)
#. Creates a new release from the current release on each :code:`node` :code:`{deployPath}/releases`
#. Rsync the current local state to the new release dir on each :code:`node`
#. Symlink shared directories and files (shared means shared between the releases) -
   the shared directories and files are expected to be at :code:`{deployPath}/shared`. They
   can be configured as seen in the typo3 preset:

    .. code:: php

        <?php
        $this->merge(
            $this['jobs']['deploy']['task'],
            [
                'shared' => [
                    'dirs' => ['fileadmin', 'uploads', 'typo3temp']
                ]
            ]
        );

    To illustrate the behaviour of the stage configuration here's an example setting
    the shared directories differently for each :code:`stage`:

    .. code:: php

        <?php
        $this->merge(
            $this['stages'],
            [
                'staging' => [
                    'shared' => [
                        'dirs' => ['shared_dir_1', 'shared_dir_2'],
                        'files' => ['file1', 'file2']
                    ]
                ],
                'production' => [
                    'shared' => [
                        'dirs' => '{config["stages"]["staging"]["shared"]["dirs"]}',
                        'file' => 'file'
                    ]
                ]
            ]
        );

#. Switch the previous release pointer (:code:`{deploypath}/previous`) to the current release.
#. Switch the current release pointer (:code:`{deploypath}/current`) to the new release.
#. Invoke the :code:`onReady` task if any. F.i.:

    .. code:: php

        <?php
        $this->merge(
            $this['jobs']['deploy']['task'],
            [
                'onReady' => [
                    'type' => 'shell',
                    'command' => 'mail',
                    'optArg' => ['s' => 'Deployed to {stage}', 'user@example.com']
                ],
            ]
        );

    And to once again demonstrate that each of the :code:`stages` can override any option on
    the deployment workflow:

    .. code:: php

        <?php
        $this->merge(
            $this['stages']['production'],
            [
                'onReady' => [
                    'type' => 'shell',
                    'command' => 'mail',
                    'optArg' => ['s' => '[IMPORTANT] Deployed to {stage}', 'user@example.com']
                ],
            ]
        );

When you invoke the :code:`deployment` or :code:`rollout` jobs with the rollback (:code:`--rollback` or :code:`-r`)
option, it

#. switches the next release pointer (:code:`{deploypath}/next`) to the current release
#. switches the current release pointer (:code:`{deploypath}/current`) to the previous release
#. invokes the :code:`onReady` task if any.

When you invoke the :code:`deployment` or :code:`rollout` jobs with the rollback (:code:`--activate` or :code:`-a`)
option, it invokes the last three steps of the deployment (switch symlinks, and invoke :code:`onReady`).

=====
Usage
=====

As stated above, all jobs are available as kite sub commands (:code:`kite job-name`). You can list the available commands by running

.. code:: bash

    kite [list]

By running

.. code:: bash

    kite help command
    #or
    kite command --help

you can show help for a specific job/command.

Common commands
===============
- :code:`kite [help [command]]`
    - Gives a list of all available commands (jobs) or shows help for the given one
- :code:`kite --workflow=<workflow-name-or-class>`
    - Runs a workflow class without requiring it to be inside a job
- :code:`kite --workflow=<workflow-name-or-class> --help`
    - Shows the docs (php class doc), arguments and options for a workflow
- :code:`kite log [-l]`
    - Shows the last (default), specific (use with caret like ^2 shows the 2nd least
      log or with a timestamp from :code:`kite log -l`) or a list of the available log
      records

Common jobs
===========
- :code:`kite checkout [--merge] branch`
    - Goes through all composer packages and checks out the branch there if it’s available
    - After checking out the branch on a package it goes through all packages requiring it and updates the version constraint to that branch
    - When :code:`--merge` is passed, the currently checked out branch is merged into the branch to checkout
- :code:`kite merge [--squash] [--message=”Message”] branch`
    - Goes through all composer packages and merges the branch into the currently checked out
- :code:`kite package-foreach [--git] command`
    - Runs a command for each composer package (optionally only :code:`--git` packages)
- :code:`kite cc, kite ccr [stage]`
    - Clears caches locally (cc) or on all nodes of a specific stage

Deployment jobs
===============
- :code:`kite deploy [stage]`
    - Runs the deployment for all nodes on the given or selected stage
- :code:`kite rollout [stage]`
    - Runs the deployment for all nodes for each stage until (including) the given stage

.. topic:: Use public key authentication

    To prevent you to have to type your password several times during deployment you should set your public key on your server. Usually this is located here: "~/.ssh/authorized_keys".

Trouble shooting
================

Every task that's executed including it's output will be logged to a log file inside
your home directory. This includes f.i. each command ran on the local and remote shells,
their output, debug messages and a lot more. Basically it holds the output, you would
get by adding :code:`-vvv` to your kite command.

Just run :code:`kite log` when a job failed and you want to know exactly what went wrong.