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
namespace local_queue\queues;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/local/queue/lib.php');
require_once(LOCAL_QUEUE_FOLDER.'/interfaces/Queue.php');
require_once(LOCAL_QUEUE_FOLDER.'/classes/QueueLogger.php');
use \local_queue\QueueLogger as QueueLogger;

class CronDatabaseQueue implements \local_queue\interfaces\Queue {

    public function consume($limit = 1) {
        global $DB;

        QueueLogger::systemlog(" ... Requested pool items: ". $limit);
        $conditions = ['banned' => false, 'running' => false];
        $items = $DB->get_records('local_queue_items', $conditions, 'priority ASC, timecompleted ASC', '*', 0, $limit);
        foreach ($items as $item) {
            local_queue_item_running($item);
        }
        QueueLogger::systemlog(" ... Loaded from pool: ". count($items));
        return $items;
    }

    public function publish($record) {
        // Add new item.
        $item = local_queue_item_prepare($record);
        QueueLogger::systemlog(" ... Publish item ". $item->hash);
    }

    public function ack($item) {
        // Update item.
        local_queue_item_closure($item);
        QueueLogger::systemlog(" ... Closure for item: ". $item->hash);
    }

    public function nack($item) {
        // Update requeued item.
        local_queue_item_requeued($item);
        QueueLogger::systemlog(" ... Requeued  item: ". $item->hash);
    }

    public function ban($item) {
        // Ban item.
        local_queue_item_ban($item);
        QueueLogger::systemlog(" ... Banned item: ". $item->hash);
    }

}