<?php
/**
 * @package PhpTaskDaemon
 * @subpackage Daemon\Ipc
 * @copyright Copyright (C) 2011 Dirk Engels Websolutions. All rights reserved.
 * @author Dirk Engels <d.engels@dirkengels.com>
 * @license https://github.com/DirkEngels/PhpTaskDaemon/blob/master/doc/LICENSE
 */

namespace PhpTaskDaemon\Daemon\Ipc;

/**
 * The Daemon\Ipc\FileSystem class is responsible for storing and retrieving
 * inter process communication data from the database.
 * 
 */
class FileSystem extends IpcAbstract implements IpcInterface {

    /**
     * Returns an array with registered keys.
     * 
     * @return array
     */
    public function getKeys() {
        return array();
    }


    /**
     * Returns nothing (NULL).
     *  
     * @todo Read IPC keys from a filesystem
     * @param string $key
     * @return NULL
     */
    public function getVar($key) {
    }


    /**
     * Sets a key using the filesystem.
     * 
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function setVar($key, $value) {
        $keyFile = $this->_getFileForKey($key);
        return file_put_contents($keyFile, $value);
    }


    /**
     * Increment a key registered as a filename.
     * 
     * @param string $key
     * @return bool
     */
    public function incrementVar($key, $count = 1) {
        $keyFile = $this->_getFileForKey($key);
        $value = (int) file_get_contents($keyFile);
        $value += $count;
        return file_put_contents($keyFile, $value);
    }


    /**
     * Decrements the value of the key stored in a file.
     * 
     * @param string $key
     * @return bool
     */
    public function decrementVar($key, $count = 1) {
        $keyFile = $this->_getFileForKey($key);
        $value = (int) file_get_contents($keyFile);
        if ($value > 0) {
            $value -= $count;
            return file_put_contents($keyFile, $value);
        }
        return false;
    }


    /**
     * Removes the file from the filesystem.
     *  
     * @param string $key
     * @return bool
     */
    public function removeVar($key) {
        $keyFile = $this->_getFileForKey($key);
        if (file_exists($keyFile)) {
            return unlink($keyFile);
        }
        return false;
    }


    /**
     * Removes nothing.
     * 
     * @return bool
     */
    public function remove() {
    }

    /**
     * Returns the filename for a specific key.
     * 
     * @param string $key
     * @return string
     */
    protected function _getFileForKey($key) {
        return \TMP_PATH . '/' . $this->getId() . '_' . strtolower($key);
    }

}
