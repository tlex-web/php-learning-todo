
<?php 

require_once 'Database.php';
require_once 'helpers/functions.php';
require_once '../model/Response.php';
require_once '../model/Image.php';

function uploadImage($readDB, $writeDB, $taskid, $userid) {

    try {

        if (!isset($_SERVER["CONTENT_TYPE"]) || !str_contains($_SERVER["CONTENT_TYPE"], 'multipart/form-data; boundary=')) {

            sendResponse(400, false, 'Content Type Header is not set to multipart/form-data');
        }

        $query = $readDB->prepare('SELECT id FROM tbltasks WHERE id = :taskid AND userid = :userid');
        $query->bindParam(':taskid', $taskid, PDO::PARAM_STR);
        $query->bindParam(':userid', $userid, PDO::PARAM_INT);
        $query->execute();

        $rows = $query->rowCount();

        if ($rows === 0) {
            sendResponse(404, false, 'Task not found');
        }

        if (!isset($_POST['attributes'])) {
            sendResponse(400, false, 'Attributes missing from the body');
        }

        if (!$jsonData = json_decode($_POST['attributes'])) {
            sendResponse(400, false, 'Attributes are not valid JSON');
        }

        if (!isset($jsonData->title) || !isset($jsonData->filename) || $jsonData->title == '' || $jsonData->filename == '') {
            sendResponse(400, false, 'Title and Filename are mandatory');
        }

        if (strpos($jsonData->filename, '.') > 0) {
            sendResponse(400, false, 'Filename must not contain a file extension');
        }

        if (!isset($_FILES['imagefile']) || $_FILES['imagefile']['error'] !== 0) {
            sendResponse(500, false, 'Image File could not be uploaded');
        }

        $fileDetails = getimagesize($_FILES['imagefile']['tmp_name']);

        if (isset($_FILES['imagefile']['size']) && $_FILES['imagefile']['size'] > 5242880) {
            sendResponse(400, false, 'File size mus be <5MB');
        }

        $mimetypes = array('image/jpeg', 'image/gif', 'image/png');

        if (!in_array($fileDetails['mime'], $mimetypes)) {
            sendResponse(400, false, 'File Type not supported');
        }

        $fileExtension = '';
        switch ($fileDetails['mime']) {

            case 'image/jpeg':
                $fileExtension = '.jpg';
                break;
            case 'image/gif':
                $fileExtension = '.gif';
                break;
            case 'image/png':
                $fileExtension = '.png';
                break;
            default:
                break;
        }

        if ($fileExtension == '') {
            sendResponse(400, false, 'No valid file extension for MIME Type');
        }

        $image = new Image(null, $taskid, $jsonData->title, $jsonData->filename.$fileExtension, $fileDetails['mime']);

        $title = $image->getTitle();
        $newFileName = $image->getFilename();
        $mimetype = $image->getMimetype();

        $query = $readDB->prepare('SELECT tblimages.id FROM tblimages, tbltasks WHERE tblimages.taskid = tbltasks.id AND tbltasks.id = :taskid AND tbltasks.userid = :userid AND tblimages.filename = :filename');
        $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
        $query->bindParam(':userid', $userid, PDO::PARAM_INT);
        $query->bindParam(':filename', $newFileName, PDO::PARAM_STR);
        $query->execute();

        $rows = $query->rowCount();

        if ($rows === 1) {
            sendResponse(409,false, 'A file with that filename already exits');
        }

        $writeDB->beginTransaction();

        $query = $writeDB->prepare('INSERT INTO tblimages (taskid, title, filename, mimetype) VALUES (:taskid, :title, :filename, :mimetype)');
        $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
        $query->bindParam(':title', $title, PDO::PARAM_STR);
        $query->bindParam(':filename', $newFileName, PDO::PARAM_STR);
        $query->bindParam(':mimetype', $mimetype, PDO::PARAM_STR);
        $query->execute();

        $rows = $query->rowCount();

        if ($rows === 0) {
            if ($writeDB->inTransaction()) {
                $writeDB->rollback();
            }
            sendResponse(500, false, 'Failed to upload image');
        }

        $lastID = $writeDB->lastInsertId();

        $query = $writeDB->prepare('SELECT tblimages.id, tblimages.taskid, tblimages.title, tblimages.filename, tblimages.mimetype FROM tblimages, tbltasks WHERE tblimages.id = :imageid AND tbltasks.id = :taskid AND tbltasks.userid = :userid AND tblimages.taskid = tbltasks.id');
        $query->bindParam(':imageid', $lastID, PDO::PARAM_INT);
        $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
        $query->bindParam(':userid', $userid, PDO::PARAM_INT);
        $query->execute();

        $rows = $query->rowCount();

        if ($rows === 0) {
            if ($writeDB->inTransaction()) {
                $writeDB->rollback();
            }
            sendResponse(500, false, 'Failed to retrieve image attributes - upload again');
        }

        $returnData = array();

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $image = new Image($row['id'], $row['taskid'], $row['title'], $row['filename'], $row['mimetype']);
            $returnData[] = $image->returnImageArray();
        }

        $image->saveFile($_FILES['imagefile']['tmp_name']);

        $writeDB->commit();

        sendResponse(201, true, 'Image successfully uploaded', false, $returnData);
    }
    catch (PDOException $e) {
        error_log('Connection Error' . $e, 0);
        if ($writeDB->inTransaction()) {
            $writeDB->rollback();
        }
        sendResponse(500, false, 'Database connection error');
    }
    catch (ImageException $e) {
        if ($writeDB->inTransaction()) {
            $writeDB->rollback();
        }
        sendResponse(500, false, $e->getMessage());
    }
}

function getImageAttributes($readDB, $taskid, $imageid, $userid) {

    try {

        $query = $readDB->prepare('SELECT tblimages.id, tblimages.taskid, tblimages.title, tblimages.filename, tblimages.mimetype FROM tblimages, tbltasks WHERE tblimages.id = :imageid AND tbltasks.id = :taskid AND tbltasks.userid = :userid AND tblimages.taskid = tbltasks.id');
        $query->bindParam(':imageid', $imageid, PDO::PARAM_INT);
        $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
        $query->bindParam(':userid', $userid, PDO::PARAM_INT);
        $query->execute();

        $rows = $query->rowCount();

        if ($rows === 0) {
            sendResponse(404, false, 'Image Not Found');
        }

        $returnData = array();

        while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $image = new Image($row['id'], $row['taskid'], $row['title'], $row['filename'], $row['mimetype']);
            $returnData[] = $image->returnImageArray();
        }

        sendResponse(200, true, null, true, $returnData);
    }
    catch (ImageException $e) {
        sendResponse(500, false, $e->getMessage());
    }
    catch (PDOException $e) {
        error_log('Connection Error: ' . $e, 0);
        sendResponse(500, false, 'Failed to get image attributes');
    }
}

function getImageRoute($readDB, $taskid, $imageid, $userid) {

    try {

        $query = $readDB->prepare('SELECT tblimages.id, tblimages.taskid, tblimages.title, tblimages.filename, tblimages.mimetype FROM tblimages, tbltasks WHERE tblimages.id = :imageid AND tbltasks.id = :taskid AND tbltasks.userid = :userid AND tblimages.taskid = tbltasks.id');
        $query->bindParam(':imageid', $imageid, PDO::PARAM_INT);
        $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
        $query->bindParam(':userid', $userid, PDO::PARAM_INT);
        $query->execute();

        $rows = $query->rowCount();

        if ($rows === 0) {
            sendResponse(404, false, 'Image Not Found');
        }

        $image = null;

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $image = new Image($row['id'], $row['taskid'], $row['title'], $row['filename'], $row['mimetype']);
        }

        if ($image == null) {
            sendResponse(500, false, 'Image not found');
        }

        $image->returnImageFile();
    }
    catch (ImageException $e) {
        sendResponse(500, false, 'Could not get the Image');
    }
    catch (PDOException $e) {
        error_log('Connection error: ' . $e, 0);
        sendResponse(500, false, 'Database connection error');
    }
}

function updateImageAttributes($writeDB, $taskid, $imageid, $userid) {

    try {

        if ($_SERVER["CONTENT_TYPE"] !== 'application/json') {
            sendResponse(400, false, 'Content Type Header not set to JSON');
        }

        $rawData = file_get_contents('php://input');

        if (!$jsonData = json_decode($rawData)) {
            sendResponse(400, false, 'Request body is not valid JSON');
        }

        $title_updated = false;
        $filename_updated = false;
        $queryFields = '';

        if (isset($jsonData->title)) {
            $title_updated = true;
            $queryFields .= 'tblimages.title = :title, ';
        }

        if (isset($jsonData->filename)) {
            if (str_contains($jsonData->filename, ".")) {
                sendResponse(400, false, 'Filename cannot contain file extensions');
            }
            $filename_updated = true;
            $queryFields .= 'tblimages.filename = :filename, ';
        }

        $queryFields = rtrim($queryFields, ', ');

        if ($title_updated === false && $filename_updated === false) {
            sendResponse(400, false, 'No image fields provided');
        }

        $writeDB->beginTransaction();

        $query = $writeDB->prepare('SELECT tblimages.id, tblimages.taskid, tblimages.title, tblimages.filename, tblimages.mimetype FROM tblimages, tbltasks WHERE tblimages.id = :imageid AND tblimages.taskid = :taskid AND tbltasks.userid = :userid AND tblimages.taskid = tbltasks.id');
        $query->bindParam(':imageid', $imageid, PDO::PARAM_INT);
        $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
        $query->bindParam(':userid', $userid, PDO::PARAM_INT);
        $query->execute();

        $rows = $query->rowCount();

        if ($rows === 0) {
            if ($writeDB->inTransaction()) {
                $writeDB->rollBack();
            }
            sendResponse(404, false, 'No Image Found');
        }

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $image = new Image($row['id'], $row['taskid'], $row['title'], $row['filename'], $row['mimetype']);
        }

        $stmt = "UPDATE tblimages INNER JOIN tbltasks ON tblimages.taskid = tbltasks.id SET " . $queryFields . " WHERE tblimages.id = :imageid AND tblimages.taskid = :taskid AND tblimages.taskid = tbltasks.id AND tbltasks.userid = :userid";

        $query = $writeDB->prepare($stmt);
        if ($title_updated) {
            $image->setTitle($jsonData->title);
            $title = $image->getTitle();
            $query->bindParam(':title', $title, PDO::PARAM_STR);
        }

        if ($filename_updated) {
            $oldFilename = $image->getFilename();
            $image->setFilename($jsonData->filename. '.' . $image->getFileExtension());
            $filename = $image->getFilename();
            $query->bindParam(':filename', $filename, PDO::PARAM_STR);
        }

        $query->bindParam(':imageid', $imageid, PDO::PARAM_INT);
        $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
        $query->bindParam(':userid', $userid, PDO::PARAM_INT);
        $query->execute();

        $rows = $query->rowCount();

        if ($rows === 0) {
            if ($writeDB->inTransaction()) {
                $writeDB->rollBack();
            }
            sendResponse(400, false, 'Image attributes not updated - The new attributes may be the same as the old');
        }

        $query = $writeDB->prepare('SELECT tblimages.id, tblimages.taskid, tblimages.title, tblimages.filename, tblimages.mimetype FROM tblimages, tbltasks WHERE tblimages.id = :imageid AND tblimages.taskid = :taskid AND tbltasks.userid = :userid AND tblimages.taskid = tbltasks.id');
        $query->bindParam(':imageid', $imageid, PDO::PARAM_INT);
        $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
        $query->bindParam(':userid', $userid, PDO::PARAM_INT);
        $query->execute();

        $rows = $query->rowCount();

        if ($rows === 0) {
            if ($writeDB->inTransaction()) {
                $writeDB->rollBack();
            }
            sendResponse(404, false, 'No Image Found');
        }

        $returnData = array();

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $image = new Image($row['id'], $row['taskid'], $row['title'], $row['filename'], $row['mimetype']);
            $returnData[] = $image->returnImageArray();
        }

        if ($filename_updated) {
            $image->updateImageFile($oldFilename, $filename);
        }

        $writeDB->commit();

        sendResponse(200, true, 'Image attributes updated', false, $returnData);
    }
    catch (ImageException $e) {
        if ($writeDB->inTransaction()) {
            $writeDB->rollBack();
        }
        sendResponse(500, false, 'Failed to update Image attributes');
    }
    catch (PDOException $e) {
        echo $e->getMessage();
        error_log('Connection error: ' . $e, 0);
        if ($writeDB->inTransaction()) {
            $writeDB->rollBack();
        }
        sendResponse(500, false, 'Database connection error');
    }
}

function deleteImageRoute($writeDB, $taskid, $imageid, $userid) {

    try {

        $writeDB->beginTransaction();

        $query = $writeDB->prepare('SELECT tblimages.id, tblimages.taskid, tblimages.title, tblimages.filename, tblimages.mimetype FROM tblimages, tbltasks WHERE tblimages.id = :imageid AND tblimages.taskid = :taskid AND tbltasks.userid = :userid AND tblimages.taskid = tbltasks.id');
        $query->bindParam('imageid', $imageid, PDO::PARAM_INT);
        $query->bindParam('taskid', $taskid, PDO::PARAM_INT);
        $query->bindParam(':userid', $userid, PDO::PARAM_INT);
        $query->execute();

        $rows = $query->rowCount();

        if ($rows === 0) {
            $writeDB->rollBack();
            sendResponse(404, false, 'Image Not Found');
        }

        $image = null;

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $image = new Image($row['id'], $row['taskid'], $row['title'], $row['filename'], $row['mimetype']);
        }

        if ($image == null) {
            sendResponse(500, false, 'Failed to get Image');
        }

        $query = $writeDB->prepare('DELETE tblimages FROM tblimages, tbltasks WHERE tblimages.id = :imageid AND tbltasks.id = :taskid AND tblimages.taskid = tbltasks.id AND tbltasks.userid = :userid');
        $query->bindParam(':imageid', $imageid, PDO::PARAM_INT);
        $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
        $query->bindParam(':userid', $userid, PDO::PARAM_INT);
        $query->execute();

        $rows = $query->rowCount();

        if ($rows === 0) {
            $writeDB->rollBack();
            sendResponse(404, false, 'Image Not Found');
        }

        $image->deleteImageFile();

        $writeDB->commit();

        sendResponse(200, true, 'Image deleted');
    }
    catch (ImageException $e) {
        $writeDB->rollBack();
        sendResponse(500, false, $e->getMessage());
    }
    catch (PDOException $e) {
        error_log('Connection error' . $e, 0);
        $writeDB->rollBack();
        sendResponse(500, false, 'Failed to delete image');
    }
}

try {
    $writeDB = Database::connectWriteDB();
    $readDB = Database::connectReadDB();
}
catch (PDOException $e) {
    error_log('Connection Error: ' . $e, 0);
    sendResponse(500, false, 'Database connection error');
}

$returned_userid = checkAuthAndReturnUserID($writeDB);

// tasks/id/images/id/attributes
if (array_key_exists('taskid', $_GET) && array_key_exists('imageid', $_GET) && array_key_exists('attributes', $_GET)) {

    $taskid = $_GET['taskid'];
    $imageid = $_GET['imageid'];
    $attributes = $_GET['attributes'];

    if ($imageid == '' || !is_numeric($imageid) || $taskid == '' || !is_numeric($taskid)) {

        sendResponse(400, false, 'Image ID or Task ID cannot be blank or must be numeric');
    }

    if ($_SERVER["REQUEST_METHOD"] === 'GET') {

        getImageAttributes($readDB, $taskid, $imageid, $returned_userid);
    }
    elseif ($_SERVER["REQUEST_METHOD"] === 'PATCH') {

        updateImageAttributes($writeDB, $taskid, $imageid, $returned_userid);
    }
    else {
        sendResponse(405, false, 'Request method not allowed');
    }
}

// tasks/id/images/id
elseif (array_key_exists('taskid', $_GET) && array_key_exists('imageid', $_GET)) {

    $taskid = $_GET['taskid'];
    $imageid = $_GET['imageid'];

    if ($taskid == '' || !is_numeric($taskid) || $imageid == '' || !is_numeric($imageid)) {

        sendResponse(400, false, 'Task ID or Image ID cannot be blank or must be numeric');
    }

    if ($_SERVER["REQUEST_METHOD"] === 'GET') {

        getImageRoute($readDB, $taskid, $imageid, $returned_userid);
    }
    elseif ($_SERVER["REQUEST_METHOD"] === 'DELETE') {

        deleteImageRoute($writeDB, $taskid, $imageid, $returned_userid);
    }
    else {
        sendResponse(405, false, 'Request method not allowed');
    }
}

// tasks/id/images
elseif (array_key_exists('taskid', $_GET)) {

    $taskid = $_GET['taskid'];

    if ($taskid == '' || !is_numeric($taskid)) {
        sendResponse(400, false, 'Task ID cannot be blank or must be numeric');
    }

    if ($_SERVER["REQUEST_METHOD"] === 'POST') {

        uploadImage($readDB, $writeDB, $taskid, $returned_userid);
    }
    else {
        sendResponse(405, false, 'Request method not allowed');
    }
}

else {
    sendResponse(404, false, 'Endpoint not found');
}