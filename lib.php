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

defined('MOODLE_INTERNAL') || die();

define('LOCAL_QUEUE_FOLDER', $CFG->dirroot.'/local/queue');
define('QUEUE_CONTAINERS_FOLDER', LOCAL_QUEUE_FOLDER.'/classes/containers');
define('QUEUE_WORKERS_FOLDER', LOCAL_QUEUE_FOLDER.'/classes/workers');
define('QUEUE_BROKERS_FOLDER', LOCAL_QUEUE_FOLDER.'/classes/brokers');
define('QUEUE_JOBS_FOLDER', LOCAL_QUEUE_FOLDER.'/classes/jobs');
define('QUEUE_REFRESHER_CLASS', '\local_queue\task\cron_queue_refresher');


/**
 * Extracts the class name from a fully-qualified-class-name string
 *
 * @param string $fullname fully-qualified-class-name string.
 * @return string
 */
function local_queue_extract_classname($fullname) {
    $parts = explode('\\', $fullname);
    $name = end($parts);
    return $name;
}

/**
 * Extracts the parent class from a fully-qualified-class-name string
 *
 * @param string $task fully-qualified-class-name string.
 * @return string
 */
function local_queue_task_type_from_class($task) {
    $fullname = get_parent_class($task);
    return local_queue_extract_classname($fullname);
}

/**
 * Extracts the parent class from a fully-qualified-class-name string
 *
 * @param string $task fully-qualified-class-name string.
 * @return string
 */
function local_queue_cron_task_table($taskname) {
    if (strpos($taskname, '\\') !== false) {
        $classname = local_queue_extract_classname($taskname);
    } else {
        $classname = $taskname;
    }
    $parts = explode('_', $classname);
    $reversed = array_reverse($parts);
    return implode('_', $reversed);
}

function local_queue_task_record($type, $id) {
    global $DB;

    return $DB->get_record(local_queue_cron_task_table($type), ['id' => $id], '*', MUST_EXIST);
}

function local_queue_ban_task_record($classname, $id) {
    global $DB;

    $type = local_queue_task_type_from_class($classname);
    $table = local_queue_cron_task_table($type);
    if ($type == 'scheduled_task') {
        return $DB->set_field($table, 'disabled', true, ['id' => $id]);
    } else {
        return $DB->set_field($table, 'nextruntime', strtotime("-1 year"), ['id' => $id]);
    }
}


function local_queue_class_scanner($dir, $types, &$classes = []) {
    $files = scandir($dir);
    foreach ($files as $key => $value) {
        $path = realpath($dir.DIRECTORY_SEPARATOR.$value);
        if (!is_dir($path)) {
            $ext = substr($path, -4);
            if ($ext == '.php') {
                $code = file_get_contents($path);
                $tokens = token_get_all($code);
                $count = count($tokens);
                $namespace = $name = $extends = $implements = '';
                for ($i = 2; $i < $count; $i++) {
                    if ($tokens[$i - 2][0] == T_NAMESPACE && $tokens[$i - 1][0] == T_WHITESPACE && $tokens[$i][0] == T_STRING) {
                        for ($j = $i; $j < $count; $j++) {
                            if (is_array($tokens[$j])) {
                                $namespace .= $tokens[$j][1];
                            } else {
                                break;
                            }
                        }
                    }
                    if ($tokens[$i - 2][0] == T_CLASS && $tokens[$i - 1][0] == T_WHITESPACE && $tokens[$i][0] == T_STRING) {
                        $name = $tokens[$i][1];
                        if (is_array($tokens[$i + 2])) {
                            if ($tokens[$i + 2][1] == 'extends') {
                                for ($j = $i + 4; $j < $count; $j++) {
                                    if (is_array($tokens[$j]) && $tokens[$j][0] != T_WHITESPACE) {
                                        $extends .= $tokens[$j][1];
                                    } else {
                                        if ($tokens[$j][0] == T_WHITESPACE) {
                                            if (is_array($tokens[$j + 1]) && $tokens[$j + 1][1] == 'implements') {
                                                for ($k = $j + 3; $k < $count; $k++) {
                                                    if (is_array($tokens[$k]) && $tokens[$k][0] != T_WHITESPACE) {
                                                        $implements .= $tokens[$k][1];
                                                    } else {
                                                        break;
                                                    }
                                                }
                                            }
                                        }
                                        break;
                                    }
                                }
                            } else {
                                if ($tokens[$i + 2][1] == 'implements') {
                                    for ($j = $i + 4; $j < $count; $j++) {
                                        if (is_array($tokens[$j]) && $tokens[$j][0] != T_WHITESPACE) {
                                            $implements .= $tokens[$j][1];
                                        } else {
                                            break;
                                        }
                                    }
                                }
                            }
                            if ($namespace != '' && $namespace[0] != '\\') {
                                $namespace = '\\'. $namespace;
                            }
                            $classname = $namespace == '' ? $name : $namespace . '\\'. $name;
                            if ($extends != '' && strpos($extends, '\\') === false) {
                                $extends = $namespace == '' ? $extends : $namespace. '\\'. $extends;
                            }
                            if ($implements != '' && strpos($implements, '\\') === false) {
                                $implements = $namespace == '' ? $implements : $namespace. '\\'. $implements;
                            }
                            if (in_array($extends, $types) || in_array($implements, $types)) {
                                $data = [
                                    'path' => $path,
                                    'namespace' => $namespace,
                                    'name' => $name,
                                    'classname' => $classname,
                                    'implements' => $implements,
                                    'extends' => $extends
                                ];
                                $classes[stripslashes($classname)] = $data;
                            }
                        }
                    }
                }
            }
        } else if ($value != "." && $value != ".." && $value != ".git" && $value != "tests") {
            ini_set('memory_limit', '1024M');
            $classes = local_queue_class_scanner($path, $types, $classes);
        }
    }
    return $classes;
}

function local_queue_is_empty_dir($folder) {
    if ($handle = opendir($folder)) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != "..") {
                closedir($handle);
                return false;
            }
        }
        closedir($handle);
    }
    return true;
}

function local_queue_rm_local_dir($dir) {
    $folder = LOCAL_QUEUE_FOLDER.DIRECTORY_SEPARATOR.$dir;
    if (is_dir($folder)) {
        $files = scandir($folder);
        foreach ($files as $key => $value) {
            $path = realpath($folder.DIRECTORY_SEPARATOR.$value);
            $localpath = $dir.DIRECTORY_SEPARATOR.$value;
            if (!is_dir($path)) {
                unlink($path);
            } else if ($value != "." && $value != ".." ) {
                local_queue_rm_local_dir($localpath);
            }
        }
        rmdir($folder);
    }
}

function local_queue_class_loader($folder, $classname) {
    $classes = local_queue_class_scanner($folder, [$classname]);
    if (!empty($classes)) {
        $class = current($classes);
        require_once($class['path']);
    }
}

function local_queue_defaults($wanted = null) {
    $cache = \cache::make('core', 'config');
    $cache->purge();
    $defaults = [
        'mechanics' => true,
        'keeplogs' => false,
        'usenice' => false,
        'pipesnumber' => 10,
        'handletime' => 2,
        'waittime' => 30,
        'attempts' => 10,
        'priority' => 5,
        'cronworker' => '\\local_queue\\workers\\CronWorker',
        'croncontainer' => '\\local_queue\\containers\\DefaultContainer',
        'cronjob' => '\\local_queue\\jobs\\SilentCronJob',
        'cronbroker' => '\\local_queue\\brokers\\CronBroker',
    ];
    // Get defined configuration.
    $config = (array) get_config('local_queue');
    if (count($config) > 1) {
        if (isset($config['local_queue_mechanics'])) {
            $defaults['mechanics'] = $config['local_queue_mechanics'];
        }
        if (isset($config['local_queue_keeplogs'])) {
            $defaults['keeplogs'] = $config['local_queue_keeplogs'];
        }
        if (isset($config['local_queue_usenice'])) {
            $defaults['usenice'] = $config['local_queue_usenice'];
        }
        if (isset($config['local_queue_pipesnumber'])) {
            $defaults['pipesnumber'] = $config['local_queue_pipesnumber'];
        }
        if (isset($config['local_queue_handletime'])) {
            $defaults['handletime'] = $config['local_queue_handletime'];
        }
        if (isset($config['local_queue_waittime'])) {
            $defaults['waittime'] = $config['local_queue_waittime'];
        }
        if (isset($config['local_queue_attempts'])) {
            $defaults['attempts'] = $config['local_queue_attempts'];
        }
        if (isset($config['local_queue_cronworker'])) {
            $defaults['cronworker'] = $config['local_queue_cronworker'];
        }
        if (isset($config['local_queue_croncontainer'])) {
            $defaults['croncontainer'] = $config['local_queue_croncontainer'];
        }
        if (isset($config['local_queue_cronjob'])) {
            $defaults['cronjob'] = $config['local_queue_cronjob'];
        }
        if (isset($config['local_queue_cronbroker'])) {
            $defaults['cronbroker'] = $config['local_queue_cronbroker'];
        }
    }
    if ($wanted != null && isset($defaults[$wanted])) {
        return $defaults[$wanted];
    }
    return $defaults;
}

function local_queue_crontasks() {
    global $CFG, $SESSION;

    $scheduled = \core\task\manager::get_all_scheduled_tasks();
    $tasks = [];
    foreach ($scheduled as $key => $task) {
        $tasks[stripslashes(get_class($task))] = local_queue_apply_crontask_defaults($task);
        unset($scheduled[$key]);
    }
    return $tasks;
}

function local_queue_apply_crontask_defaults($task) {
    global $CFG;

    if (is_object($task)) {
        $obj = $task;
        $task = [];
        if (property_exists($obj, 'classname')) {
            $task['classname'] = $obj->classname;
        } else {
            $task['classname'] = '\\'. get_class($obj);
        }
        unset($obj);
    }
    $queuetask = queue_task_record($task['classname']);
    if ($queuetask) {
        $task = (array) $queuetask;
    }
    $task['name'] = local_queue_crontask_name($task['classname']);
    if (!$queuetask) {
        $defaults = local_queue_defaults();
        if (isset($task['worker']) === false) {
            $task['worker'] = $defaults['cronworker'];
        }
        if (isset($task['broker']) === false) {
            $task['broker'] = $defaults['cronbroker'];
        }
        if (isset($task['container']) === false) {
            $task['container'] = $defaults['croncontainer'];
        }
        if (isset($task['job']) === false) {
            $task['job'] = $defaults['cronjob'];
        }
        if (isset($task['priority']) === false) {
            $task['priority'] = $defaults['priority'];
        }
        if (isset($task['attempts']) === false) {
            $task['attempts'] = $defaults['attempts'];
        }
        $task["timecreated"] = time();
        $table = 'local_queue_tasks';
        $id = save_update_queue_data((object)$task, $table);
        $task['id'] = $id;
    }
    return $task;
}

/**
 * Update/save queue table entry
 *
 * @param stdClass $data queue task data to be inserted/updated
 * @param string $table name of the table
 * @return stdClass
 */
function save_update_queue_data(\stdClass $data, $table) {
    global $DB;
    if (property_exists($data, 'id')) {
        $data->timechanged = time();
        $DB->update_record($table, $data);
        return $data->id;
    } else {
        return $DB->insert_record($table, $data, true);
    }
}

/**
 * Delete queue table entry
 *
 * @param string $id queue entry id
 * @param string $table name of the table
 * @return stdClass
 */
function remove_queue_data($id, $table) {
    global $DB;

    return $DB->delete_records($table, ['id' => $id]);
}

/**
 * Return queue task record.
 *
 * @param string $classname task classname
 * @param boolean $strict search strictness, if true MUST_EXIST else IGNORE_MISSING, default false
 * @return stdClass|false
 */
function queue_task_record($classname, $strict = false) {
    global $DB;

    $strictness = $strict ? MUST_EXIST : IGNORE_MISSING;
    $where = 'classname = :classname';
    $params = ['classname' => $classname];
    return $DB->get_record_select('local_queue_tasks', $where, $params, '*', $strictness);
}

/**
 * Return queue item record.
 *
 * @param string $hash queue item hash
 * @param boolean $strict search strictness, if true MUST_EXIST else IGNORE_MISSING, default false
 * @return stdClass|false
 */
function queue_item_record($hash, $strict = false) {
    global $DB;

    $strictness = $strict ? MUST_EXIST : IGNORE_MISSING;
    $where = 'hash = :hash';
    $params = ['hash' => $hash];
    return $DB->get_record_select('local_queue_items', $where, $params, '*', $strictness);
}

function local_queue_crontask_name($classname) {
    $obj = new $classname();
    return method_exists($obj, 'get_name') ? $obj->get_name() : local_queue_extract_classname($classname);
}

function local_queue_crontask($id) {
    global $DB;

    $where = 'id = :id';
    $params = ['id' => $id];
    $task = (array) $DB->get_record_select('local_queue_tasks', $where, $params, '*', MUST_EXIST);
    $task['name'] = local_queue_crontask_name($task['classname']);
    return $task;
}

function local_queue_item_prepare(\stdClass $record) {
    if (!property_exists($record, 'classname') || !property_exists($record, 'id') ) {
        return false;
    }
    $hash = stripslashes($record->classname).'_'.$record->id;
    $item = queue_item_record($hash);
    $settings = (object) local_queue_apply_crontask_defaults($record);
    if (!$item) {
        $item = new \stdClass();
        $item->payload = json_encode([
            'task' => $record->classname,
            'record' => $record->id
        ]);
        $item->attempts = $settings->attempts;
        $item->banned = false;
        $item->running = false;
        $item->hash = $hash;
        $item->timecreated = time();
        $item->task = $settings->id;
    } else {
        if ($item->attempts > $settings->attempts || local_queue_is_refresher($item)) {
            $item->attempts = $settings->attempts;
        }
    }
    $item->container = $settings->container;
    $item->job = $settings->job;
    $item->priority = $settings->priority;
    $item->worker = $settings->worker;
    $item->broker = $settings->broker;
    $item->id = save_update_queue_data($item, 'local_queue_items');
    return $item;
}

function local_queue_item_running(\stdClass $item) {
    $item->timestarted = time();
    $item->running = true;
    save_update_queue_data($item, 'local_queue_items');
}

function local_queue_item_ban(stdClass $item) {
    $item->timecompleted = time();
    $item->timechanged = time();
    $item->running = false;
    $item->banned = true;
    $item->attempts = 0;
    save_update_queue_data($item, 'local_queue_items');
    $payload = json_decode($item->payload);
    if (property_exists($payload, 'classname') && property_exists($payload, 'record')) {
        local_queue_ban_task_record($payload->classname, $payload->record);
    }
}

function local_queue_item_closure(\stdClass $item) {
    $item->timecompleted = time();
    if (!local_queue_is_refresher($item)) {
        return remove_queue_data($item->id, 'local_queue_items');
    } else {
        return save_update_queue_data($item, 'local_queue_items');
    }
}

function local_queue_is_refresher(\stdClass $item) {
    $is = false;
    $payload = json_decode($item->payload);
    if (property_exists($payload, 'task')) {
        if ($payload->task == QUEUE_REFRESHER_CLASS) {
            $is = true;
        }
    }
    return $is;
}

function local_queue_item_requeued(\stdClass $item) {
    $item->timecompleted = time();
    $item->timechanged = time();
    save_update_queue_data($item, 'local_queue_items');
}

function  local_queue_refresher() {
    global $DB;

    $where = 'classname = :classname';
    $params = ['classname' => QUEUE_REFRESHER_CLASS];
    $queuerefresher = $DB->get_record_select('task_scheduled', $where, $params, '*', MUST_EXIST);
    local_queue_item_prepare($queuerefresher);
    return true;
}

function  local_queue_requeue_orphans($time) {
    global $DB;

    $where = 'timestarted < :timestarted AND running = 1';
    $params = ['timestarted' => $time];
    return $DB->set_field_select('local_queue_items', 'running', 0, $where, $params);
}