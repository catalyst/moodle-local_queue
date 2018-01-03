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
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->libdir.'/cronlib.php');

if (isset($_SERVER['container']) && isset($_SERVER['broker']) && isset($_SERVER['job']) && isset($_SERVER['payload'])) {
    $container = $_SERVER['container'];
    $broker = $_SERVER['broker'];
    $job = $_SERVER['job'];
    $payload = $_SERVER['payload'];

    if (strpos($container, '\\') !== 0) {
        $container = '\\' . $container;
    }
    if (!class_exists($container)) {
        QueueLogger::error("Failed to load job container class: ". $container);
    }

    if (strpos($broker, '\\') !== 0) {
        $broker = '\\' . $broker;
    }
    if (!class_exists($broker)) {
        QueueLogger::error("Failed to load broker class: ". $broker);
    }

    if (strpos($job, '\\') !== 0) {
        $job = '\\' . $job;
    }
    if (!class_exists($job)) {
        QueueLogger::error("Failed to load job class: ". $job);
    }

    $broker = new $broker($payload);
    $task = null;

    try {
        $task = $broker->get_task();
    } catch (\Exception $e) {
        $info = get_exception_info($e);
        $logerrmsg = 'Task exception: '. $info->message;
        $logerrmsg .= ' Debug: '. $info->debuginfo. "\n". format_backtrace($info->backtrace, true);
        QueueLogger::systemlog($logerrmsg);
    }

    if ($task != null) {
        $jobcontainer = new $container(new $job($task));
        $jobcontainer->initiate();
        $jobcontainer->run();
    } else {
        QueueLogger::error('Could not instantiate task.');
    }
} else {
    QueueLogger::error('Missing require configuration.');
}