<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>ToDo | Error: <?php echo isset($_GET["error"]) ? $_GET["error"] : null;?></title>

        <style>
            p.fehler {
                background-color: #FFFFDF;
                padding: 15px;
            }
        </style>

    </head>
<body>

<?php
if (isset($_GET["error"])) {

    switch ($_GET["error"]) {

        case "400":
            $message = 'Die Anfrage konnte so nicht verstanden werden!';
            break;

        case "401":
            $message = 'Sie haben keine Berechtigung, diesen Zugriff auszuführen! Es wurde ein verbotener Zugriff auf dem Webserver ausgeführt.';
            break;

        case "403":
            $message = 'Verbotene Anfrage! Es wurde eine Verbotene Anfrage an den Webserver gesendet.';
        break;

        case "404":
            $message = 'Die aufgerufene Seite wurde nicht gefunden! Möglicherweise wurde die Seite vom Webmaster entfernt oder es ist ein Fehler am Webserver aufgetreten.';
            break;

        case "500":
            $message = 'Interner Fehler am Webserver! Es ist ein Interner Fehler am Webserver aufgetreten.';
        break;

        case "503":
            $message = 'Der Webserver ist zur Zeit überlastet! Der Webserver hat wegen Überlastung den Dienst (zeitweise) eingestellt!';
        break;

        default:
            $message = 'Fehler am Webserver! Es ist ein Fehler am Webserver aufgetreten.';
    }

    // Output
    echo '<p class="fehler">' . $message . '&mdash; Wir bitten um Entschuldigung!<br>';
    echo '<a href="javascript:history.back();">Zurück zur vorherigen Seite</a> - ';
    echo '<a href="index.php">Zur Startseite</a></p>';

    // Save errors
    $error = $_GET["error"] . ", " . date("d.m.Y H:i") . ", " . $_SERVER['HTTP_REFERER'] . " ," . $_SERVER['REMOTE_ADDR'] . "\n";
    $handler = fOpen("../error_log.txt" , "a+");
    fWrite($handler, $error);
    fClose($handler);
}
?>

</body>
</html>