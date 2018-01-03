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
namespace local_queue\management;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot . '/local/queue/lib.php');

class edit_queue_cron_task_form extends \moodleform {

    public function definition() {
        $mform = $this->_form;
        $task = $this->_customdata;
        $localqueue = 'local_queue';

        $mform->addElement('static', 'classname', get_string('classname', 'local_queue'), $task['classname']);
        $type = local_queue_task_type_from_class($task['classname']);
        $mform->addElement('static', 'type', get_string('tasktype', $localqueue), $type);

        // The default cron worker class.
        $types = ['\local_queue\interfaces\QueueWorker'];
        $folder = QUEUE_WORKERS_FOLDER;
        $workers = local_queue_class_scanner($folder, $types);
        $workeroptions = [];
        foreach ($workers as $worker) {
            $workeroptions[$worker['classname']] = $worker['name'];
        }
        $cronworker = get_string('worker', $localqueue);
        $mform->addElement('select', 'worker', $cronworker, $workeroptions);

        // The default cron broker class.
        $types = ['\local_queue\interfaces\QueueBroker'];
        $folder = QUEUE_BROKERS_FOLDER;
        $brokers = local_queue_class_scanner($folder, $types);
        $brokeroptions = [];
        foreach ($brokers as $broker) {
            $brokeroptions[$broker['classname']] = $broker['name'];
        }
        $cronbroker = get_string('broker', $localqueue);
        $mform->addElement('select', 'broker', $cronbroker, $brokeroptions);

        // The default cron job container class.
        $types = ['\local_queue\interfaces\QueueJobContainer'];
        $folder = QUEUE_CONTAINERS_FOLDER;
        $containers = local_queue_class_scanner($folder, $types);
        $containeroptions = [];
        foreach ($containers as $container) {
            $containeroptions[$container['classname']] = $container['name'];
        }
        $croncontainer = get_string('container', $localqueue);
        $mform->addElement('select', 'container', $croncontainer, $containeroptions);

        // The default cron job class.
        $types = ['\local_queue\jobs\BaseCronJob'];
        $folder = QUEUE_JOBS_FOLDER;
        $jobs = local_queue_class_scanner($folder, $types);
        $joboptions = [];
        foreach ($jobs as $job) {
            $joboptions[$job['classname']] = $job['name'];
        }
        $cronjob = get_string('job', $localqueue);
        $mform->addElement('select', 'job', $cronjob, $joboptions);
        for ($i = 0; $i < 11; $i++) {
            $priorities[$i] = $i;
        }
        $priorities[0] = $priorities[0].' - '. get_string('highest', $localqueue);
        $priorities[5] = $priorities[5].' - '. get_string('normal', $localqueue);
        $priorities[10] = $priorities[10].' - '. get_string('lowest', $localqueue);
        $mform->addElement('select', 'priority', get_string('priority', $localqueue), $priorities);

        $mform->addElement('text', 'attempts', get_string('attempts', $localqueue));
        $mform->setType('attempts', PARAM_RAW);

        $mform->addElement('hidden', 'task', $task['id']);
        $mform->setType('task', PARAM_RAW);
        $mform->addElement('hidden', 'action', 'edit');
        $mform->setType('action', PARAM_ALPHANUMEXT);
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    public function save($data) {
        $data->id = $data->task;
        $table = 'local_queue_tasks';
        return save_update_queue_data($data, $table);
    }

}

