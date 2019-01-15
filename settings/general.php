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
require_once($CFG->dirroot . '/local/queue/lib.php');

if ($hassiteconfig) {
    $localqueue = 'local_queue';
    $queuesettings = get_string('generalsettings', $localqueue);
    $settings = new admin_settingpage('local_queue_generalsettings', $queuesettings);

    // Master switch to enable or disable the system.
    $enablequeue = get_string('enablequeue', $localqueue);
    $enablequeuehelp = get_string('enablequeue_help', $localqueue);
    $settings->add(new admin_setting_configcheckbox(
        $localqueue. '/'. $localqueue. '_enabled',
        $enablequeue,
        $enablequeuehelp,
        0
    ));

    $queuemanagerdefaults = get_string('queuemanagerdefaults', $localqueue);
    $settings->add(new admin_setting_heading('queuemanagerdefaults', $queuemanagerdefaults, ''));

    // Include system mechanics output in the logs?
    $showmechanics = get_string('showmechanics', $localqueue);
    $showmechanicshelp = get_string('showmechanics_help', $localqueue);
    $settings->add(new admin_setting_configcheckbox(
        'local_queue/local_queue_mechanics',
        $showmechanics,
        $showmechanicshelp,
        1
    ));

    // Keep the generated logs?
    $keeplogs = get_string('keeplogs', $localqueue);
    $keeplogshelp = get_string('keeplogs_help', $localqueue);
    $settings->add(new admin_setting_configcheckbox(
        'local_queue/local_queue_keeplogs',
        $keeplogs,
        $keeplogshelp,
        0
    ));

    if (PHP_OS == "Linux") {
        // Nice processes ?
        $usenice = get_string('usenice', $localqueue);
        $usenicehelp = get_string('usenice_help', $localqueue);
        $settings->add(new admin_setting_configcheckbox(
            'local_queue/local_queue_usenice',
            $usenice,
            $usenicehelp,
            1
        ));
    }

    // Number of worker pipes.
    $pipesnumber = get_string('pipesnumber', $localqueue);
    $pipesnumberhelp = get_string('pipesnumber_help', $localqueue);
    $settings->add(new admin_setting_configtext(
        'local_queue/local_queue_pipesnumber',
        $pipesnumber,
        $pipesnumberhelp,
        10
    ));

    // Time to wait between queue cycles.
    $waittime = get_string('waittime', $localqueue);
    $waittimehelp = get_string('waittime_help', $localqueue);
    $settings->add(new admin_setting_configtext(
        'local_queue/local_queue_waittime',
        $waittime,
        $waittimehelp,
        500000
    ));

    $queueitemsdefaults = get_string('queueitemsdefaults', $localqueue);
    $settings->add(new admin_setting_heading('queueitemsdefaults', $queueitemsdefaults, ''));

    // Fail attempts.
    $attempts = get_string('attempts', $localqueue);
    $attemptshelp = get_string('attempts_help', $localqueue);
    $settings->add(new admin_setting_configtext(
        'local_queue/local_queue_attempts',
        $attempts,
        $attemptshelp,
        10
    ));

    // Priority.
    $priority = get_string('priority', $localqueue);
    $priorityhelp = get_string('priority_help', $localqueue);
    for ($i = 0; $i < 11; $i++) {
        $priorities[$i] = $i;
    }
    $priorities[0] = $priorities[0].' - '. get_string('highest', $localqueue);
    $priorities[5] = $priorities[5].' - '. get_string('normal', $localqueue);
    $priorities[10] = $priorities[10].' - '. get_string('lowest', $localqueue);
    $settings->add(new admin_setting_configselect(
        'local_queue/local_queue_priority',
        $priority,
        $priorityhelp,
        5,
        $priorities
    ));

    $queueservice = 'Queue Service';
    $settings->add(new admin_setting_heading('queueservice', $queueservice, ''));


    // The default queue class.
    $types = ['\local_queue\interfaces\QueueService'];
    $folder = QUEUE_SERVICES_FOLDER;
    $queues = local_queue_class_scanner($folder, $types);
    $queueoptions = [];
    foreach ($queues as $queue) {
        $queueoptions[$queue['classname']] = $queue['name'];
    }
    $mainqueueservice = get_string('mainqueueservice', $localqueue);
    $mainqueueservicehelp = get_string('mainqueueservice_help', $localqueue);
    $settings->add(new admin_setting_configselect(
        'local_queue/local_queue_mainqueue',
        $mainqueueservice,
        $mainqueueservicehelp,
        null,
        $queueoptions
    ));

    $crondefaults = get_string('crondefaults', $localqueue);
    $settings->add(new admin_setting_heading('crondefaults', $crondefaults, ''));

    // The default cron worker class.
    $types = ['\local_queue\interfaces\QueueWorker'];
    $folder = QUEUE_WORKERS_FOLDER;
    $workers = local_queue_class_scanner($folder, $types);
    $workeroptions = [];
    foreach ($workers as $worker) {
        $workeroptions[$worker['classname']] = $worker['name'];
    }
    $cronworker = get_string('cronworker', $localqueue);
    $cronworkerhelp = get_string('cronworker_help', $localqueue);
    $settings->add(new admin_setting_configselect(
        'local_queue/local_queue_cronworker',
        $cronworker,
        $cronworkerhelp,
        null,
        $workeroptions
    ));

    // The default cron broker class.
    $types = ['\local_queue\interfaces\QueueBroker'];
    $folder = QUEUE_BROKERS_FOLDER;
    $brokers = local_queue_class_scanner($folder, $types);
    $brokeroptions = [];
    foreach ($brokers as $broker) {
        $brokeroptions[$broker['classname']] = $broker['name'];
    }
    $cronbroker = get_string('cronbroker', $localqueue);
    $cronbrokerhelp = get_string('cronbroker_help', $localqueue);
    $settings->add(new admin_setting_configselect(
        'local_queue/local_queue_cronbroker',
        $cronbroker,
        $cronbrokerhelp,
        null,
        $brokeroptions
    ));

    // The default cron job container class.
    $types = ['\local_queue\interfaces\QueueJobContainer'];
    $folder = QUEUE_CONTAINERS_FOLDER;
    $containers = local_queue_class_scanner($folder, $types);
    $containeroptions = [];
    foreach ($containers as $container) {
        $containeroptions[$container['classname']] = $container['name'];
    }
    $croncontainer = get_string('croncontainer', $localqueue);
    $croncontainerhelp = get_string('croncontainer_help', $localqueue);
    $settings->add(new admin_setting_configselect(
        'local_queue/local_queue_croncontainer',
        $croncontainer,
        $croncontainerhelp,
        null,
        $containeroptions
    ));

    // The default cron job class.
    $types = ['\local_queue\jobs\BaseCronJob'];
    $folder = QUEUE_JOBS_FOLDER;
    $jobs = local_queue_class_scanner($folder, $types);
    $joboptions = [];
    foreach ($jobs as $job) {
        $joboptions[$job['classname']] = $job['name'];
    }
    $cronjob = get_string('cronjob', $localqueue);
    $cronjobhelp = get_string('cronjob_help', $localqueue);
    $settings->add(new admin_setting_configselect(
        'local_queue/local_queue_cronjob',
        $cronjob,
        $cronjobhelp,
        null,
        $joboptions
    ));
    // Install/update local queue cron refresher task.
    local_queue_cron_refresher();
    $ADMIN->add('local_queue', $settings);
}
