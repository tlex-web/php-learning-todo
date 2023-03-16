
<?php

require_once 'Database.php';
require_once 'helpers/functions.php';
require_once '../model/Task.php';
require_once '../model/Response.php';
require_once '../model/Image.php';

function retrieveTaskImages($db, $taskid, $userid) {

    $query = $db->prepare('SELECT tblimages.id, tblimages.taskid, tblimages.title, tblimages.filename, tblimages.mimetype FROM tblimages, tbltasks WHERE tbltasks.id = :taskid AND tbltasks.userid = :userid AND tbltasks.id = tblimages.taskid');
    $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
    $query->bindParam(':userid', $userid, PDO::PARAM_INT);
    $query->execute();

    $returnData = array();

    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $image = new Image($row['id'], $row['taskid'], $row['title'], $row['filename'], $row['mimetype']);
        $returnData[] = $image->returnImageArray();
    }

    return $returnData;
}

try {

    $writeDB = Database::connectWriteDB();
    $readDB = Database::connectReadDB();
}
catch (PDOException $e) {

    error_log('Database connection error:' . $e, 0);
    sendResponse(500, false, 'Database connection error');
}

// Authorization
if (!isset($_SERVER["HTTP_AUTHORIZATION"]) || strlen($_SERVER["HTTP_AUTHORIZATION"]) < 1) {

    $response = new Response();
    $response->setHttpStatusCode(401);
    $response->setSuccess(false);
    isset($_SERVER["HTTP_AUTHORIZATION"]) ? $response->addMessage('Access token is missing from the header') : false;
    strlen($_SERVER["HTTP_AUTHORIZATION"]) < 1 ? $response->addMessage('Access token cannot be blank') : false;
    $response->send();
    exit;
}

$access_token = $_SERVER["HTTP_AUTHORIZATION"];

try {

    $query = $writeDB->prepare('SELECT userid, accesstokenexpiry, active, loginattempts FROM tblsessions, tblusers WHERE tblsessions.userid = tblusers.id AND accesstoken = :accesstoken');
    $query->bindParam(':accesstoken', $access_token, PDO::PARAM_STR);
    $query->execute();

    $rows = $query->rowCount();

    if ($rows === 0) {

        sendResponse(401, false, 'Invalid access token');
    }

    $row = $query->fetch(PDO::FETCH_ASSOC);

    $returned_userid = $row['userid'];
    $returned_active = $row['active'];
    $returned_accesstokenexpiry = $row['accesstokenexpiry'];
    $returned_loginattempts = $row['loginattempts'];

    if ($returned_active !== 'Y') {

        sendResponse(401, false, 'User is inactive');
    }

    if ($returned_loginattempts >= 3) {

        sendResponse(401, false, 'User is currently logged out');
    }

    if (strtotime($returned_accesstokenexpiry) < time()) {

        sendResponse(401, false, 'Access token expired');
    }
}
catch (PDOException $e) {

    sendResponse(500, false, 'There was an error authenticating');
}

// tasks/id
if (array_key_exists('taskid', $_GET)) {

    $taskID = $_GET['taskid'];

    if (empty($taskID) || !is_numeric($taskID)) {

        sendResponse(400, false, 'Task ID must be provided or numeric');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        try {

            $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tblTasks WHERE id = :taskid AND userid = :userid');
            $query->bindParam(':taskid', $taskID, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rows = $query->rowCount();

            if ($rows === 0) {

                sendResponse(404, false, 'Task not found');
            }

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {

                $imageArray = retrieveTaskImages($readDB, $taskID, $returned_userid);

                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed'], $imageArray);

                $taskArray[] = $task->returnTaskArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rows;
            $returnData['tasks'] = $taskArray;

            sendResponse(200, true, 'success', true, $returnData);
        }
        catch (ImageException $e) {
            sendResponse(500, false, $e->getMessage());
        }
        catch (TaskException $e) {

            sendResponse(500, false, $e->getMessage());
        }
        catch (PDOException $e) {

            error_log('Database connection error:' . $e, 0);
            sendResponse(500, false, 'Failed to get task');
        }
    }
    elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

        try {

            $imageQuery = $readDB->prepare('SELECT tblimages.id, tblimages.taskid, tblimages.title, tblimages.filename, tblimages.mimetype FROM tblimages, tbltasks WHERE tbltasks.id = :taskid AND tbltasks.userid = :userid AND tblimages.taskid = tbltasks.id');
            $imageQuery->bindParam(':taskid', $taskID, PDO::PARAM_INT);
            $imageQuery->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $imageQuery->execute();

            while ($row = $imageQuery->fetch(PDO::FETCH_ASSOC)) {
                $writeDB->beginTransaction();
                $image = new Image($row['id'], $row['taskid'], $row['title'], $row['filename'], $row['mimetype']);

                $imageID = $image->getID();

                $query = $writeDB->prepare('DELETE tblimages FROM tblimages, tbltasks WHERE tblimages.id = :imageid AND tblimages.taskid = :taskid AND tbltasks.userid = :userid AND tblimages.taskid = tbltasks.id');
                $query->bindParam(':imageid', $imageID, PDO::PARAM_INT);
                $query->bindParam(':taskid', $taskID, PDO::PARAM_INT);
                $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
                $query->execute();

                $image->deleteImageFile();

                $writeDB->commit();
            }

            $query = $writeDB->prepare('DELETE FROM tbltasks WHERE id = :taskid AND userid = :userid');
            $query->bindParam(':taskid', $taskID, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rows = $query->rowCount();

            if ($rows === 0) {

                sendResponse(404, false, 'Task not found');
            }

            $imageFolder = '../../../project-files/rest-todo-images/task' . $taskID;

            if (is_dir($imageFolder)) {
                rmdir($imageFolder);
            }

            sendResponse(200, true, 'Task deleted');
        }
        catch (ImageException $e) {

            if ($writeDB->inTransaction()) {
                $writeDB->rollBack();
            }
            sendResponse(500, false, $e->getMessage());
        }
        catch (PDOException $e) {

            if ($writeDB->inTransaction()) {
                $writeDB->rollBack();
            }
            error_log('Database connection error:' . $e, 0);
            sendResponse(500, false, 'Failed to delete the task');
        }
    } 
    elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') try {

        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {

            sendResponse(400, false, 'Content type header not set to JSON');
        }

        $rawData = file_get_contents('php://input');

        if (!$jsonData = json_decode($rawData)) {

            sendResponse(400, false, 'Request body is not valid JSON');
        }

        $title_updated = false;
        $description_updated = false;
        $deadline_updated = false;
        $completed_updated = false;

        $queryFields = "";

        if (isset($jsonData->title)) {

            $title_updated = true;
            $queryFields .= "title = :title, ";
        }

        if (isset($jsonData->description)) {

            $description_updated = true;
            $queryFields .= "description = :description, ";
        }

        if (isset($jsonData->deadline)) {

            $deadline_updated = true;
            $queryFields .= "deadline = STR_TO_DATE(:deadline, '%d/%m/%Y %H:%i'), ";
        }

        if (isset($jsonData->completed)) {

            $completed_updated = true;
            $queryFields .= "completed = :completed, ";
        }

        $queryFields = rtrim($queryFields, ", ");

        if (!$title_updated && !$description_updated && !$deadline_updated && !$completed_updated) {

            sendResponse(400, false, 'No task fields provided');
        }

        $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE id = :id AND userid = :userid');
        $query->bindParam(':id', $taskID, PDO::PARAM_INT);
        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->execute();

        $rows = $query->rowCount();

        if ($rows === 0) {

            sendResponse(404, false, 'Task to update not found');
        }

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {

            $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
        }

        $queryUPDATE = "UPDATE tbltasks SET " . $queryFields . " WHERE id = :id AND userid = :userid";
        $query = $writeDB->prepare($queryUPDATE);

        if ($title_updated) {

            $task->setTitle($jsonData->title);
            $title_update = $task->getTitle();
            $query->bindParam(':title', $title_update, PDO::PARAM_STR);
        }

        if ($description_updated) {

            $task->setDescription($jsonData->description);
            $description_update = $task->getDescription();
            $query->bindParam(':description', $description_update, PDO::PARAM_STR);
        }

        if ($deadline_updated) {

            $task->setDeadline($jsonData->deadline);
            $deadline_update = $task->getDeadline();
            $query->bindParam(':deadline', $deadline_update, PDO::PARAM_STR);
        }

        if ($completed_updated) {

            $task->setCompleted($jsonData->completed);
            $completed_update = $task->getCompleted();
            $query->bindParam(':completed', $completed_update, PDO::PARAM_STR);
        }

        $query->bindParam(':id', $taskID, PDO::PARAM_INT);
        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->execute();

        $rows = $query->rowCount();

        if ($rows === 0) {

            sendResponse(500, false, 'Task update failed');
        }

        $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE id = :id AND userid = :userid');
        $query->bindParam(':id', $taskID, PDO::PARAM_INT);
        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->execute();

        $rows = $query->rowCount();

        if ($rows === 0) {

            sendResponse(404, false, 'No updated task found');
        }

        $taskArray = array();

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {

            $imageArray = retrieveTaskImages($writeDB, $taskID, $returned_userid);

            $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed'], $imageArray);

            $taskArray[] = $task->returnTaskArray();
        }

        $returnData = array();
        $returnData['rows_returned'] = $rows;
        $returnData['tasks'] = $taskArray;

        sendResponse(200, true, 'Task updated', true, $returnData);
    }
    catch (ImageException $e) {
        sendResponse(500, false, $e->getMessage());
    }
    catch (TaskException $e) {

        sendResponse(500, false, $e->getMessage());
    }
    catch (PDOException $e) {
        echo $e->getMessage();

        error_log('Database connection error:' . $e, 0);
        sendResponse(500, false, 'Failed to get the task');
    }
    else {

        sendResponse(405, false, 'Request method not allowed');
    }
}
// tasks/completed incompleted
elseif (array_key_exists('completed', $_GET)) {

    $completed = $_GET['completed'];

    if ($completed !== 'Y' && $completed !== 'N') {

        sendResponse(400, false, 'Completed filter must be Y or N');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        try {

            $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE completed = :completed AND userid = :userid');
            $query->bindParam(':completed', $completed);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rows = $query->rowCount();

            $taskArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {

                $imageArray = retrieveTaskImages($readDB, $row['id'], $returned_userid);

                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed'], $imageArray);

                $taskArray[] = $task->returnTaskArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rows;
            $returnData['task'] = $taskArray;

            sendResponse(200, true, true, $returnData);
        }
        catch (ImageException $e) {
            sendResponse(500, false, $e->getMessage());
        }
        catch (TaskException $e) {

            sendResponse(500, false, $e->getMessage());
        }
        catch (PDOException $e) {

            error_log('Database error:', $e, 0);
            sendResponse(500, false, 'Failed to get tasks');
        }
    }
    else {

        sendResponse(405, false, 'Request method not allowed');
    }
}
// tasks/page
elseif (array_key_exists('page', $_GET)) {

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        $page = $_GET['page'];

        if (empty($page) || !is_numeric($page)) {

            sendResponse(400, false, 'Page number must be given or numeric');
        }
        else {

            $limitPerPage = 20;

            try {

                $query = $readDB->prepare('SELECT COUNT(id) as totalNumberOfTasks FROM tbltasks WHERE userid = :userid');
                $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
                $query->execute();
    
                $row = $query->fetch(PDO::FETCH_ASSOC);

                $taskCount = intval($row['totalNumberOfTasks']);

                $numberOfPages = ceil($taskCount/$limitPerPage);

                if ($numberOfPages == 0) {

                    $numberOfPages = 1;
                }

                if ($page > $numberOfPages || $page == 0) {

                    sendResponse(404, false, 'Page not found');
                }

                $offset = ($page == 1 ? 0 : ($limitPerPage * ($page - 1))); 
    
                $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE userid = :userid LIMIT :pglimit OFFSET :offset');
                $query->bindParam(':pglimit', $limitPerPage, PDO::PARAM_INT);
                $query->bindParam(':offset', $offset, PDO::PARAM_INT);
                $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
                $query->execute();

                $rows = $query->rowCount();

                $taskArray = array();

                while ($row = $query->fetch(PDO::FETCH_ASSOC)) {

                    $imageArray = retrieveTaskImages($readDB, $row['id'], $returned_userid);

                    $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed'], $imageArray);

                    $taskArray[] = $task->returnTaskArray();
                }

                $returnData = array();
                $returnData['rows_returned'] = $rows;
                $returnData['total_rows'] = $taskCount;
                $returnData['total_pages'] = $numberOfPages;
                $page < $numberOfPages ? $returnData['has_next_page'] = true : $returnData['has_next_page'] = false;
                $page > 1 ? $returnData['has_previous_page'] = true : $returnData['has_previous_page'] = false;
                $returnData['tasks'] = $taskArray;
    
                $response = new Response();
                sendResponse(200, true, true, $returnData);
            }
            catch (ImageException $e) {
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage($e->getMessage());
                $response->send();
                exit;
            }
            catch (TaskException $e) {
    
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage($e->getMessage());
                $response->send();
                exit;
            }
            catch (PDOException $e) {
    
                error_log("Database error" . $e->getMessage(), 0);
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage('Failed to get tasks');
                $response->send();
                exit;
            }
        }
    }
    else {

        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage('Request method not allowed');
        $response->send();
        exit;
    }
}
// tasks
elseif (empty($_GET)) {

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        try {

            $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE userid = :userid');
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rows = $query->rowCount();

            $taskArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {

                $imageArray = retrieveTaskImages($readDB, $row['id'], $returned_userid);

                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed'], $imageArray);
                $taskArray[] = $task->returnTaskArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rows;
            $returnData['tasks'] = $taskArray;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit;
        }
        catch (ImageException $e) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit;
        }
        catch (TaskException $e) {

            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit;
        }
        catch (PDOException $e) {

            error_log("Database error" . $e->getMessage(), 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage('Failed to get tasks');
            $response->send();
            exit;
        }
    }
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

        try {

            if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {

                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage('Content type header is not set to JSON');
                $response->send();
                exit;
            }

            $rawData = file_get_contents('php://input');

            if (!$jsonData = json_decode($rawData)) {

                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage('Request body is not valid JSON');
                $response->send();
                exit;
            }
            else {

                if (!isset($jsonData->title) || !isset($jsonData->completed)) {

                    $response = new Response();
                    $response->setHttpStatusCode(400);
                    $response->setSuccess(false);
                    !isset($jsonData->title) ? $response->addMessage('Task title must be provided') : false;
                    !isset($jsonData->completed) ? $response->addMessage('Task completed status must be provied') : false;
                    $response->send();
                    exit;
                }
                
                $newTask = new Task(null, $jsonData->title, (isset($jsonData->description) ? $jsonData->description : null), (isset($jsonData->deadline) ? $jsonData->deadline : null), $jsonData->completed);

                $title = $newTask->getTitle();
                $description = $newTask->getDescription();
                $deadline = $newTask->getDeadline();
                $completed = $newTask->getCompleted();

                $query = $writeDB->prepare('INSERT INTO tbltasks (title, description, deadline, completed, userid) VALUES (:title, :description, STR_TO_DATE(:deadline, \'%d/%m/%Y %H:%i\'), :completed, :userid)');
                $query->bindParam(':title', $title, PDO::PARAM_STR);
                $query->bindParam(':description', $description, PDO::PARAM_STR);
                $query->bindParam(':deadline', $deadline, PDO::PARAM_STR);
                $query->bindParam(':completed', $completed, PDO::PARAM_STR);
                $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
                $query->execute();

                $rows = $query->rowCount();

                if ($rows === 0) {

                    $response = new Response();
                    $response->setHttpStatusCode(500);
                    $response->setSuccess(false);
                    $response->addMessage('Failed to insert the new task');
                    $response->send();
                    exit;
                }

                $lastTaskID = $writeDB->lastInsertId();

                $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE id = :lastTaskID AND userid = :userid');
                $query->bindParam(':lastTaskID', $lastTaskID, PDO::PARAM_INT);
                $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
                $query->execute();

                $row = $query->rowCount();

                if ($rows === 0) {

                    $response = new Response();
                    $response->setHttpStatusCode(500);
                    $response->setSuccess(false);
                    $response->addMessage('Failed to retrieve the new task');
                    $response->send();
                    exit;
                }

                $taskArray = array();

                while ($row = $query->fetch(PDO::FETCH_ASSOC)) {

                    $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);

                    $taskArray[] = $task->returnTaskArray();
                }

                $returnData = array();
                $returnData['rows_returned'] = $rows;
                $returnData['tasks'] = $taskArray;

                $response = new Response();
                $response->setHttpStatusCode(201);
                $response->setSuccess(true);
                $response->toCache(true);
                $response->addMessage('Task created');
                $response->setData($returnData);
                $response->send();
                exit;
            }
        }
        catch (TaskException $e) {

            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit;
        }
        catch (PDOException $e) {
            echo $e->getMessage();

            error_log('Database error:' . $e->getMessage(), 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage('Failed to insert task to database');
            $response->send();
            exit;
        }
    }
    else {

        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage('Request method not allowed');
        $response->send();
        exit;
    }
}
// -
else {

    $response = new Response();
    $response->setHttpStatusCode(404);
    $response->setSuccess(false);
    $response->addMessage('Endpoint not found');
    $response->send();
    exit;
}