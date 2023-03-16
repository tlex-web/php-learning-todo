<?php

require_once 'images.php';


try {
    $i = new Image(null, 1, 't', 'i.jpg', 'image/jpg');
    header('Content-type: application/json');
    echo json_encode($i->returnImageArray());
}catch (ImageException $e) {
    echo $e->getMessage();
}


