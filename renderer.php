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

    public function scheduled_tasks_table($tasks) {
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
            $configureurl = new moodle_url($cronurl, array('action' => 'edit', 'task' => $task['id']));
            $editlink = $this->action_icon($configureurl, new pix_icon('t/edit', $task['name']));
            $name = new html_table_cell(html_writer::tag('b', $task['name']). "<br/><br/>".'('.$task['classname'].')');
            $details = new html_table_cell(
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $worker. ': '). local_queue_extract_classname($task['worker'])
                ). "<br/>".
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $broker. ': '). local_queue_extract_classname($task['broker'])
                ). "<br/>".
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $container. ': '). local_queue_extract_classname($task['container'])
                ). "<br/>".
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $job. ': '). local_queue_extract_classname($task['job'])
                ). "<br/>".
                html_writer::tag(
                    'span',
                    html_writer::tag('b', $priority. ': '). $task['priority']
                ).' '.
                html_writer::tag('span',
                    html_writer::tag('b', $attempts. ': '). $task['attempts']
                )
            );
            $rowitems = array($name, $details, new html_table_cell($editlink));
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
        $defaults = local_queue_defaults();
        $configureurl = new moodle_url('/admin/settings.php?section=local_queue_generalsettings');
        $editlink = $this->action_icon($configureurl, new pix_icon('t/edit', get_string('crondefaults', 'local_queue')));
        $name = new html_table_cell($taskdefaults);
        $name->header = true;;
        $details = new html_table_cell(
            html_writer::tag(
                'span',
                html_writer::tag('b', $worker. ': '). local_queue_extract_classname($defaults['cronworker'])
            ). "<br/>".
            html_writer::tag(
                'span',
                html_writer::tag('b', $container. ': '). local_queue_extract_classname($defaults['croncontainer'])
            ). "<br/>".
            html_writer::tag(
                'span',
                html_writer::tag('b', $job. ': '). local_queue_extract_classname($defaults['cronjob'])
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
