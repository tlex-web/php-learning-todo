<?php

function sendResponse($statusCode, $success, $message=null, $toCache=false, $data=null) {

    $response = new Response();
    $response->setHttpStatusCode($statusCode);
    $response->setSuccess($success);
    $message !== null ? $response->addMessage($message) : false;
    $response->toCache($toCache);
    $data !== null ? $response->setData($data) : false;
    $response->send();
    exit;
}

function checkAuthAndReturnUserID($writeDB) {

    if (!isset($_SERVER["HTTP_AUTHORIZATION"]) || strlen($_SERVER["HTTP_AUTHORIZATION"]) < 1) {

        $message = null;

        if (!isset($_SERVER["HTTP_AUTHORIZATION"])) {

            $message = 'Access Token is missing from the header';
        }
        else {

            if (strlen($_SERVER["HTTP_AUTHORIZATION"]) < 1) {

                $message = 'Access Token cannot be blank';
            }
        }

        sendResponse(401, false, $message);
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

            sendResponse(401, false, 'User not active');
        }

        if ($returned_loginattempts >= 3) {

            sendResponse(401, false, 'User is not active');
        }

        if (strtotime($returned_accesstokenexpiry) < time()) {

            sendResponse(401, false, 'Access token expired');
        }
        return $returned_userid;
    }
    catch (PDOException $e) {

        sendResponse(500, false, 'There was an issue authenticating');
    }
}