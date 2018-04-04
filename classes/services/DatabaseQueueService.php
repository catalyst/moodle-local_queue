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
namespace local_queue\services;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/local/queue/lib.php');
require_once(LOCAL_QUEUE_FOLDER.'/interfaces/QueueService.php');
require_once(LOCAL_QUEUE_FOLDER.'/classes/QueueLogger.php');
use \local_queue\QueueLogger;

class DatabaseQueueService implements \local_queue\interfaces\QueueService {
    private static $color = QueueLogger::RED;

    /**
     * Retrieve a number of items from the queue.
     * @param int|sting $limit number of queue items needed. Default 1.
     * @param string $queue the name of the queue to get items from. Default null.
     * @return array
     */
    public static function consume($limit = 1, $queue = null) {
        global $DB;

        QueueLogger::systemlog(" ... Requested pool items: ". $limit, self::$color);
        $conditions = ['banned' => false, 'running' => false];
        if ($queue) {
            $conditions['queue'] = $queue;
        }
        $items = $DB->get_records(QUEUE_ITEMS_TABLE, $conditions, 'priority ASC, timecompleted ASC', '*', 0, $limit);
        $ids = [];
        foreach ($items as $item) {
            array_push($ids, $item->id);
            $item->running = true;
        }
        if (!empty($ids)) {
            list($sql, $params)  = $DB->get_in_or_equal($ids);
            $sql = 'id '.$sql;
            $DB->set_field_select(QUEUE_ITEMS_TABLE, 'running', true, $sql, $params);
            $DB->set_field_select(QUEUE_ITEMS_TABLE, 'timechanged', time(), $sql, $params);
            $DB->set_field_select(QUEUE_ITEMS_TABLE, 'timestarted', time(), $sql, $params);
        }
        QueueLogger::systemlog(" ... Loaded from pool: ". count($items), self::$color);
        return $items;
    }

    /**
     * Add a new queue item.
     * @param stdClass $item the queue item object.
     * @param string $queue the name of the queue to add the item in. Default 'cron'.
     */
    public static function publish($item, $queue = 'cron') {
        $item->timecreated = time();
        $item->queue = $queue;
        $id = self::save_update_queue_item($item);
        QueueLogger::systemlog(" ... Published item '". $item->hash. "' - ID ". $id, self::$color);
    }

    /**
     * Handle the successful execution of a queue item.
     * @param stdClass $item the queue item object.
     */
    public static function ack($item) {
        $item->timecompleted = time();
        if (!$item->persist) {
            self::remove_queue_item($item->hash);
        } else {
            self::save_update_queue_item($item);
        }
        QueueLogger::systemlog(" ... Closure for item: '". $item->hash. "' - ID ". $item->id, self::$color);
    }

    /**
     * Re-queue a failed queue item.
     * @param stdClass $item the queue item object.
     */
    public static function nack($item) {
        $item->timecompleted = time();
        $item->timechanged = time();
        $id = self::save_update_queue_item($item);
        QueueLogger::systemlog(" ... Requeued  item '". $item->hash. "' - ID ". $id, self::$color);
    }

    /**
     * Ban a queue item.
     * @param stdClass $item the queue item object.
     */
    public static function ban($item) {
        $item->timecompleted = time();
        $item->timechanged = time();
        $item->running = false;
        $item->banned = true;
        $item->attempts = 0;
        $id = self::save_update_queue_item($item);
        QueueLogger::systemlog(" ... Banned item '". $item->hash. "' - ID ". $id, self::$color);
    }

    /**
     * Get queue item record by hash, without removing it from the queue.
     *
     * @param string $hash queue item hash
     * @return stdClass|false
     */
    public static function item_info($hash) {
        global $DB;

        $where = 'hash = :hash';
        $params = ['hash' => $hash];
        return $DB->get_record_select(QUEUE_ITEMS_TABLE, $where, $params, '*', IGNORE_MISSING);
    }

    /**
     * Re-queue any orphan items that were stuck in running mode if the manager crashed.
     * @param int $time start time of a new manger.
     * @param string $queue the name of the queue. Default null.
     */
    public static function requeue_orphans($time, $queue = null) {
        global $DB;

        $where = 'timestarted < :timestarted AND running = 1 AND banned = 0';
        $params = ['timestarted' => $time];
        if ($queue != null) {
            $where = $where. ' AND queue =:queue';
            $params['queue'] = $queue;
        }
        $DB->set_field_select(QUEUE_ITEMS_TABLE, 'running', 0, $where, $params);
    }

    /**
     * Update/save queue item
     *
     * @param stdClass $item queue item data to be inserted/updated
     * @return int
     */
    private static function save_update_queue_item(\stdClass $item) {
        global $DB;
        if (property_exists($item, 'id')) {
            if (!$item->banned) {
                $item->timechanged = time();
            }
            $DB->update_record(QUEUE_ITEMS_TABLE, $item);
            return $item->id;
        } else {
            return $DB->insert_record(QUEUE_ITEMS_TABLE, $item, true);
        }
    }

    /**
     * Remove queue item
     *
     * @param string $hash queue item hash
     * @return boolean
     */
    private static function remove_queue_item($hash) {
        global $DB;

        return $DB->delete_records(QUEUE_ITEMS_TABLE, ['hash' => $hash]);
    }
}