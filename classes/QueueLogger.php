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

class QueueLogger {
    const GREEN = "\033[32m";
    const BLUE = "\033[34m";
    const YELLOW = "\033[33m";
    const PURPLE = "\033[35m";
    const BLACK = "\033[0m";
    const RED = "\033[31m";

    public static function systemlog($message, $color = self::BLACK) {
        $ce = self::BLACK;
        if (local_queue_configuration('mechanics')) {
            if (defined('STDOUT')) {
                if (posix_isatty(STDOUT)) {
                    self::log($color. ' '. $message. $ce);
                } else {
                    self::log($message);
                }
            } else {
                self::log($message);
            }
        }
    }

    public static function log($message, $eol = PHP_EOL, $timestamp = null) {
        if ($timestamp != null) {
            $message = $timestamp. ' '. $message;
        }
        if (defined('STDOUT')) {
            fwrite(STDOUT, $message. $eol);
        } else {
            echo $message. $eol;
        }
    }

    public static function error($message) {
        debugging($message, DEBUG_DEVELOPER);
        throw new \Exception($message);
    }

    public static function read($filename, $hash = '') {
        $unlink = !local_queue_configuration('keeplogs');
        if ($outputpipe = fopen($filename, 'r')) {
            while (!feof($outputpipe)) {
                $line = fgets($outputpipe);
                if ($line) {
                    $output = $hash. ' '. $line;
                    self::log($output, '', date("Y-m-d H:i:s"));
                }
            }
            fclose($outputpipe);
            if ($unlink) {
                @unlink($filename);
                if (local_queue_is_empty_dir(dirname($filename))) {
                    @rmdir(dirname($filename));
                }
            }
        }
    }
}