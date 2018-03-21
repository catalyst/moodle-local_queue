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
define('QUEUE_SERVICES_FOLDER', LOCAL_QUEUE_FOLDER.'/classes/services');
define('QUEUE_REFRESHER_CLASS', '\local_queue\task\cron_queue_refresher');
define('QUEUE_ITEMS_TABLE', 'local_queue_items');
define('QUEUE_ITEM_SETTINGS_TABLE', 'local_queue_items_settings');

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
 * Get queue task user-friendly name.
 *
 * @param string $classname classname
 * @return string
 */
function local_queue_task_name($classname) {
    $obj = new $classname();
    return method_exists($obj, 'get_name') ? $obj->get_name() : local_queue_extract_classname($classname);
}
/**
 * Extracts moodle cron task table name from a fully-qualified-class-name string
 *
 * @param string $taskname fully-qualified-class-name string.
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

/**
 * Get the cron task record by type (adhoc/scheduled) and id
 *
 * @param string $type cron task type string.
 * @param string $id table entry id.
 * @return object
 */
function local_queue_cron_task_record($type, $id) {
    global $DB;

    return $DB->get_record(local_queue_cron_task_table($type), ['id' => $id], '*', MUST_EXIST);
}

/**
 * Get a list of classes from a folder by type.
 *
 * @param string $dir directory string.
 * @param array $types array of parent classes or interfaces.
 * @param array $classes array to load the results in.
 * @return array
 */
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
                $abstract = false;
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
                    if ($tokens[$i - 1][0] == T_ABSTRACT) {
                        $abstract = true;
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
                                if (!$abstract) {
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
            }
        } else if ($value != "." && $value != ".." && $value != ".git" && $value != "tests") {
            ini_set('memory_limit', '1024M');
            $classes = local_queue_class_scanner($path, $types, $classes);
        }
    }
    return $classes;
}

/**
 * Check if a folder is empty.
 *
 * @param string $folder directory string.
 * @return boolean
 */
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

/**
 * Remove a local_queue folder recursively.
 *
 * @param string $dir directory string.
 */
function local_queue_rm_local_dir($dir) {
    global $CFG;

    $folder = $CFG->localcachedir. DIRECTORY_SEPARATOR.$dir;
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

/**
 * Get the local_queue general settings configuration.
 *
 * @param string|null $wanted specific setting name string.
 * @return array|string.
 */
function local_queue_configuration($wanted = null) {
    $defaults = [
        'mechanics' => true,
        'keeplogs' => false,
        'usenice' => false,
        'pipesnumber' => 10,
        'waittime' => 500000,
        'attempts' => 10,
        'priority' => 5,
        'mainqueueservice' => '\\local_queue\\services\\DatabaseQueueService'
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
        if (isset($config['local_queue_waittime'])) {
            $defaults['waittime'] = $config['local_queue_waittime'];
        }
        if (isset($config['local_queue_attempts'])) {
            $defaults['attempts'] = $config['local_queue_attempts'];
        }
        if (isset($config['local_queue_priority'])) {
            $defaults['priority'] = $config['local_queue_priority'];
        }
        if (isset($config['local_queue_mainqueueservice'])) {
            $defaults['mainqueueservice'] = $config['local_queue_mainqueueservice'];
        }
    }
    if ($wanted != null && isset($defaults[$wanted])) {
        return $defaults[$wanted];
    }
    return $defaults;
}

/**
 * Refresh the plugin's configuration if different from cached verion.
 *
 */
function refresh_configuration() {
    global $DB;

    $cache = \cache::make('core', 'config');
    $plugin = 'local_queue';
    $result = $DB->get_records_menu('config_plugins', array('plugin' => $plugin), '', 'name,value');    
    $cachedresult = $cache->get($plugin);
    if ($result != $cachedresult){
        $cache->set($plugin, $result);
    }
}

/**
 * Create/update the cron refresher queue item.
 *
 */
function  local_queue_cron_refresher() {
    global $DB;

    $where = 'classname = :classname';
    $params = ['classname' => QUEUE_REFRESHER_CLASS];
    $cronrefresher = $DB->get_record_select('task_scheduled', $where, $params, '*', MUST_EXIST);
    $item = \local_queue\QueueItemHelper::prepare_queue_item($cronrefresher, 'cron');
    unset($cronrefresher);
    ob_start();
    $queueservice = local_queue_configuration('mainqueueservice');
    $queueservice::publish($item, 'cron');
    ob_clean();
    unset($item);
}

/**
 * Get list of queue items settings.
 *
 * @param string $queue the name of the queue to load items settings.
 * @return array.
 */
function local_queue_items_settings($queue = 'cron') {
    global $DB;

    $condition = ['queue' => $queue];
    $settings = $DB->get_records(QUEUE_ITEM_SETTINGS_TABLE, $condition, 'priority ASC');
    if (count($settings) <= 1) {
        $scheduled = \core\task\manager::get_all_scheduled_tasks();
        $settings = [];
        foreach ($scheduled as $key => $task) {
            $classname = "\\". get_class($task);
            $hash = stripslashes($classname);
            $itemsetting = \local_queue\QueueItemHelper::item_settings_data($classname, $queue);
            $settings[$hash] = $itemsetting;
            unset($itemsetting);
            unset($scheduled[$key]);
        }
    }
    return $settings;
}

/**
 * Get the record of queue items settings.
 *
 * @param int|string $id the ID of the queue item settings.
 * @return array.
 */
function local_queue_item_settings($id) {
    global $DB;

    $where = 'id = :id';
    $params = ['id' => $id];
    $task = (array) $DB->get_record_select(QUEUE_ITEM_SETTINGS_TABLE, $where, $params, '*', MUST_EXIST);
    $task['name'] = local_queue_task_name($task['classname']);
    return $task;
}
