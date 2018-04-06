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
namespace local_queue\workers;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/local/queue/lib.php');
require_once(LOCAL_QUEUE_FOLDER.'/interfaces/QueueWorker.php');
require_once(LOCAL_QUEUE_FOLDER.'/classes/QueueLogger.php');
use \local_queue\QueueLogger as QueueLogger;

class DefaultWorker implements \local_queue\interfaces\QueueWorker{
    /**
     * Worker process pipes specifications.
     */
    private $specs = array(
       0 => array("pipe", "r"),
       1 => array('file', 'queuelogs/output', 'w'),
       2 => array('file', 'queuelogs/errors', 'w'),
    );
    /**
     * Array containing the worker process pipes.
     */
    private $pipes = [];
    /**
     * The queue item object.
     */
    private $item;
    /**
     * The worker process pointer.
     */
    private $process;
    /**
     * The Process ID of the worker process
     */
    public $pid;
    /**
     * The time the worker began work.
     */
    public $starttime;
    /**
     * If there were errors mark as true.
     */
    public $failed = false;
    /**
     * Path to the output file.
     */
    public $outputfile;
    /**
     * Path to the errors file.
     */
    public $errorfile;
    /**
     * Queue item hash.
     */
    public $hash;

    /**
     * Validate the queue item object.
     *
     * @param stdClass $item the queue item object.
     * @return boolean
     */
    private function validate($item) {
        if (empty($item)) {
            return false;
        }
        $clone = (array) json_decode(json_encode($item));
        $ok = true;
        $keys = array_keys($clone);
        unset($clone);
        $list = ['container', 'payload', 'priority', 'job', 'broker'];
        $inter = array_intersect($list, $keys);
        $haskeys = count($inter) == count($list);
        if (!$haskeys) {
            $ok = false;
        }
        return $ok;
    }

    /**
     * Assign the queue item.
     *
     * @param stdClass $item the queue item object.
     */
    public function set_item($item) {
        if ($this->validate($item)) {
            $this->item = $item;
        } else {
            QueueLogger::error('Invalid item provided - missing required properties');
        }
    }

    /**
     * Start the worker process.
     */
    public function begin() {
        global $CFG;

        $php = PHP_BINARY;
        $dir = $CFG->localcachedir;
        if (!is_dir($dir)) {
            mkdir($dir);
        }
        if (!is_dir($dir. '/queuelogs')) {
            mkdir($dir. '/queuelogs');
        }
        $cwd = null;
        $env = array(
            'container' => $this->item->container,
            'payload' => $this->item->payload,
            'broker' => $this->item->broker,
            'job' => $this->item->job
        );
        $cmd = "exec ".$php. ' '. LOCAL_QUEUE_FOLDER. '/worker.php '. $this->item->hash;
        if (PHP_OS == "Linux" && local_queue_configuration('usenice')) {
            $niceness = (4 * $this->item->priority) - 20;
            if ($niceness != 0) {
                $nice = 'nice -n '. $niceness;
                $cmd = $nice. ' '. $cmd;
            }
        }
        $this->starttime = microtime(true);
        $specs = $this->specs;
        $outputdir = $dir. '/'. $specs[1][1];
        $errorsdir = $dir. '/'. $specs[2][1];
        if (!is_dir($outputdir)) {
            mkdir($outputdir);
        }
        if (!is_dir($errorsdir)) {
            mkdir($errorsdir);
        }
        $taskdir = '/'. $this->item->hash. '/';
        if (!is_dir($outputdir. $taskdir)) {
            mkdir($outputdir. $taskdir);
        }
        if (!is_dir($errorsdir. $taskdir)) {
            mkdir($errorsdir. $taskdir);
        }
        $ext = '.txt';
        $filename  = $this->item->id. '_'. $this->starttime. $ext;
        $this->outputfile = $outputdir. $taskdir. $filename;
        $this->errorfile = $errorsdir. $taskdir. $filename;
        $specs[1][1] = $this->outputfile;
        $specs[2][1] = $this->errorfile;
        $this->process = proc_open($cmd, $specs, $this->pipes, $cwd, $env);
        $this->item->running = $this->running();
        $this->hash = $this->item->hash;
    }

    /**
     * Check the status of the worker pipe.
     *
     * @return array
     */
    private function get_status() {
        $status = [];
        if (is_resource($this->process)) {
            $this->stats = proc_get_status($this->process);
            $this->pid = $this->stats['pid'];
            $status = $this->stats;
        }
        return $status;
    }

    /**
     * Check if the worker pipe is still running.
     *
     * @return boolean
     */
    public function running() {
        $status = $this->get_status();
        if (isset($status['running'])) {
            if ($status['running'] == false) {
                if ($status['signaled'] == true && $status['exitcode'] != 0 && $status['termsig'] != 0) {
                    $this->trigger_error("TERMINATION signal '".$status['termsig']."' received ..." );
                }
                if ($status['stopped'] == true && $status['exitcode'] != 0 && $status['stopsig'] != 0) {
                    $this->trigger_error("STOP signal '".$status['stopsig']."' received ..." );
                }
            }
            return $status['running'];
        }
        return false;
    }

    /**
     * Return the assigned queue item object.
     *
     * @return stdClass
     */
    public function get_item() {
        return $this->item;
    }

    /**
     * Check if there are any errors.
     *
     * @return boolean
     */
    private function errors() {
        $found = false;
        if ($pipe = fopen($this->errorfile, 'r')) {
            $found = strlen(fread($pipe, 32)) > 0;
            fclose($pipe);
        }
        $this->failed = $found;
        return $found;
    }

    /**
     * Trigger a "fake" error. Not caused by the task, but by the system, no attempt penalty needed.
     */
    public function trigger_error($error) {
        file_put_contents($this->errorfile, $error, FILE_APPEND | LOCK_EX);
        $this->item->attempts++;
    }

    /**
     * Close the worker pipe and do some clean up.
     */
    public function finish() {
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        if (is_resource($this->process)) {
            proc_close($this->process);
        }
        unset($this->pipes);
        unset($this->item);
        unset($this->process);
        unset($this->pid);
        unset($this->starttime);
        unset($this->failed);
        unset($this->outputfile);
        unset($this->errorfile);
        unset($this->hash);
        gc_collect_cycles();
    }

    /**
     * Return the report about worker pipe process.
     *
     * @return array
     */
    public function get_report() {
        $error = '';
        if ($this->errors()) {
            $error = $this->errorfile;
            $this->item->attempts--;
            if ($this->item->attempts <= 0) {
                $this->item->banned = true;
                $action = 'ban';
            } else {
                $action = 'nack';
            }
        } else {
            $action = 'ack';
            @unlink($this->errorfile);
            if (local_queue_is_empty_dir(dirname($this->errorfile))) {
                @rmdir(dirname($this->errorfile));
            }
        }
        $report = [];
        $report['action'] = $action;
        $report['output'] = $this->outputfile;
        $report['error'] = $error;
        $report['failed'] = $this->failed;
        $this->item->running = false;
        unset($action);
        unset($this->outputfile);
        unset($this->errorfile);
        unset($error);
        unset($this->failed);
        return $report;
    }
}