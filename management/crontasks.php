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
require_once(dirname(__FILE__) . '/../../../config.php');
defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot . '/local/queue/lib.php');
require_once(LOCAL_QUEUE_FOLDER.'/management/forms/edit_queue_cron_task_form.php');

require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/tablelib.php');

$PAGE->set_url('/local/queue/management/crontasks.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$strheading = get_string('crontasks', 'local_queue');
$PAGE->set_title($strheading);
$PAGE->set_heading($strheading);

require_login();
require_capability('moodle/site:config', context_system::instance());

$renderer = $PAGE->get_renderer('local_queue');

$action = optional_param('action', '', PARAM_ALPHAEXT);
$taskid = optional_param('task', '', PARAM_RAW);

$task = null;
$mform = null;

if ($taskid) {
    $task = local_queue_item_settings($taskid);
    if (!$task) {
        print_error('invaliddata');
    }
}

if ($action == 'edit') {
    $PAGE->navbar->add($task['name']);
}

if ($task) {
    $mform = new \local_queue\management\edit_queue_cron_task_form(null, $task);
}

if ($mform && $mform->is_cancelled()) {
    redirect(new moodle_url('/local/queue/management/crontasks.php'));
} else if ($mform && $mform->is_submitted()) {
    $formdata = null;
    if ($mform->is_validated()) {
        $formdata = data_submitted();
    }
    $saved = false;
    if ($formdata) {
        if (method_exists($mform, 'save')) {
            $saved = $mform->save($formdata);
        }
    }
    if ($saved !== false) {
        $msg = get_string('crontasksettingsupdated', 'local_queue');        
        redirect(new moodle_url('/local/queue/management/crontasks.php'), $msg, 1);
    }
} else if ($action == 'edit') {
    echo $OUTPUT->header();
    echo $OUTPUT->heading($task['name']);
    $mform->set_data($task);
    $mform->display();
    echo $OUTPUT->footer();
} else {
    echo $OUTPUT->header();
    $error = optional_param('error', '', PARAM_NOTAGS);
    if ($error) {
        echo $OUTPUT->notification($error, 'notifyerror');
    }
    echo "<h3>".get_string('othertasks', 'local_queue')."</h3>";
    echo $renderer->crontask_defaults_table();

    echo "<h3>".get_string('knowntasks', 'local_queue')."</h3>";
    $tasks = local_queue_items_settings();
    echo $renderer->known_tasks_table($tasks);
    echo $OUTPUT->footer();
}
