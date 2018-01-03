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
    // Max open pipes.
    private $pipes;
    // Time to wait in between checks.
    private $wait;
    // Time to wait before placing the worker in the background.
    private $handletime;
    // Status - Doing work on open pipes?
    private $working = false;
    // Execution - number of open pipes.
    private $inuse = 0;
    // Execution - number of times waited.
    private $waited = 0;
    // Execution - number of queue items handled.
    private $executed = 0;
    // Internal - workers ~ open pipes.
    private $workers = [];
    // Internal - backgrounded workers.
    private $inbackground = [];
    private $queue;

    public function __construct(\local_queue\interfaces\Queue $queue) {
        $this->queue = $queue;
        $this->check_configuration();
        ini_set('xdebug.max_nesting_level', -1);
        gc_disable();
    }

    public function check_configuration() {
        QueueLogger::systemlog(" ... Rechecking configuration ... ");
        $defaults = local_queue_defaults();
        $this->pipes = $defaults['pipesnumber'];
        $this->handletime = $defaults['handletime'];
        $this->wait = $defaults['waittime'];
    }

    public function work() {
        QueueLogger::log(memory_get_usage());
        $cs = "\033[32m"; // Green.
        QueueLogger::systemlog(" ... Work started ... ", $cs);
        while (count($this->workers) > 0) {
            $this->working = true;
            $began = time();
            $worker = array_shift($this->workers);
            $pid = $worker->pid;
            $primetime = $began + $this->handletime;
            do {
                if ($worker->running()) {
                    break;
                }
            } while ($primetime >= time());
            if ($worker->running()) {
                // array_push($this->inbackground, $worker);
                $this->inbackground[$worker->hash] = $worker;
                $action = 'move to background';
                QueueLogger::systemlog(" --- $pid Worker $action. ", $cs);
                continue;
            } else {
                $this->results($worker, $cs);
                unset($worker);
            }
        }
        QueueLogger::systemlog(" ... Work ended ... ", $cs);
        $this->working = false;
        gc_collect_cycles();
        gc_disable();
        // $this->standby();
    }

    public function background() {
        QueueLogger::log(memory_get_usage());
        $cs = "\033[34m"; // Blue.
        QueueLogger::systemlog(" ... Background work started ... ", $cs);
        if (count($this->inbackground) > 0) {
            foreach ($this->inbackground as $worker) {
                $pid = $worker->pid;
                if ($worker->running()) {
                    QueueLogger::systemlog(" --- $pid Worker still running --- ", $cs);
                    continue;
                } else {
                    unset($this->inbackground[$worker->hash]);
                    $this->results($worker, $cs);
                    unset($worker);
                }
            }
        }
        QueueLogger::systemlog(" ... Background work ended ... ", $cs);
        gc_collect_cycles();
        gc_disable();
        // $this->standby();
    }

    public function results($worker, $cs) {
        QueueLogger::log(memory_get_usage());
        $pid = $worker->pid;
        $report = $worker->get_report();
        $item = $worker->get_item();
        $action = $report['action'];
        // $message = $report['output'];
        // $error = $report['error'];
        $worker->finish();
        $this->queue->$action($item);
        $this->inuse--;
        $this->executed++;
        QueueLogger::systemlog(" ---> $pid Worker Start", $cs);
        QueueLogger::systemlog(" ---> ITEM: $item->id.", $cs);
        QueueLogger::systemlog(" ---> PAYLOAD: $item->payload.", $cs);
        QueueLogger::systemlog(" ---> ACTION: $action.", $cs);
        QueueLogger::systemlog(" ---> OUTPUT: ", $cs);
        QueueLogger::read($worker->outputfile);
        if ($worker->failed) {
            QueueLogger::systemlog(" ---> ERROR: ", $cs);
            QueueLogger::read($worker->errorfile);
        }
        QueueLogger::systemlog(" ---> $pid Worker End", $cs);
        QueueLogger::systemlog(" ... Executed: $this->executed", $cs);
        gc_collect_cycles();
        gc_disable();
        unset($worker);
    }

    public function standby() {
        QueueLogger::log(memory_get_usage());
        $cs = "\033[33m"; // Yellow.
        QueueLogger::systemlog(" ... Standing by ... ", $cs);
        $pausetime = 1;
        if (!$this->queueing()) {
            QueueLogger::systemlog(" ... Queue cycle ended ... ", $cs);
            $pausetime = $this->waited == 0 ? $pausetime : $this->wait;
            $this->check_configuration();
            $this->waited++;
        }
        sleep($pausetime);
        QueueLogger::systemlog(" ... Resuming ... ", $cs);
        gc_collect_cycles();
        gc_disable();
        $this->schedule();
    }

    public function schedule() {
        QueueLogger::log(memory_get_usage());
        $cs = "\033[35m"; // Purple.
        QueueLogger::systemlog(" ... Checking schedule ... ", $cs);
        QueueLogger::systemlog(" ... Pipes used: $this->inuse ... ", $cs);
        $slots = $this->pipes - $this->inuse;
        QueueLogger::systemlog(" ... Available slots: $slots ... ", $cs);
        QueueLogger::systemlog(" ... In background: ".count($this->inbackground). " ... ", $cs);
        gc_collect_cycles();
        gc_disable();
        QueueLogger::log(memory_get_usage());
        if ($slots > 0) {
            $items = $this->queue->consume($slots);
            foreach ($items as $item) {
                $this->new_worker($item);
                $this->inuse++;
            }
            if (!$this->working && count($this->workers) > 0) {
                $this->work();
            }
        }
        if (count($this->inbackground) > 0) {
            $this->background();
        }
        // if (count($this->workers) == 0 && count($this->inbackground) == 0) {
        //     $this->standby();
        // }
    }

    public function queueing() {
        return count($this->workers) > 0 || count($this->inbackground) > 0;
    }

    public function new_worker($item) {
        $workerclass = $item->worker;
        local_queue_class_loader(QUEUE_WORKERS_FOLDER, $workerclass);
        $worker = new $workerclass();
        $worker->set_item($item);
        $worker->begin();
        // array_push($this->workers, $worker);
        $this->workers[$worker->hash] = $worker;
        gc_collect_cycles();
        gc_disable();
        unset($worker);
    }
}