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

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/local/queue/lib.php');

class QueueManager {
    /**
     * Maximum number of workers allowed.
     */
    private $maxworkers;
    /**
     * Time to wait in between cycles.
     */
    private $wait;
    /**
     * Time to wait before placing the worker in the background.
     */
    private $handletime;
    /**
     * Status - Doing foreground work?
     */
    private $working = false;
    /**
     * Execution - number of queue cycles.
     */
    private $cycles = 0;
    /**
     * Execution - number of queue items handled.
     */
    private $executed = 0;
    /**
     * Internal - workers ~ open pipes.
     */
    private $workers = [];
    /**
     * Internal - backgrounded workers.
     */
    private $inbackground = [];
    /**
     * \local_queue\interfaces\Queue queue objects.
     */
    private $queue;

    public function __construct(\local_queue\interfaces\Queue $queue) {
        $this->queue = $queue;
        gc_collect_cycles();
        gc_disable();
    }

    /**
     * Load the plugin configuration for any changes.
     */
    public function load_configuration() {
        $defaults = local_queue_defaults();
        $this->maxworkers = $defaults['pipesnumber'];
        $this->handletime = $defaults['handletime'];
        $this->wait = $defaults['waittime'];
    }

    /**
     * Handle the current foregrounded workers, or move them to the background.
     */
    public function foreground() {
        $color = QueueLogger::GREEN;
        QueueLogger::systemlog(" ... Foreground work started ... ", $color);
        while (count($this->workers) > 0) {
            $this->working = true;
            $worker = array_shift($this->workers);
            $pid = $worker->pid;
            $primetime = $worker->starttime + $this->handletime;
            while ($primetime > time()) {
                if (!$worker->running()) {
                    break;
                }
                sleep(1);
            }
            if ($worker->running()) {
                $this->inbackground[$worker->hash] = $worker;
                $action = 'move to background';
                QueueLogger::systemlog(" --- [$pid] Worker '$worker->hash' $action. ", $color);
                continue;
            } else {
                $this->results($worker, $color);
                unset($worker);
            }
        }
        QueueLogger::systemlog(" ... Foreground work ended ... ", $color);
        $this->working = false;
        gc_collect_cycles();
        gc_disable();
    }

    /**
     * Handle the current backgrounded workers if they're finished, or skip.
     */
    public function background() {
        $color = QueueLogger::BLUE;
        QueueLogger::systemlog(" ... Background work started ... ", $color);
        foreach ($this->inbackground as $worker) {
            $pid = $worker->pid;
            if ($worker->running()) {
                QueueLogger::systemlog(" --- [$pid] Worker '$worker->hash' still running --- ", $color);
                continue;
            } else {
                unset($this->inbackground[$worker->hash]);
                $this->results($worker, $color);
                unset($worker);
            }
        }
        QueueLogger::systemlog(" ... Background work ended ... ", $color);
        gc_collect_cycles();
        gc_disable();
    }

    /**
     * Get the results of this worker execution and update the associated queue item accordingly.
     * @param \local_queue\interfaces\QueueWorker $worker
     * @param string $color color string of the mechanics output.
     */
    public function results(\local_queue\interfaces\QueueWorker $worker, $color) {
        $pid = $worker->pid;
        $report = $worker->get_report();
        $item = $worker->get_item();
        $action = $report['action'];
        $worker->finish();
        $this->queue->$action($item);
        $this->executed++;
        QueueLogger::systemlog(" ---> [$pid] Worker '$worker->hash' Start", $color);
        QueueLogger::systemlog(" ---> ITEM: $item->id.", $color);
        QueueLogger::systemlog(" ---> PAYLOAD: $item->payload.", $color);
        QueueLogger::systemlog(" ---> ACTION: $action.", $color);
        QueueLogger::systemlog(" ---> OUTPUT: ", $color);
        QueueLogger::read($worker->outputfile, $worker->hash);
        QueueLogger::log('');
        if ($worker->failed) {
            QueueLogger::systemlog(" ---> ERROR: ", $color);
            QueueLogger::read($worker->errorfile, $worker->hash);
            QueueLogger::log('');
        }
        QueueLogger::systemlog(" ---> [$pid] Worker '$worker->hash' End", $color);
        QueueLogger::systemlog(" ... Executed: $this->executed", $color);
        unset($worker);
        gc_collect_cycles();
        gc_disable();
    }

    /**
     * Loadss the plugin configuration and pauses (for performance reasons) in betweet cycles or before checking the schedule.
     */
    public function work() {
        $color = QueueLogger::YELLOW;
        QueueLogger::systemlog(" ... Work started ... ", $color);
        $pausetime = 1;
        QueueLogger::systemlog(" ... Rechecking configuration ... ", $color);
        $this->load_configuration();
        if (!$this->queueing()) {
            QueueLogger::systemlog(" ... Queue cycle ended ... ", $color);
            $pausetime = $this->cycles == 0 ? $pausetime : $this->wait;
            $this->cycles++;
        }
        QueueLogger::systemlog(" ... Pausing work for $pausetime second(s) ... ", $color);
        sleep($pausetime);
        QueueLogger::systemlog(" ... Resuming work... ", $color);
        gc_collect_cycles();
        gc_disable();
        $this->schedule();
    }

    /**
     * Consumes items from the queue and routes the next step (foreground or background work) based upon the workload status.
     */
    public function schedule() {
        $color = QueueLogger::PURPLE;
        $used = $this->used();
        QueueLogger::systemlog(" ... Checking schedule ... ", $color);
        QueueLogger::systemlog(" ... Workers used: $used ... ", $color);
        $slots = $this->maxworkers - $used;
        QueueLogger::systemlog(" ... Available slots: $slots ... ", $color);
        QueueLogger::systemlog(" ... In background: ".count($this->inbackground). " ... ", $color);
        gc_collect_cycles();
        gc_disable();
        if ($slots > 0) {
            $items = $this->queue->consume($slots);
            foreach ($items as $item) {
                $this->new_worker($item);
            }
            if (!$this->working && count($this->workers) > 0) {
                $this->foreground();
            }
        }
        if (count($this->inbackground) > 0) {
            $this->background();
        }
    }

    /**
     * Check if there are any running workers either in the foreground or the background.
     */
    public function queueing() {
        return count($this->workers) > 0 || count($this->inbackground) > 0;
    }

    /**
     * Get the total number of running workers in both the foreground and the background.
     */
    public function used() {
        return count($this->workers) + count($this->inbackground);
    }

    /**
     * Creates and starts a new worker for the queue item and adds it to the foreground.
     * @param \stdClass $item - queue item object.
     */
    public function new_worker($item) {
        $workerclass = $item->worker;
        local_queue_class_loader(QUEUE_WORKERS_FOLDER, $workerclass);
        $worker = new $workerclass();
        $worker->set_item($item);
        $worker->begin();
        $this->workers[$worker->hash] = $worker;
        unset($worker);
        gc_collect_cycles();
        gc_disable();
    }
}