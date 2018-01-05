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

class DefaultJob implements \local_queue\interfaces\QueueJob {
    private $task;

    public function __construct(\core\task\task_base $task) {
        $this->task = $task;
        if (!method_exists($this->task, 'execute')) {
            QueueLogger::error("\n".'Task invalid.'."\n".' Does not have the required method (execute).');
        }
    }

    public function start() {
        QueueLogger::systemlog('Task to be executed: '. $this->task->get_name()."\n");
        try {
            $this->task->execute();
            QueueLogger::systemlog("\n".'Task execution status: success.');
        } catch (Exception $e) {
            QueueLogger::error("\n".'Task execution status: failed.'."\n".' Reason: ' . $e->getMessage());
        }
        QueueLogger::systemlog("End Time: ".date('r', time()));
    }
}