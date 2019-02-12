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

$url = new moodle_url('/local/queue/management/blacklist.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$strheading = get_string('blacklist', 'local_queue');
$PAGE->set_title($strheading);
$PAGE->set_heading($strheading);

require_login();
require_capability('moodle/site:config', context_system::instance());

$renderer = $PAGE->get_renderer('local_queue');

echo $OUTPUT->header();
$error = optional_param('error', '', PARAM_NOTAGS);
if ($error) {
    echo $OUTPUT->notification($error, 'notifyerror');
}
$msg = optional_param('msg', '', PARAM_NOTAGS);
if ($msg) {
    echo $OUTPUT->notification($msg, 'notifysuccess');
}

$action = optional_param('action', '', PARAM_ALPHAEXT);
$itemid = optional_param('itemid', null, PARAM_INT);
if ($action == 'remove' && $itemid > 0) {
    local_queue_remove_item($itemid);
    $msg = get_string('itemremoved', 'local_queue');
    redirect(new moodle_url($url, ['msg' => $msg]));
}

$items = banned_queue_items();
$total = count($items);
echo "<h3>$total ".get_string('bannedqueueitems', 'local_queue'). "</h3>";
if ($total > 0) {
    echo $renderer->banned_queue_items_table($items);
} else {
    echo get_string('noitemsfound', 'local_queue');
}
echo $OUTPUT->footer();
