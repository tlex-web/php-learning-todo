
<?php

class ImageException extends Exception {}

class Image {

    private mixed $_id = null;
    private ?int $_taskid = null;
    private ?string $_title;
    private ?string $_filename;
    private ?string $_mimetype;
    private string $_target_dir;

    public function __construct($id, $taskid, $title, $filename, $mimetype)  {

        $this->setID($id);
        $this->setTaskID($taskid);
        $this->setTitle($title);
        $this->setFilename($filename);
        $this->setMimetype($mimetype);
        $this->_target_dir = '../../../project-files/rest-todo-images/task';
    }

    public function getID(): int
    {
        return $this->_id;
    }

    public function getTaskID(): int
    {
        return $this->_taskid;
    }

    public function getTitle(): string
    {
        return $this->_title;
    }

    public function getFilename(): string
    {
        return $this->_filename;
    }

    public function getFileExtension(): string
    {
        $filenameparts = explode(".", $this->_filename);
        $lastArrayElement = count($filenameparts) - 1;
        $fileExtension = $filenameparts[$lastArrayElement];

        return $fileExtension;
    }

    public function getMimetype(): string
    {
        return $this->_mimetype;
    }

    public function getFolderLocation(): string
    {
        return $this->_target_dir;
    }

    public function getImageURL(): string
    {
        $httpOrhttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $url = '/rest-todo/tasks/' . $this->getTaskid() . '/images/' . $this->getID();

        return $httpOrhttps . '://' . $host . $url;
    }

    public function setID($id) {

        if (($id !== null) && (!is_numeric($id) || $id <= 0 || $id > 92233372036854775807 || $this->_id !== null)) {

            throw new ImageException('Image ID Error');
        }
        $this->_id = $id;
    }

    public function setTaskID($taskid) {

        if (($taskid !== null) && (!is_numeric($taskid) || $taskid <= 0 || $taskid > 92233372036854775807 || $this->_taskid !== null)) {

            throw new ImageException('Task ID Error');
        }
        $this->_taskid = $taskid;
    }

    public function setTitle($title) {

        if (strlen($title) < 1 || strlen($title) > 255) {

            throw new ImageException('Image Title Error');
        }
        $this->_title = $title;
    }

    public function setFilename($filename) {

        if (strlen($filename) < 1 || strlen($filename) > 255 || preg_match("/^[a-zA-Z0-9_-]+(.jpg|.gif|.png)$/", $filename) !== 1) {

            throw new ImageException('Image filename must be between 1 and 255 characters and only be of type jpg, gif or png');
        }
        $this->_filename = $filename;
    }

    public function setMimetype($mimetype) {

        if (strlen($mimetype) < 1 || strlen($mimetype) > 255) {

            throw new ImageException('Image MIME Type Error');
        }
        $this->_mimetype = $mimetype;
    }

    public function saveFile($temp) {

        $filePath = $this->getFolderLocation().$this->getTaskID().'/'.$this->getFilename();

        if (!is_dir($this->getFolderLocation().$this->getTaskID())) {
            if (!mkdir($this->getFolderLocation().$this->getTaskID())) {
                throw new ImageException('Failed to create image folder');
            }
        }

        if (!file_exists($temp)) {
            throw new ImageException('Failed to upload image file');
        }

        if (!move_uploaded_file($temp, $filePath)) {
            throw new ImageException('Failed to upload image file');
        }
    }

    public function returnImageFile() {

        $path = $this->getFolderLocation().$this->getTaskID().'/'.$this->getFilename();

        if (!file_exists($path)) {
            throw new ImageException('Image File not found');
        }

        header('Content-Type: ' . $this->getMimetype());
        header('Content-Disposition: inline; filename="' . $this->getFilename() . '"');

        if (!readfile($path)) {
            http_response_code(404);
            exit;
        }

        exit;
    }

    public function updateImageFile($oldFilename, $newFilename) {

        $oldPath = $this->getFolderLocation() . $this->getTaskID() . '/' . $oldFilename;
        $newPath = $this->getFolderLocation() . $this->getTaskID() . '/' . $newFilename;

        if (!file_exists($oldPath)) {
            throw new ImageException('Cannot file image file to rename');
        }

        if (!rename($oldPath, $newPath)) {
            throw new ImageException('Failed to update the filename');
        }
    }

    public function deleteImageFile() {

        $path = $this->getFolderLocation() . $this->getTaskID() . '/' . $this->getFilename();

        if (file_exists($path)) {
            if (!unlink($path)) {
                throw new ImageException('Failed to delete Image File');
            }
        }
    }

    public function returnImageArray(): array
    {
        $image = array();
        $image['id'] = $this->getID();
        $image['taskid'] = $this->getTaskID();
        $image['title'] = $this->getTitle();
        $image['filename'] = $this->getFilename();
        $image['mimetype'] = $this->getMimetype();
        $image['imageurl'] = $this->getImageURL();

        return $image;
    }
}