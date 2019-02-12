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

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/local/queue/lib.php');

class local_queue_renderer extends plugin_renderer_base {

    public function known_tasks_table($tasks) {
        $cronurl = '/local/queue/management/crontasks.php';
        $table = new html_table();
        $table->head  = array(
            get_string('name'),
            get_string('taskdetails', 'local_queue'),
            get_string('edit')
        );
        $table->attributes['class'] = 'admintable generaltable';
        $data = array();
        $worker = get_string('worker', 'local_queue');
        $broker = get_string('broker', 'local_queue');
        $container = get_string('container', 'local_queue');
        $job = get_string('job', 'local_queue');
        $priority = get_string('priority', 'local_queue');
        $attempts = get_string('attempts', 'local_queue');
        foreach ($tasks as $task) {
            $configureurl = new moodle_url($cronurl, array('action' => 'edit', 'task' => $task->id));
            $task->name = local_queue_task_name($task->classname);
            $editlink = $this->action_icon($configureurl, new pix_icon('t/edit', $task->name));
            $name = new html_table_cell(html_writer::tag('b', $task->name). "<br/><br/>".'('.$task->classname.')');
            $details = new html_table_cell(
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $worker. ': '). local_queue_extract_classname($task->worker)
                ). "<br/>".
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $broker. ': '). local_queue_extract_classname($task->broker)
                ). "<br/>".
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $container. ': '). local_queue_extract_classname($task->container)
                ). "<br/>".
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $job. ': '). local_queue_extract_classname($task->job)
                ). "<br/>".
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $priority. ': '). $task->priority
                ).' '.
                html_writer::tag('span',
                    html_writer::tag('b', $attempts. ': '). $task->attempts
                )
            );
            $rowitems = array($name, $details, new html_table_cell($editlink));
            $row = new html_table_row($rowitems);
            $data[] = $row;
        }
        $table->data = $data;
        return html_writer::table($table);
    }

    public function queue_items_table($items) {
        $url = '/local/queue/management/activity.php';
        $table = new html_table();
        $table->head  = array(
            get_string('name'),
            get_string('activity', 'local_queue'),
            get_string('taskdetails', 'local_queue')
        );
        $table->attributes['class'] = 'admintable generaltable';
        $data = array();
        $worker = get_string('worker', 'local_queue');
        $broker = get_string('broker', 'local_queue');
        $container = get_string('container', 'local_queue');
        $job = get_string('job', 'local_queue');
        $priority = get_string('priority', 'local_queue');
        $attempts = get_string('attempts', 'local_queue');
        $attemptsleft = get_string('attemptsleft', 'local_queue');

        $running = get_string('running', 'local_queue');
        $timeadded = get_string('timeadded', 'local_queue');
        $timestarted = get_string('timestarted', 'local_queue');
        $timeinprogress= get_string('timeinprogress', 'local_queue');
        $format = 'Y-m-d / H:i:s';
        foreach ($items as $item) {
            $payload = json_decode($item->payload);
            $item->name = local_queue_task_name($payload->task) . ' - Record ID: '. $payload->record;
            $name = new html_table_cell(html_writer::tag('b', $item->name). "<br/><br/>".'('.$payload->task.')');
            $end = $item->timecompleted > 0 ? $item->timecompleted : microtime(true);
            $start = $item->timestarted > 0 ? $item->timestarted : microtime(true);
            $difftime = @microtime_diff($start, $end);
            $content = html_writer::tag(
                    'span',
                    html_writer::tag('b', $timeadded. ': '). gmdate($format, $item->timecreated)
                ). "<br/>".
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $running. ': '). ($item->running ? get_string('yes') : get_string('no'))
                ). "<br/>".
                html_writer::tag('span',
                    html_writer::tag('b', $attemptsleft. ': '). $item->attempts
                ). "<br/>";
            if ($item->timestarted > 0) {
                $content .= html_writer::tag(
                    'span',
                    html_writer::tag('b', $timestarted. ': '). gmdate($format, $item->timestarted)
                ). "<br/>".
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $timeinprogress. ': '). round($difftime, 3) .' s'
                );
            }
            $activity = new html_table_cell($content);
            $details = new html_table_cell(
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $worker. ': '). local_queue_extract_classname($item->worker)
                ). "<br/>".
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $broker. ': '). local_queue_extract_classname($item->broker)
                ). "<br/>".
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $container. ': '). local_queue_extract_classname($item->container)
                ). "<br/>".
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $job. ': '). local_queue_extract_classname($item->job)
                ). "<br/>".
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $priority. ': '). $item->priority
                ).' '
            );
            $rowitems = array($name, $activity, $details);
            $row = new html_table_row($rowitems);
            $data[] = $row;
        }
        $table->data = $data;
        return html_writer::table($table);
    }


    public function banned_queue_items_table($items) {
        $url = '/local/queue/management/activity.php';
        $table = new html_table();
        $table->head  = array(
            get_string('name'),
            get_string('activity', 'local_queue'),
            get_string('taskdetails', 'local_queue'),
            get_string('actions')
        );
        $table->attributes['class'] = 'admintable generaltable';
        $data = array();
        $worker = get_string('worker', 'local_queue');
        $broker = get_string('broker', 'local_queue');
        $container = get_string('container', 'local_queue');
        $job = get_string('job', 'local_queue');
        $priority = get_string('priority', 'local_queue');
        $attempts = get_string('attempts', 'local_queue');
        $attemptsleft = get_string('attemptsleft', 'local_queue');

        $running = get_string('running', 'local_queue');
        $timeadded = get_string('timeadded', 'local_queue');
        $lastrun = get_string('lastrun', 'local_queue');
        $timebanned = get_string('timebanned', 'local_queue');
        $format = 'Y-m-d / H:i:s';
        foreach ($items as $item) {
            $payload = json_decode($item->payload);
            $item->name = local_queue_task_name($payload->task) . ' - Record ID: '. $payload->record;
            $name = new html_table_cell(html_writer::tag('b', $item->name). "<br/><br/>".'('.$payload->task.')');
            $activity = new html_table_cell(
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $timeadded. ': '). gmdate($format, $item->timecreated)
                ). "<br/>".
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $lastrun. ': '). gmdate($format, $item->timestarted)
                ). "<br/>".
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $timebanned. ': '). gmdate($format, $item->timecompleted)
                )
            );
            $details = new html_table_cell(
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $worker. ': '). local_queue_extract_classname($item->worker)
                ). "<br/>".
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $broker. ': '). local_queue_extract_classname($item->broker)
                ). "<br/>".
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $container. ': '). local_queue_extract_classname($item->container)
                ). "<br/>".
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $job. ': '). local_queue_extract_classname($item->job)
                ). "<br/>".
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $priority. ': '). $item->priority
                ).' '
            );
            $url = new moodle_url('/local/queue/management/blacklist.php', ['itemid' => $item->id, 'action'=> 'remove']);
            $remove = $this->action_icon($url, new pix_icon('t/delete', get_string('remove')));
            $action = new html_table_cell($remove);
            $rowitems = array($name, $activity, $details, $action);
            $row = new html_table_row($rowitems);
            $data[] = $row;
        }
        $table->data = $data;
        return html_writer::table($table);
    }

    public function crontask_defaults_table() {
        $table = new html_table();
        $table->head  = array(
            get_string('name'),
            get_string('taskdetails', 'local_queue'),
            get_string('edit')
        );
        $table->attributes['class'] = 'admintable generaltable';
        $taskdefaults = get_string('crontasksdefaults', 'local_queue');
        $worker = get_string('worker', 'local_queue');
        $container = get_string('container', 'local_queue');
        $job = get_string('job', 'local_queue');
        $priority = get_string('priority', 'local_queue');
        $attempts = get_string('attempts', 'local_queue');
        $defaults = \local_queue\QueueItemHelper::queue_item_defaults('cron');
        $configureurl = new moodle_url('/admin/settings.php?section=local_queue_generalsettings');
        $editlink = $this->action_icon($configureurl, new pix_icon('t/edit', get_string('crondefaults', 'local_queue')));
        $name = new html_table_cell($taskdefaults);
        $name->header = true;;
        $details = new html_table_cell(
            html_writer::tag(
                'span',
                html_writer::tag('b', $worker. ': '). local_queue_extract_classname($defaults['worker'])
            ). "<br/>".
            html_writer::tag(
                'span',
                html_writer::tag('b', $container. ': '). local_queue_extract_classname($defaults['container'])
            ). "<br/>".
            html_writer::tag(
                'span',
                html_writer::tag('b', $job. ': '). local_queue_extract_classname($defaults['job'])
            ). "<br/>".
            html_writer::tag(
                'span',
                html_writer::tag('b', $priority. ': '). $defaults['priority']
            ).' '.
            html_writer::tag('span',
                html_writer::tag('b', $attempts. ': '). $defaults['attempts']
            )
        );
        $rowitems = array($name, $details, new html_table_cell($editlink));
        $table->data = [new html_table_row($rowitems)];
        return html_writer::table($table);
    }
}
