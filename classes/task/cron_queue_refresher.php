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

namespace local_queue\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/queue/lib.php');
require_once(LOCAL_QUEUE_FOLDER.'/classes/QueueItemHelper.php');
use \local_queue\QueueItemHelper;

class cron_queue_refresher extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('cronqueuerefresher', 'local_queue');
    }

    /**
     * Load the next queue items from any adhoc or scheduled tasks found.
     */
    public function execute() {
        global $DB;

        $now = time();
        $lastrun = $this->get_last_run_time();
        $nextrun = $this->get_next_scheduled_time();
        $sql = '
        SELECT *
        FROM (
            SELECT CONCAT("scheduled", ts.id) as qid, ts.id, ts.classname,
             (CASE ts.nextruntime WHEN NULL THEN :now1 ELSE ts.nextruntime END) AS runtime
            FROM {task_scheduled} ts
            WHERE (ts.lastruntime IS NULL OR ts.lastruntime <= :lr)
            AND (ts.nextruntime IS NULL OR ts.nextruntime <= :nr1)
            AND ts.disabled = 0
            UNION ALL
            SELECT CONCAT("adhoc", ta.id) as qid, ta.id, ta.classname,
             (CASE ta.nextruntime WHEN NULL THEN :now2 ELSE ta.nextruntime END) AS runtime
            FROM {task_adhoc} ta
            WHERE (ta.nextruntime IS NULL OR ta.nextruntime <= :nr2)
        ) task ORDER BY runtime ASC
        ';
        $params = ['now1' => $now, 'now2' => $now, 'lr' => $lastrun, 'nr1' => $nextrun, 'nr2' => $nextrun];
        $records = $DB->get_records_sql($sql, $params);
        $keys = ['id', 'classname', 'nextruntime'];
        $found = count($records);
        $queueservice = local_queue_configuration('mainqueueservice');
        foreach ($records as $key => $record) {
            $item = QueueItemHelper::prepare_queue_item($record, 'cron');
            $queueservice::publish($item, 'cron');
            unset($item);
            unset($records[$key]);
        }
        echo " $found Queue item(s) refreshed. ";
    }
}
