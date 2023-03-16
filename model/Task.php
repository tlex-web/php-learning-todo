
<?php

class TaskException extends Exception {}

class Task {

    private ?int $_id = null;
    private string $_title;
    private ?string $_description;
    private mixed $_deadline;
    private string $_completed;
    private array $_images;


    public function __construct($id, $title, $description, $deadline, $completed, $images = array()) {

        $this->setID($id);
        $this->setTitle($title);
        $this->setDescription($description);
        $this->setDeadline($deadline);
        $this->setCompleted($completed);
        $this->setImages($images);
    }
    
    public function getID(): int
    {

        return $this->_id;
    }

    public function getTitle(): string
    {

        return $this->_title;
    }

    public function getDescription(): ?string
    {

        return $this->_description;
    }

    public function getDeadline() {

        return $this->_deadline;
    }

    public function getCompleted(): bool
    {

        return $this->_completed;
    }

    public function getImages(): array
    {
        return $this->_images;
    }

    public function setID($id) {

        if (($id !== null) && (!is_numeric($id) || $id <= 0 || $id > 9223372036854775807 || $this->_id !== null)) {

            throw new TaskException('Task ID error');
        }

        $this->_id = $id;
    }

    public function setTitle($title) {

        if (empty($title) || strlen($title) > 255) {

            throw new TaskException('Task Title error');
        }

        $this->_title = $title;
    }

    public function setDescription($description) {

        if (($description !== null) && (strlen($description) > 16777215)) {

            throw new TaskException('Task Description error');
        }

        $this->_description = $description;
    }

    public function setDeadline($deadline) {

        if ($deadline !== null && date_format(date_create_from_format('d/m/Y H:i', $deadline), 'd/m/Y H:i') != $deadline) {

            throw new TaskException('Task Deadline date time error');
        }

        $this->_deadline = $deadline;
    }

    public function setCompleted($completed) {

        if (strtoupper($completed) !== 'Y' && strtoupper($completed) !== 'N') {

            throw new TaskException('Task completed must be Y or N');
        }

        $this->_completed = $completed;
    }

    public function setImages($images) {

        if (!is_array($images)) {
            throw new TaskException('Images need to be an array');
        }

        $this->_images = $images;
    }

    public function returnTaskArray(): array
    {

        $task = array();
        $task['id'] = $this->getID();
        $task['title'] = $this->getTitle();
        $task['description'] = $this->getDescription();
        $task['deadline'] = $this->getDeadline();
        $task['completed'] = $this->getCompleted();
        $task['images'] = $this->getImages();

        return $task;
    }
}