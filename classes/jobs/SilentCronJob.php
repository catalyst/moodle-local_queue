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

class SilentCronJob extends \local_queue\jobs\BaseCronJob {
    private $task;

    public function __construct(\core\task\task_base $task) {
        $this->task = $task;
    }

    public function start() {
        parent::prepare();
        QueueLogger::systemlog("");
        try {
            get_mailer('buffer');
            $this->task->execute();
            $this->success();
        } catch (Exception $e) {
            $this->failed($e);
        }
        parent::finish();
    }

    public function failed($exception) {
        parent::failed($exception);
        parent::manage($this->task, 'failed');
    }

    public function success() {
        parent::success();
        parent::manage($this->task, 'complete');
    }
}