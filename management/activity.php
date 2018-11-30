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

$url = new moodle_url('/local/queue/management/activity.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$strheading = get_string('crontasks', 'local_queue');
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

$running = get_string('running', 'local_queue');
$pending = get_string('pending', 'local_queue');
$banned = get_string('banned', 'local_queue');

$refreshbtn = $OUTPUT->single_button($url, get_string('refresh', 'local_queue'));
$stats = local_queue_items_stats();
$total = array_sum($stats);
$statistics = "$running: ". $stats[0]." / $pending: ". $stats[1]. " / $banned: ". $stats[2];
echo "<h3>$total ".get_string('queueitems', 'local_queue'). "  ". $refreshbtn."</h3>";
echo '<h5>'.$statistics.'</h5>';

$items = local_queue_items();
echo $renderer->queue_items_table($items);
echo $OUTPUT->footer();
