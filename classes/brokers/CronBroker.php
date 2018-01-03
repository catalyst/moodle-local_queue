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
namespace local_queue\brokers;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/local/queue/lib.php');
require_once(LOCAL_QUEUE_FOLDER.'/interfaces/QueueBroker.php');


class CronBroker implements \local_queue\interfaces\QueueBroker{
    private $classname;
    private $type;
    private $record;

    public function __construct($payload) {
        $payload = json_decode($payload, true);
        $classname = $payload['task'];
        $record = $payload['record'];
        $this->classname = $classname;
        $this->type = local_queue_task_type_from_class($classname);
        $this->record = local_queue_task_record($this->type, $record);
    }

    public function get_task() {
        $method = $this->type. '_from_record';
        if (method_exists('\core\task\manager', $method)) {
            $this->task = \core\task\manager::$method($this->record);
        }

        $cronlockfactory = \core\lock\lock_config::get_lock_factory('cron');
        if (!$cronlock = $cronlockfactory->get_lock('core_cron', 5)) {
            throw new \Exception('Cron is locked. Cannot continue.');
        }
        $unique = $this->classname.'_'.$this->record->id;
        if ($lock = $cronlockfactory->get_lock($unique, 1)) {
            if (!$this->task) {
                $lock->release();
            }
            $this->task->set_lock($lock);
            if (!$this->task->is_blocking()) {
                $cronlock->release();
            } else {
                $this->task->set_cron_lock($cronlock);
            }
            return $this->task;
        } else {
            $cronlock->release();
            throw new \Exception('Task is locked. Cannot continue.');
        }

        $cronlock->release();
        return null;
    }
}