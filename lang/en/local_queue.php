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
 * Language strings for the local_queue plugin
 *
 * @package    local_queue
 * @copyright  2017 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Ionut Marchis <ionut.marchis@catalyst-eu.net>
 */

$string['pluginname'] = 'Queue';
$string['cronqueuerefresher'] = 'Cron Queue Refresher Task';
// General settings.
$string['generalsettings'] = 'General settings';
$string['showmechanics'] = 'Show mechanics output';
$string['showmechanics_help'] = 'Include system information about queue cycles in the logs?';
$string['keeplogs'] = 'Keep log files';
$string['keeplogs_help'] = 'Keep the individual tasks runtime logs?';
$string['usenice'] = 'Use niceness';
$string['usenice_help'] = 'Nice processes based on priority? (Linux systems only)';
$string['pipesnumber'] = 'Number of worker pipes';
$string['pipesnumber_help'] = 'Number of parallel running processes the queue can open.';
$string['handletime'] = 'Handle time';
$string['handletime_help'] = 'Time to wait before moving a running process to the background. (in seconds)';
$string['waittime'] = 'Wait time';
$string['waittime_help'] = "Time to pause between queue cycles. (in seconds)";
$string['attempts'] = 'Fail attempts';
$string['attempts_help'] = 'Max number of allowed failed attempts before a process is banned.';
// Cron defaults.
$string['crondefaults'] = 'Cron Queue default settings';
$string['cronbroker'] = 'Default cron broker';
$string['cronbroker_help'] = 'Brokers are classes that handle the instantiation and locking of a given task';
$string['cronworker'] = 'Default cron worker';
$string['cronworker_help'] = 'Workers are parallel processes that execute a given job in the desired conditions.';
$string['croncontainer'] = 'Default cron job container';
$string['croncontainer_help'] = 'Containers are environments in which jobs are run.';
$string['cronjob'] = 'Default cron job';
$string['cronjob_help'] = 'Jobs hold scenarios for how the execution of a task should be handled, things like what information to collect, what happens next, etc.';
// Cron tasks.
$string['tasksmanagement'] = 'Tasks management';
$string['crontasks'] = 'Cron tasks';
$string['crontasksdefaults'] = 'Cron tasks defaults';
$string['adhoctasks'] = 'Adhoc tasks';
$string['crontaskupdated'] = 'Cron task updated.';
$string['taskdetails'] = 'Task details';
$string['tasktype'] = 'Task type';
$string['classname'] = 'Classname';
$string['worker'] = 'Worker';
$string['broker'] = 'Broker';
$string['container'] = 'Container';
$string['job'] = 'Job';
$string['priority'] = 'Priority';
$string['lowest'] = 'Lowest';
$string['normal'] = 'Normal';
$string['highest'] = 'Highest';