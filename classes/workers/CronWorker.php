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

class CronWorker implements \local_queue\interfaces\QueueWorker{
    // Internal - worker specs.
    private $specs = array(
       0 => array("pipe", "r"),
       1 => array('file', 'logs/output/', 'w'),
       2 => array('file', 'logs/errors/', 'w'),
    );

    private $pipes = [];
    private $item;
    private $process;

    public $pid;
    public $starttime;
    public $failed = false;
    public $outputfile;
    public $errorfile;
    public $error = '';
    public $hash;


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

    public function set_item($item) {
        if ($this->validate($item)) {
            $this->item = $item;
        } else {
            QueueLogger::error('Invalid item provided - missing required properties');
        }
    }

    public function begin() {
        global $CFG;

        $php = "/usr/bin/php";
        $dir = LOCAL_QUEUE_FOLDER;
        if (!is_dir($dir.'/logs')) {
            mkdir($dir.'/logs');
        }
        $cwd = null;
        $env = array(
            'container' => $this->item->container,
            'payload' => $this->item->payload,
            'broker' => $this->item->broker,
            'job' => $this->item->job
        );
        // $nice = 'nice -n '.( (2*$this->item->priority) - 20);
        $nice = 'nice -n '.($this->item->priority - 20);
        $cmd = $nice. ' '. $php.' '. $dir.'/worker.php';

        $this->starttime = time();
        $specs = $this->specs;
        $outputdir = $dir. '/'. $specs[1][1];
        $errorsdir = $dir. '/'. $specs[2][1];
        if (!is_dir($outputdir)) {
            mkdir($outputdir);
        }
        if (!is_dir($errorsdir)) {
            mkdir($errorsdir);
        }
        $taskdir = '/'.$this->item->hash. '/';
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

    private function get_status() {
        $status = [];
        if (is_resource($this->process)) {
            $this->stats = proc_get_status($this->process);
            $this->pid = $this->stats['pid'];
            $status = $this->stats;
        }
        return $status;
    }

    public function running() {
        $status = $this->get_status();
        if (isset($status['running'])) {
            return $status['running'];
        }
        return false;
    }

    public function get_item() {
        return $this->item;
    }

    private function errors() {
        $found = false;
        if ($pipe = fopen($this->errorfile, 'r')) {
            $found = strlen(fread($pipe, 32)) > 0;
            fclose($pipe);
        }
        $this->failed = $found;
        return $found;
    }

    public function finish() {
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        if (is_resource($this->process)) {
            proc_close($this->process);
        }
        gc_collect_cycles();
    }

    public function get_report() {
        $errors = $this->errors();
        $this->item->running = false;
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
        return $report;
    }
}