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

namespace local_queue;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/local/queue/lib.php');

class QueueItemHelper {

    /**
     * Get the queue item default configuration based on queue.
     *
     * @param string | null $wanted specific setting name string.
     * @return array | string.
     */
    public static function queue_item_defaults($queue = 'cron', $wanted = null) {
        $defaults = [
            'attempts' => local_queue_configuration('attempts'),
            'priority' => local_queue_configuration('priority'),
            'worker' => '\\local_queue\\workers\\DefaultWorker',
            'container' => '\\local_queue\\containers\\DefaultContainer',
            'job' => '\\local_queue\\jobs\\DefaultJob',
            'broker' => '\\local_queue\\brokers\\DefaultBroker',
        ];
        if ($queue == 'cron') {
            $cache = \cache::make('core', 'config');
            $cache->purge();
            $defaults['worker'] = '\\local_queue\\workers\\DefaultWorker';
            $defaults['container'] = '\\local_queue\\containers\\DefaultContainer';
            $defaults['job'] = '\\local_queue\\jobs\\SilentCronJob';
            $defaults['broker'] = '\\local_queue\\brokers\\CronBroker';
            $config = (array) get_config('local_queue');
            if (count($config) > 1) {
                if (isset($config['local_queue_cronworker'])) {
                    $defaults['worker'] = $config['local_queue_cronworker'];
                }
                if (isset($config['local_queue_croncontainer'])) {
                    $defaults['container'] = $config['local_queue_croncontainer'];
                }
                if (isset($config['local_queue_cronjob'])) {
                    $defaults['job'] = $config['local_queue_cronjob'];
                }
                if (isset($config['local_queue_cronbroker'])) {
                    $defaults['broker'] = $config['local_queue_cronbroker'];
                }
            }
        }
        if ($wanted != null && isset($defaults[$wanted])) {
            return $defaults[$wanted];
        }
        return $defaults;
    }

    /**
     * Get queue item settings record by classname.
     *
     * @param string $classname queue item settings classname
     * @return stdClass|false
     */
    public static function get_existing_settings($classname) {
        global $DB;

        $where = 'classname = :classname';
        $params = ['classname' => $classname];
        return $DB->get_record_select(QUEUE_ITEM_SETTINGS_TABLE, $where, $params, '*', IGNORE_MISSING);
    }

    /**
     * Update/save queue item settings
     *
     * @param stdClass $data queue item settings data to be inserted/updated
     * @return stdClass
     */
    public static function save_update_item_settings(\stdClass $data) {
        global $DB;

        if (property_exists($data, 'id')) {
            $data->timechanged = time();
            $DB->update_record(QUEUE_ITEM_SETTINGS_TABLE, $data);
            return $data->id;
        } else {
            return $DB->insert_record(QUEUE_ITEM_SETTINGS_TABLE, $data, true);
        }
    }

    /**
     * Extract a desired property from a data record
     *
     * @param stdClass|array $record the data record to extract property from.
     * @param string $property the property needed.
     * @return stdClass
     */
    private static function extract_property($record, $property) {
        if (is_object($record)) {
            if (property_exists($record, $property)) {
                return $record->$property;
            }
        } else if (is_array($record)) {
            if (isset($record[$property])) {
                return $record[$property];
            }
        } else {
            QueueLogger::error('Invalid record provided');
        }
    }

    /**
     * Get a queue item settings record.
     *
     * @param string $classname classname of the item to get/create settings for.
     * @param string $queue name of the queue.
     * @return array
     */
    public static function item_settings_data($classname, $queue) {
        $data = self::get_existing_settings($classname);
        if (!$data) {
            $data = new \stdClass();
            $data->classname = $classname;
            $defaults = self::queue_item_defaults($queue);
            $data->worker = $defaults['worker'];
            $data->broker = $defaults['broker'];
            $data->container = $defaults['container'];
            $data->job = $defaults['job'];
            $data->priority = $defaults['priority'];
            $data->attempts = $defaults['attempts'];
            $data->timecreated = time();
            $data->queue = $queue;
            $data->id = self::save_update_item_settings($data);
        }
        $data->special = $classname == QUEUE_REFRESHER_CLASS;
        return $data;
    }

    /**
     * Create/update a queue item for the given data and queue.
     *
     * @param stdClass|array $data array or object to create queue item from.
     * @param string $queue name of the queue.
     * @return stdClass
     */
    public static function prepare_queue_item($data, $queue = 'cron') {
        $classname = self::extract_property($data, 'classname');
        $id = self::extract_property($data, 'id');
        $hash = stripslashes($classname). '_'. $id;
        $queueservice = local_queue_configuration('mainqueueservice');
        $item = $queueservice::item_info($hash);
        $settings = self::item_settings_data($classname, $queue);
        if (!$item) {
            $item = new \stdClass();
            $item->payload = json_encode([
                'task' => $classname,
                'record' => $id
            ]);
            $item->attempts = $settings->attempts;
            $item->banned = false;
            $item->running = false;
            $item->persist = $settings->special;
            $item->hash = $hash;
            $item->setting = $settings->id;
        } else {
            if ($item->attempts > $settings->attempts || $settings->special == true) {
                $item->attempts = $settings->attempts;
                $item->banned = false;
            }
        }
        $item->container = $settings->container;
        $item->job = $settings->job;
        $item->priority = $settings->priority;
        $item->worker = $settings->worker;
        $item->broker = $settings->broker;
        return $item;
    }
}