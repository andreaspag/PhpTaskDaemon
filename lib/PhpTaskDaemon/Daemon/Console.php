<?php
/**
 * @package PhpTaskDaemon
 * @subpackage Daemon
 * @copyright Copyright (C) 2011 Dirk Engels Websolutions. All rights reserved.
 * @author Dirk Engels <d.engels@dirkengels.com>
 * @license https://github.com/DirkEngels/PhpTaskDaemon/blob/master/doc/LICENSE
 */

namespace PhpTaskDaemon\Daemon;

/**
* The main Console class is responsible for starting, stopping and monitoring
* the daemon. It accepts command line arguments to set daemon daemon options.
* The start method creates an Instance object.
*/
class Console {

    // Messages
    const MSG_NO_TASKS = "No tasks found!";

    // Configuration options
    const CONFIG_PROCESS_TYPE           = 'process.type';
    const CONFIG_IPC                    = 'ipc';
    const CONFIG_ACTION                 = 'action';

    // Manager depndies.
    const CONFIG_TIMER_TYPE             = 'timer.type';
    const CONFIG_TIMER_INTERVAL_TIME    = 'timer.interval.time';
    const CONFIG_TIMER_CRON_TIME        = 'timer.cron.time';

    // Actions
    const ACTION_START                  = 'start';
    const ACTION_STOP                   = 'stop';
    const ACTION_RESTART                = 'restart';
    const ACTION_STATUS                 = 'status';
    const ACTION_MONITOR                = 'monitor';
    const ACTION_LIST_TASKS             = 'list-tasks';
    const ACTION_SETTINGS               = 'settings';
    const ACTION_HELP                   = 'action';

    /**
     * Console options object
     * 
     * @var Zend_Console_Getopt
     */
    protected $_consoleOpts;

    /**
     * Instance run object.
     * 
     * @var Instance
     */
    protected $_instance;

    /**
     * Tasks collection object.
     * 
     * @var Tasks
     */
    protected $_tasks;


    /**
     * Daemon constructor method.
     * 
     * @param \Zend_Console_Getopt $consoleOpts
     */
    public function __construct( Instance $instance = NULL ) {
        // Initialize command line arguments
        $this->setInstance( $instance );
        $this->setConsoleOpts();
    }


    /**
     * Returns an object containing the console arguments.
     * 
     * @return Zend_Console_Getopt
     */
    public function getConsoleOpts() {
        // Initialize default console options
        if ( is_null( $this->_consoleOpts ) ) {
            $this->_consoleOpts = new \Zend_Console_Getopt(
                array(
                    'config-file|c-s'   => 'Configuration file (defaults: /etc/{name}.conf, {cwd}/{name}.conf)',
                    'log-file|l-s'      => 'Log file (defaults /var/log/{name}.log, {cwd}/{name}.log)',
                    'action|a=s'        => 'Action (default: start) (options: start, stop, restart, status, monitor)',
                    'list-tasks|lt'     => 'List tasks',
                    'settings|s'        => 'Display tasks settings',
                    'task|t=s'          => 'Run single task',
                    'verbose|v-i'       => 'Verbose',
                    'help|h'            => 'Show help message (this message)',
                )
            );
        }

        return $this->_consoleOpts;
    }


    /**
     * Sets new console arguments.
     * 
     * @param \Zend_Console_Getopt $consoleOpts
     * @return $this
     */
    public function setConsoleOpts( \Zend_Console_Getopt $consoleOpts = NULL ) {
        if ( $consoleOpts === NULL ) {
            $consoleOpts = $this->getConsoleOpts();
        }

        // Parse Options
        try {
            $consoleOpts->parse();
        } catch ( \Zend_Console_Getopt_Exception $e ) {
            echo $e->getUsageMessage();
            $this->_exit();
        }
        $this->_consoleOpts = $consoleOpts;

        return $this;
    }


    /**
     * Returns the daemon instance object.
     * 
     * @return Instance
     */
    public function getInstance() {
        if ($this->_instance === NULL) {
            $this->_instance = new Instance();
        }
        return $this->_instance;
    }


    /**
     * Sets a daemon instance object.
     * 
     * @param Instance $instance
     * @return $this
     */
    public function setInstance( $instance ) {
        $this->_instance = $instance;
        return $this;
    }


    /**
     * Returns the daemon tasks collection object.
     * 
     * @return Tasks
     */
    public function getTasks() {
        if ($this->_tasks === NULL) {
            $this->_tasks = new Tasks();
        }
        return $this->_tasks;
    }


    /**
     * Sets a daemon tasks collection object.
     * 
     * @param Tasks $tasks
     * @return $this
     */
    public function setTasks( $tasks ) {
        $this->_tasks = $tasks;
        return $this;
    }


    /**
     * Reads the command line arguments and invokes the selected action.
     * 
     * @expected \RuntimeException
     */
    public function run() {
        try {
            // Set verbose mode (--verbose)
            $this->_initLogVerbose();

            // Initialize Configuration
            $this->_initConfig();

            // List Tasks (--list-tasks)
            if ($this->_consoleOpts->getOption( self::ACTION_LIST_TASKS ) ) {
                $this->listTasks();
                $this->_exit();
            }

            // Display Settings (--settings)
            if ($this->_consoleOpts->getOption( self::ACTION_SETTINGS ) ) {
                $this->settings();
                $this->_exit();
            }

            // Add Log Files
            $this->_initLogFile();

            // Check action, otherwise display help
            if ($this->_consoleOpts->getOption( self::ACTION_HELP ) ) {

                // All available actions
                $allActions = array(
                    self::ACTION_START,
                    self::ACTION_STOP,
                    self::ACTION_RESTART,
                    self::ACTION_STATUS,
                    self::ACTION_MONITOR,
                );

                $action = $this->_consoleOpts->getOption( self::CONFIG_ACTION );
                if ( in_array( $action, $allActions ) )  {
                    // Perform action
                    $this->$action();
                    $this->_exit();
                }
            }

            // Display Command Help
            $this->help();

        } catch (\RuntimeException $e) {
            Logger::log( 'FATAL EXCEPTION: ' . $e->getMessage(), \Zend_Log::CRIT );
        }

    }


    /**
     * Lists the current loaded tasks.
     */
    public function listTasks() {
        $config = Config::get();
        $taskNames = $this->getTasks()->scan();

        // Print header
        echo "List Tasks\n";
        echo "==========\n\n";

        // One or more tasks found
        if ( ! count( $taskNames ) == 0 ) {
            foreach ( $taskNames as $taskName ) {

                // Print header
                echo $taskName, "\n";
                echo str_repeat( '-', strlen($taskName) ), "\n";

                echo "Process:\t\t", $config->getOptionValue( self::CONFIG_PROCESS_TYPE, $taskName ), "\n";
                echo "IPC:\t\t\t", $config->getOptionValue( self::CONFIG_IPC, $taskName ), "\n";

                // Manager Timer
                $timer = $config->getOptionValue( self::CONFIG_TIMER_TYPE, $taskName );
                echo "Timer:\t\t", $timer, "\n";
                switch($timer) {
                    case 'interval':
                        echo "- Time:\t\t\t", $config->getOptionValue( self::CONFIG_TIMER_INTERVAL_TIME, $taskName ), "\n";
                        break;
                    case 'cron':
                        echo "- Time:\t\t\t", $config->getOptionValue( self::CONFIG_TIMER_CRON_TIME, $taskName ), "\n";
                        break;
                    default:
                        break;
                }
                echo "\n";
            }
        // No Tasks
        } else {
            echo self::MSG_NO_TASKS . "\n";
        }
    }


    /**
     * Displays the configuration settings for each tasks.
     */
    public function settings() {
        echo $this->_settingsDaemon();
    }


    /**
     * Action: Start Daemon.
     */
    public function start() {
        $taskNames = $this->getTasks()->scan();

        // Initialize daemon tasks
        foreach($taskNames as $taskName) {
            if ($this->_consoleOpts->getOption('task')) {
                if (!preg_match('#' . $this->_consoleOpts->getOption('task') . '#', $taskName)) {
                    continue;
                }
            }
            $this->getTasks()->loadManagerByTaskName($taskName);
        }
        $this->getInstance()->setTasks($this->getTasks());

        // Start the Daemon
        $this->getInstance()->start();
    }


    /**
     * Action: Stop daemon. 
     */
    public function stop() {
        if (!$this->getInstance()->isRunning()) {
            echo "Daemon is NOT running!!!\n\n";
        } else {
            echo "Terminating application  !!!\n\n";
            $this->getInstance()->stop();
        }
    }


    /**
     * Alias for stopping and restarting the daemon.
     */
    public function restart() {
        $this->stop();
        $this->start();
        $this->_exit();
    }


    /**
     * Action: Get daemon status.
     */
    public function status() {
        $state = State::getState();
        if ($state['pid'] === NULL) {
            echo "Daemon not running\n";
            $this->_exit();
        }

        echo "PhpTaskDaemon - Status (" . count($state['processes']) . ")\n";
        echo "==========================\n";
        echo "\n";

        $this->_status($state);
    }


    protected function _status($state = null) {
        // Get state when not provided as method argument
        if (is_null($state)) {
            $state = State::getState();
        }

        if (count($state['processes']) == 0) {
            echo "No processes!\n";

        } else {
            foreach ($state['processes'] as $queuePid) {
                $queue = $state['queue-' . $queuePid];

                // Statistics
                echo "[" . $queuePid . "]: ";
                echo $queue['name'] . "\t";
                echo "\t(Progress: " . ($queue['loaded']-$queue['queued']) . '/' . $queue['loaded'];
                echo "\tDone: " . $queue['done'];
                echo "\tFailed: " . $queue['failed'];
                echo ")\n";

                // Status
                foreach ($queue['executors'] as $executorPid) {
                    $status = $state['executor-' . $executorPid];
                    $percentage = (isset($status['percentage'])) ? $status['percentage'] : 0;
                    $mesage = (isset($status['message'])) ? $status['message'] : '';
                    echo "- [" . $executorPid . "]: ";
                    echo "\t" . $percentage . "%:";
                    echo "\t" . $message;
                    echo "\n";
                }

                echo "\n";
            }

        }
        echo "\n";
    }


    /**
     * Displays the current tasks and activities of the daemon. The monitor 
     * action refreshes every x milliseconds.
     */
    public function monitor() {
        while (true) {
            // Put the status output into a buffer before clearing the screen.
            // This prevents the screen from flickering.
            ob_start();

            $state = State::getState();
            echo "PhpTaskDaemon - Monitoring (" . count($state['processes']) . ")\n";
            echo "==============================\n";
            echo "\n";
            $this->_status($state);

            $state = ob_get_contents();
            ob_end_clean();

            // Clear screen, print buffer & sleep
            System('clear');
            echo $state;

            usleep(Config::get()->getOptionValue('daemon.monitor.sleep'));
        }
    }


    /**
     * Displays a help message containing usage instructions.
     */
    public function help() {
        echo "Help\n";
        echo "====\n";
        echo $this->_consoleOpts->getUsageMessage();
    }


    /**
     * Initializes the Config component by loading configuration files passed 
     * using command line arguments and the default configuration files.
     */
    protected function _initConfig() {
        // Prepare configuration files
        $configFiles = array();
        if ($this->_consoleOpts->getOption('config-file')!='') {
            $configArguments = explode(',', $this->_consoleOpts->getOption('config-file'));
            foreach ($configArguments as $configArgument) {
                if (!strstr($configArgument, '/')) {
                    $configArgument = \APPLICATION_PATH . '/' . $configArgument;
                }
                array_push($configFiles, $configArgument);
            }
        }

        // Initiate config
        $config = \PhpTaskDaemon\Daemon\Config::get($configFiles);
        return $config;
    }


    /**
     * Initializes the logging verbose mode.
     */
    protected function _initLogVerbose() {
        // Log Verbose Output
        if ($this->_consoleOpts->getOption('verbose')) {
            $writerVerbose = new \Zend_Log_Writer_Stream('php://output');

            // Determine Log Level
            $logLevel = \Zend_Log::ERR;
            if ($this->_consoleOpts->getOption('verbose')>1) {
                $logLevel = (int) $this->_consoleOpts->getOption('verbose');
            }
            $writerVerbose->addFilter($logLevel);

            \PhpTaskDaemon\Daemon\Logger::get()->addWriter( $writerVerbose );
            $msg = 'Added Log Writer: Verbose';
            \PhpTaskDaemon\Daemon\Logger::log($msg, \Zend_Log::DEBUG);
        }
    }


    /**
     * Initalizes the Logger component to save log messages to a file based on
     * the command line arguments and/or configuration files.
     * 
     * @throws \Exception
     */
    protected function _initLogFile() {
        if ($this->_consoleOpts->getOption('log-file')) {
            $logFile =  getcwd() . '/' . $this->_consoleOpts->getOption('log-file');
        } else {
            $logFile = realpath(Config::get()->getOptionValue('log.file'));
            if (substr($logFile, 0, 1)!='/') {
                $logFile = realpath(\APPLICATION_PATH) . '/' . $logFile;
            }
        }
        \PhpTaskDaemon\Daemon\Logger::log('Check if log file exists or can be created: ' . $logFile, \Zend_Log::DEBUG);

        // Create logfile if not exists
        try {
            if (!file_exists($logFile)) {
                touch($logFile);
            }
            // Adding logfile
            $writerFile = new \Zend_Log_Writer_Stream($logFile);
            \PhpTaskDaemon\Daemon\Logger::get()->addWriter($writerFile);
            \PhpTaskDaemon\Daemon\Logger::log('Added Log Writer: ' . $logFile, \Zend_Log::DEBUG);
        } catch (\Exception $e) {
            \PhpTaskDaemon\Daemon\Logger::log('Cannot create log file: ' . $logFile, \Zend_Log::ALERT);
        }
    }


    /**
     * Output daemon settings.
     * 
     * Format:
     * - Global
     *   - Namespace
     *   - Interrupt adapter
     *   - IPC adapter
     * - Paths
     *   - App dir
     *   - Task dir
     *   - Tmp dir
     *   - Log dir
     * - Database
     *   - Adapter
     *   - Host
     *   - Database
     *   - Username
     * - Log
     *   - File
     *   - Level
     * 
     * @return string
     */
    protected function _settingsDaemon() {
        $out  = "Daemon Settings\n";
        $out .= "===============\n\n";

        $out .= "Global\n";
        $out .= "------\n";
        $out .= "- Namespace:\t\t" . Config::get()->getOptionValue('daemon.global.namespace') . "\n";
        $out .= "- Interrupt:\t\t" . Config::get()->getOptionValue('daemon.global.interrupt') . "\n";
        $out .= "- IPC:\t\t\t" . Config::get()->getOptionValue('daemon.global.ipc') . "\n";
        $out .= "\n";

        $out .= "Paths\n";
        $out .= "-----\n";
        $out .= "- App Dir:\t\t" . \APPLICATION_PATH . '/'. "\n";
        $out .= "- Task Dir:\t\t" . \APPLICATION_PATH . '/'. Config::get()->getOptionValue('daemon.global.taskdir') . "\n";
        $out .= "- Tmp Dir:\t\t" . \APPLICATION_PATH . '/'. Config::get()->getOptionValue('daemon.global.tmpdir') . "\n";
        $out .= "\n";

        $out .= "Database\n";
        $out .= "--------\n";
        $out .= "- Adapter:\t\t" . Config::get()->getOptionValue('daemon.db.adapter') . "\n";
        $out .= "- Host:\t\t\t" . Config::get()->getOptionValue('daemon.db.params.host') . "\n";
        $out .= "- Database:\t\t" . Config::get()->getOptionValue('daemon.db.params.dbname') . "\n";
        $out .= "- Username:\t\t" . Config::get()->getOptionValue('daemon.db.params.username') . "\n";
        $out .= "\n";

        $out .= "Log\n";
        $out .= "---\n";
        $out .= "- File:\t\t\t" . \APPLICATION_PATH . '/'. Config::get()->getOptionValue('daemon.log.file') . "\n";
        $out .= "- Level:\t\t" . Config::get()->getOptionValue('daemon.log.level') . "\n";
        $out .= "\n";

        return $out;
    }


    protected function _settingsDefaults() {
        $out = '';
        $out .= "Tasks Default Settings\n";
        $out .= "======================\n\n";

        $out .= "Global\n";
        $out .= "------\n";
        $out .= "- Namespace:\t\t" . Config::get()->getOptionValue('tasks.defaults.namespace') . "\n";
        $out .= "- IPC:\t\t\t" . Config::get()->getOptionValue('tasks.defaults.namespace') . "\n";
        $out .= "\n";

        $out .= "Timer\n";
        $out .= "-------\n";
        $out .= "- Default:\t\t" . Config::get()->getOptionValue('tasks.defaults.manager.timer.type') . "\n";
        $out .= "- Types:\t\tInterval, Cron, Gearman\n";
        $out .= "- Interval\n";
        $out .= "- Cron\n";
        $out .= "\n";

        $out .= "Process\n";
        $out .= "-------\n";
        $out .= "- Type:\t\t\t" . Config::get()->getOptionValue('tasks.defaults.manager.process.type') . "\n";
        $out .= "- Parallel\n";
        $out .= "\t- Childs:\t" . Config::get()->getOptionValue('tasks.defaults.manager.process.parallel.childs') . "\n";
        $out .= "\n";

        $out .= "\n";
        return $out;
    }


    protected function _settingsTasks() {
        $out = '';
        $out .= "Tasks Specific Settings\n";
        $out .= "=======================\n\n";

        $tasks = array();
        try {
            $tasks = $this->getTasks()->scan();
        } catch (Exception $e) {
            $out .= $e->getMessage();
        }

        if (count($tasks)>0) {
            foreach($tasks as $nr => $taskName) {
                $out .= $taskName . "\n";
                $out .= str_repeat('-', strlen($taskName)) . "\n";
                $out .= "\tProcess:\t\t" . Config::get()->getOptionValue('manager.process.type') . "\t\t(" . Config::get()->getOptionValue('manager.process.type') . ")\n";
            }
        } else {
            $out .= "No tasks found!!!";
        }

        $out .= "\n\n";
        return $out;
    }


    /**
     * Stop the application immediately with an optioan message.
     * 
     * @param $string Log a last message before exiting.
     */
    protected function _exit( $msg = NULL ) {
        // Log a last (default) message.
        if ( is_null( $msg ) ) {
            $msg = 'Exiting application with no specified reason!';
        }
        Logger::log( $msg );

        // Goodbye!
        exit;
    }

}
