
<?php

require_once 'Database.php';
require_once '../model/Response.php';

try {

    $writeDB = Database::connectWriteDB();
}
catch (PDOException $e) {

    error_log('Connection Error: ' . $e, 0);
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage('Database connection error');
    $response->send();
    exit;
}

if (array_key_exists("sessionid", $_GET)) {

    $session_id = $_GET["sessionid"];

    if ($session_id === '' || !is_numeric($session_id)) {

        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $session_id === '' ? $response->addMessage('Session ID cannot be blank') : false;
        !is_numeric($session_id) ? $response->addMessage('Session ID must be numeric') : false;
        $response->send();
        exit;
    }

    if (!isset($_SERVER["HTTP_AUTHORIZATION"]) || strlen($_SERVER["HTTP_AUTHORIZATION"]) < 1) {

        $response = new Response();
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        !isset($_SERVER["HTTP_AUTHORIZATION"]) ? $response->addMessage('Access Token missing') : false;
        strlen($_SERVER["HTTP_AUTHORIZATION"]) < 1 ? $response->addMessage('Access Token cannot be blank') : false;
        $response->send();
        exit;
    }

    $access_token = $_SERVER["HTTP_AUTHORIZATION"];

    if ($_SERVER["REQUEST_METHOD"] === 'DELETE') {

        try {

            $query = $writeDB->prepare('DELETE FROM tblsessions WHERE id = :id AND accesstoken = :accesstoken');
            $query->bindParam('id', $session_id, PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $access_token, PDO::PARAM_STR);
            $query->execute();

            $rows = $query->rowCount();

            if ($rows === 0) {

                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage('There was an issue logging you out of this session');
                $response->send();
                exit;
            }

            $returnData = array();
            $returnData['session_id'] = intval($session_id);
            
            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->addMessage('Logged out');
            $response->setData($returnData);
            $response->send();
            exit;
        }
        catch (PDOException $e) {

            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage('There was an issue logging you out');
            $response->send();
            exit;
        }
    }
    elseif ($_SERVER["REQUEST_METHOD"] === 'PATCH') {

        if ($_SERVER["CONTENT_TYPE"] !== 'application/json') {

            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage('Content Type Header not set to JSON');
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

        if (!isset($jsonData->refresh_token) || strlen($jsonData->refresh_token) < 1) {

            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            !isset($jsonData->refresh_token) ? $response->addMessage('Refesh Token not supplied') : false;
            strlen($jsonData->refresh_token) < 1 ? $response->addMessage('Refesh Token cannot be blank') : false;
            $response->send();
            exit;
        }

        try {

            $refresh_token = $jsonData->refresh_token;

            $query = $writeDB->prepare('SELECT tblsessions.id as sessionid, tblsessions.userid as userid, accesstoken, refreshtoken, active, loginattempts, accesstokenexpiry, refreshtokenexpiry FROM tblsessions, tblusers WHERE tblusers.id = tblsessions.userid AND tblsessions.id = :sessionid AND tblsessions.accesstoken = :accesstoken AND tblsessions.refreshtoken = :refreshtoken');
            $query->bindParam(':sessionid', $session_id, PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $access_token, PDO::PARAM_STR);
            $query->bindParam(':refreshtoken', $refresh_token, PDO::PARAM_STR);
            $query->execute();

            $rows = $query->rowCount();

            if ($rows === 0) {

                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->addMessage('Access Token or Refresh Token is incorrect');
                $response->send();
                exit;
            }

            $row = $query->fetch(PDO::FETCH_ASSOC);

            $returned_sessionid = $row['sessionid'];
            $returned_userid = $row['userid'];
            $returned_accesstoken = $row['accesstoken'];
            $returned_refreshtoken = $row['refreshtoken'];
            $returned_active = $row['active'];
            $returned_loginattempts = $row['loginattempts'];
            $returned_accesstokenexpiry = $row['accesstokenexpiry'];
            $returned_refreshtokenexpiry = $row['refreshtokenexpiry'];

            if ($returned_active !== 'Y') {

                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->addMessage('User account is not active');
                $response->send();
                exit;
            }

            if ($returned_loginattempts >= 3) {

                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->addMessage('User account is currently locked');
                $response->send();
                exit;
            }

            if (strtotime($returned_refreshtokenexpiry) < time()) {

                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->addMessage('Refresh Token has expired');
                $response->send();
                exit;
            }

            $access_token = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());
            $refreshtoken_token = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());

            $access_token_expiry = 1200;
            $refresh_token_expiry = 1209600;

            $query = $writeDB->prepare('UPDATE tblsessions SET accesstoken = :accesstoken, accesstokenexpiry = date_add(NOW(), INTERVAL :accesstokenexpiry SECOND), refreshtoken = :refreshtoken, refreshtokenexpiry = date_add(NOW(), INTERVAL :refreshtokenexpiry SECOND) WHERE id = :sessionid AND userid = :userid AND accesstoken = :returnedaccesstoken AND refreshtoken = :returnedrefreshtoken');
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->bindParam(':sessionid', $returned_sessionid, PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $access_token, PDO::PARAM_STR);
            $query->bindParam(':accesstokenexpiry', $access_token_expiry, PDO::PARAM_INT);
            $query->bindParam(':refreshtoken', $refresh_token, PDO::PARAM_STR);
            $query->bindParam(':refreshtokenexpiry', $refresh_token_expiry, PDO::PARAM_INT);
            $query->bindParam(':returnedaccesstoken', $returned_accesstoken, PDO::PARAM_STR);
            $query->bindParam(':returnedrefreshtoken', $returned_refreshtoken, PDO::PARAM_STR);
            $query->execute();

            $rows = $query->rowCount();

            if ($rows === 0) {

                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->addMessage('Access Token could not be refreshed');
                $response->send();
                exit;
            }

            $returnData = array();
            $returnData['access_token'] = $access_token;
            $returnData['access_token_expiry'] = $access_token_expiry;
            $returnData['refresh_token'] = $refresh_token;
            $returnData['refresh_token_expiry'] = $refresh_token_expiry;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->addMessage('Token Refreshed');
            $response->setData($returnData);
            $response->send();
            exit;
        }
        catch (PDOException $e) {

            echo $e->getMessage();
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage('There was issue refreshing the access token');
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
elseif (empty($_GET)) {

    if ($_SERVER["REQUEST_METHOD"] !== 'POST') {

        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage('Request method not allowed');
        $response->send();
        exit;
    }

    # Preventing Brute Force attacs by delaying every request by 1 second
    sleep(1);

    if ($_SERVER["CONTENT_TYPE"] !== 'application/json') {

        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage('Content Type Header not set to JSON');
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

    if (!isset($jsonData->username) || !isset($jsonData->password)) {

        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        isset($jsonData->username) ? $response->addMessage('Username not supplied') : false;
        isset($jsonData->password) ? $response->addMessage('Password not supplied') : false;
        $response->send();
        exit;
    }

    if (strlen($jsonData->username) < 1 || strlen($jsonData->username) > 255 || strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255) {

        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        strlen($jsonData->username) < 1 ? $response->addMessage('Username cannot be blank') : false;
        strlen($jsonData->username) > 255 ? $response->addMessage('Username cannot be greater than 255 characters') : false;
        strlen($jsonData->password) < 1 ? $response->addMessage('Password cannot be blank') : false;
        strlen($jsonData->password) > 255 ? $response->addMessage('Password cannot be greater than 255 characters') : false;
        $response->send();
        exit;
    }

    try {

        $username = $jsonData->username;
        $password = $jsonData->password;

        $query = $writeDB->prepare('SELECT id, fullname, username, password, active, loginattempts FROM tblusers WHERE username = :username');
        $query->bindParam(':username', $username, PDO::PARAM_STR);
        $query->execute();

        $rows = $query->rowCount();

        if ($rows === 0) {

            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->addMessage('Username or password is incorrect');
            $response->send();
            exit;
        }

        $row = $query->fetch(PDO::FETCH_ASSOC);

        $returned_id = $row['id'];
        $returned_fullname = $row['fullname'];
        $returned_username = $row['username'];
        $returned_password = $row['password'];
        $returned_active = $row['active'];
        $returned_loginattempts = $row['loginattempts'];

        if ($returned_active === 'N') {

            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->addMessage('User account not active');
            $response->send();
            exit;
        }

        if ($returned_loginattempts >= 3) {

            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage('User account is locked');
            $response->send();
            exit;
        }

        if (!password_verify($password, $returned_password)) {

            $query = $writeDB->prepare('UPDATE tblusers SET loginattempts = loginattempts + 1 WHERE id = :id');
            $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
            $query->execute();

            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->addMessage('Username or Password is incorrect');
            $response->send();
            exit;
        }

        # Creates a base64 string representation of 24 random bytes concatenaded with the current UNIX timestamp 
        $access_token = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());
        $refresh_token = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());

        $access_token_expiry = 1200;
        $refresh_token_expiry = 1209600;
    }
    catch (PDOException $e) {

        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage('There was an issue logging in');
        $response->send();
        exit;
    }

    try {

        $writeDB->beginTransaction();

        $query = $writeDB->prepare('UPDATE tblusers SET loginattempts = 0 WHERE id = :id');
        $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
        $query->execute();

        $query = $writeDB->prepare('INSERT INTO tblsessions (userid, accesstoken, accesstokenexpiry, refreshtoken, refreshtokenexpiry) VALUES (:userid, :accesstoken, date_add(NOW(), INTERVAL :accesstokenexpiry SECOND), :refreshtoken, date_add(NOW(), INTERVAL :refreshtokenexpiry SECOND))');
        $query->bindParam(':userid', $returned_id, PDO::PARAM_INT);
        $query->bindParam(':accesstoken', $access_token, PDO::PARAM_STR);
        $query->bindParam(':accesstokenexpiry', $access_token_expiry, PDO::PARAM_INT);
        $query->bindParam(':refreshtoken', $refresh_token, PDO::PARAM_STR);
        $query->bindParam(':refreshtokenexpiry', $refresh_token_expiry, PDO::PARAM_INT);
        $query->execute();

        $lastSessionID = $writeDB->lastInsertId();

        # Only if the database transaction was successful, the data will be commited to the database. Otherwise the rollBack method runs
        $writeDB->commit();

        $returnData = array();
        $returnData['session_id'] = intval($lastSessionID);
        $returnData['access_token'] = $access_token;
        $returnData['access_token_expiry'] = $access_token_expiry;
        $returnData['refresh_token'] = $refresh_token;
        $returnData['refresh_token_expiry'] = $refresh_token_expiry;

        $response = new Response();
        $response->setHttpStatusCode(201);
        $response->setSuccess(true);
        $response->setData($returnData);
        $response->send();
        exit;
    }
    catch (PDOException $e) {

        # The PDO::rollBack() method rolls back to the initial state of the database, if the database transaction fails
        $writeDB->rollBack();
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage('There was an issue logging you in');
        $response->send();
        exit;
    }
}
else {

    $response = new Response();
    $response->setHttpStatusCode(404);
    $response->setSuccess(false);
    $response->addMessage('Endpoint not found');
    $response->send();
    exit;
}
