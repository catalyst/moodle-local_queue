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
require_once(dirname(__FILE__).'/BaseCronJob.php');
require_once(LOCAL_QUEUE_FOLDER.'/classes/QueueLogger.php');
use \local_queue\QueueLogger as QueueLogger;

class VerboseCronJob extends \local_queue\jobs\BaseCronJob {
    private $task;

    private $runtime;
    private $dbqueries;

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
        global $DB;

        parent::prepare();
        $this->dbqueries = $DB->perf_get_queries();
        $this->runtime = microtime(1);
        $this->log_memory();
    }

    protected function finish() {
        $this->log_time($this->runtime);
        $this->log_queries();
        $this->log_memory();
        parent::finish();
    }

    protected function failed($exception) {
        parent::failed($exception);
        $info = get_exception_info($exception);
        $logerrmsg = 'Task exception: '. $this->task->get_name() . " - " . $info->message;
        $logerrmsg .= ' Debug: ' . $info->debuginfo . "\n" . format_backtrace($info->backtrace, true);
        QueueLogger::systemlog($logerrmsg);
        parent::manage($this->task, 'failed');
    }

    protected function success() {
        QueueLogger::systemlog("");
        parent::success();
        parent::manage($this->task, 'complete');
    }

    protected function log_time ($time) {
        $difftime = @microtime_diff($time, microtime());
        QueueLogger::systemlog("Execution took ".$difftime." seconds");
    }

    protected function log_memory() {
        gc_collect_cycles();
        QueueLogger::systemlog('Memory usage was ' . display_size(memory_get_usage()) . ', at ' . date('H:i:s') . '.');
    }

    protected function log_queries() {
        global $DB;
        QueueLogger::systemlog("Task used " . ($DB->perf_get_queries() - $this->dbqueries) . " dbqueries");
    }
}