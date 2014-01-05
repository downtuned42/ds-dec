<?php

// set to 1 for displaying errors

ini_set('display_errors', 1);


//****
//comment this out to disable debug (NOTICE) messages
//ini_set('display_errors', 0);

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
    // mhh, this is not reliable. Arguments in traces get truncated after 15 characters e.g.:
    // #0 /volume1/web/cadd/index.php(47): SynoWebApi->login('admin', 'ENTER_PASSWORD_...')
    // do some hacking to mask longer passwords as well :-/
    if (strlen(DS_API_PASSWD) > 15) {
        $msg = str_ireplace(substr(DS_API_PASSWD, 0, 15) . '...', 'XXXXXX', $msg);
    }

    $tpl = new Pmte('error.phtml');
    echo $tpl->render(array('msg' => $msg));
});