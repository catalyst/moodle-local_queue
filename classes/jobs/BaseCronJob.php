<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    local_queue
 * @copyright  2017 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Ionut Marchis <ionut.marchis@catalyst-eu.net>
 */
namespace local_queue\jobs;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/local/queue/lib.php');
require_once(LOCAL_QUEUE_FOLDER.'/interfaces/QueueJob.php');
require_once(LOCAL_QUEUE_FOLDER.'/classes/QueueLogger.php');
use \local_queue\QueueLogger;

abstract class BaseCronJob implements \local_queue\interfaces\QueueJob {
    private $task;

    public function __construct(\core\task\task_base $task) {
        $this->task = $task;
    }

    public function start() {
        QueueLogger::systemlog('Task to be executed: '. $this->task->get_name()."\n");
        try {
            $this->prepare();
            get_mailer('buffer');
            $this->task->execute();
            $this->success();
        } catch (\Exception $e) {
            $this->failed($e);
        }
        $this->finish();
    }

    protected function prepare() {
        global $DB, $CFG;

        if (CLI_MAINTENANCE) {
            throw new \Exception('CLI maintenance mode active, cron execution suspended.');
        }

        if (moodle_needs_upgrading()) {
            throw new \Exception('Moodle upgrade pending, cron execution suspended.');
        }

        require_once($CFG->libdir.'/adminlib.php');

        if (!empty($CFG->showcronsql)) {
            $DB->set_debug(true);
        }

        if (!empty($CFG->showcrondebugging)) {
            set_debugging(DEBUG_DEVELOPER, true);
        }

        \core_php_time_limit::raise();
        $this->starttime = microtime();

        // Increase memory limit.
        raise_memory_limit(MEMORY_EXTRA);

        // Emulate normal session - we use admin accoutn by default.
        cron_setup_user();

        QueueLogger::systemlog("Start Time: ".date('r', time()));
    }

    protected function finish() {
        get_mailer('close');
        QueueLogger::systemlog("End Time: ".date('r', time()));
    }

    protected function failed($exception) {
        global $DB;

        if ($DB && $DB->is_transaction_started()) {
            QueueLogger::systemlog('Database transaction aborted automatically in ' . get_class($task));
            $DB->force_transaction_rollback();
        }
        QueueLogger::systemlog("\n".'Task execution status: failed.'."\n".' Reason: ' . $exception->getMessage());
    }

    protected function success() {
        QueueLogger::systemlog("\n".'Task execution status: success.');
    }

    protected function manage($task, $action) {
        if ($task) {
            $type = local_queue_task_type_from_class($task);
            $method = $type. '_'. $action;
            if (method_exists('\core\task\manager', $method)) {
                \core\task\manager::$method($task);
            }
        }
    }
}