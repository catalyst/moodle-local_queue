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

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/local/queue/classes/QueueManager.php');

if ($argc < 2) {
    QueueLogger::error('Invalid number of arguments provided');
}
$queue = 'cron';
if (isset($argv[1])) {
    $queue = $argv[1];
}

// Requeue orphan items from the previous run of the manager (if the process got terminated).
$time = time();
$queueservice = local_queue_configuration('mainqueueservice');
$queueservice::requeue_orphans($time, $queue);
// If not keeping logs, remove the logs folder and all the contents (from previous orphans).
$unlink = !local_queue_configuration('keeplogs');
if ($unlink) {
    local_queue_rm_local_dir('logs');
}
$manager = new QueueManager($queue);
while (true) {
    $maintenance = CLI_MAINTENANCE || moodle_needs_upgrading();
    if (!$maintenance) {
        $manager->work();
    } else {
    	sleep(10);
    }
}