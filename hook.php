<?php
set_error_handler('mailerror');

function mailerror($code, $text, $file, $line) {
    global $error_mail;
    try {
        throw new \ErrorException($text.PHP_EOL." on ".$file.":".$line, $code);
    } catch (Exception $ex) {
        $msg = $ex->getMessage();
        mail($error_mail, 'Githook endpoint error', $msg.PHP_EOL.$ex);
    }
    return true;
}

require('hook-handler.php');
?>