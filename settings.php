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
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category('local_queue', get_string('pluginname', 'local_queue')));
    require($CFG->dirroot . '/local/queue/settings/general.php');
    $ADMIN->add('local_queue', new admin_category('local_queue_management', get_string('tasksmanagement', 'local_queue')));
    $ADMIN->add('local_queue_management', new admin_externalpage(
        'local_queue_tasks',
        new lang_string('crontasks', 'local_queue'),
        new moodle_url('/local/queue/management/crontasks.php')
    ));
}
