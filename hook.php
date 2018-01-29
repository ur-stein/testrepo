<?php
// script errors will be send to this email:
$error_mail = 'david.stein@mailbox.org';
set_exception_handler('mailException');
set_error_handler('errorToException');

function errorToException($code, $text, $file, $line) {
    throw new \ErrorException($text.PHP_EOL." on ".$file.":".$line, $code);
    //die quietly. No need to spam the server logs.
    exit;
}

function mailException($e) {
    global $error_mail;
    if (filter_var($error_mail, FILTER_VALIDATE_EMAIL) !== false) {
        $msg = $e->getMessage();
        mail($error_mail, 'Githook endpoint error', $msg.PHP_EOL.$e);
    }
    //die quietly. No need to spam the server logs.
    exit;
}

require('hook-handler.php');
?>