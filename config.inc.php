<?php

// set to 1 for displaying errors

ini_set('display_errors', 1);

// endpoint of the diskstation web-api
// if these scripts are hosted on the diskstation as well 'localhost' will do fine
define('DS_API_ENDPOINT', 'http://localhost:5000/webapi');
// Api user
define('DS_API_USER', 'admin');
// Api password
define('DS_API_PASSWD', 'ENTER_PASSWORD_HERE');

set_exception_handler(function ($e) {
    /* @var Exception $e */
    if ($e instanceof ExpectedException) {
        $msg = $e->getMessage();
    } else {
        $msg = "AN UNEXPECTED EXCEPTION OCCURRED:\n\n" . $e->getMessage() . "\n\n" . $e->getTraceAsString();
    }

    // mask password
    $msg = str_ireplace(DS_API_PASSWD, 'XXXXXX', $msg);

    $tpl = new Pmte('error.phtml');
    echo $tpl->render(array('msg' => $msg));
});