<?php

// set to 1 for displaying errors
ini_set('display_errors', 1);

// true if additional debug output should be provided in webfrontend / errormessages, false otherwise
// Note: debug-output may contain sensitive information if enabled!
define('DEBUG', false);// endpoint of the diskstation web-api
// if these scripts are hosted on the diskstation as well 'localhost' will do fine
define('DS_API_ENDPOINT', 'http://localhost:5000/webapi');
// Api user
define('DS_API_USER', 'admin');
// Api password - ENTER_PASSWORD_HERE
define('DS_API_PASSWD', 'ENTER_PASSWORD_HERE');
