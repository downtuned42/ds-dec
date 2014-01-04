<?php

ini_set('display_errors', 1);


//****
//AUSKOMMENTEIREN UM DEBUGMODE ZU AKTIVIEREN
error_reporting(0);

// endpoint of the diskstation web-api
// if these scripts are hosted on the diskstation as well 'localhost' will do fine
define('DS_API_ENDPOINT', 'https://localhost:5000/webapi');
// Api user
define('DS_API_USER', 'admin');
// Api password
define('DS_API_PASSWD', 'YOUR_PASSWORD_HERE');

set_exception_handler(function ($e) {
    if ($e instanceof FormErrorException) {
        $decTypes = array(DlcDecrypter::TYPE_DCRYPTIT, DlcDecrypter::TYPE_LINKDECRYPTER);
        $msg = $e->getMessage();
    } else {
        $msg = "AN UNEXPECTED EXCEPTION OCCURRED:\n\n" . $e->getMessage() . "\n\n" . $e->getTraceAsString();
        $decTypes = array(DlcDecrypter::TYPE_DCRYPTIT, DlcDecrypter::TYPE_LINKDECRYPTER);
    }
    
    $html = <<<HTML
<html>
    <head>
    <title>DownloadStation Decrypter</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-theme.min.css" rel="stylesheet">
    </head>
    <body>

<div class="container">
    <form action="{$_SERVER['PHP_SELF']}" enctype="multipart/form-data" method="POST" role="form">
    <input type="hidden" name="action" value="dlcDecrypt">
      <div class="header">
        <ul class="nav nav-pills pull-right">       
        </ul>
        <h3 class="text-muted">DownloadStation Decrypter</h3>
      </div>

      <div class="jumbotron">
        <pre style="color:red;">$msg</pre> 
        <h2>Decrypter:</h2>             
        <select name="decType" class="form-control">
            <option value="{$decTypes[0]}">dcrypt.it</option>
            <option value="{$decTypes[1]}">linkdecrypter.com</option>
        </select>   
      </div>

      <div class="row marketing">
        <div class="col-lg-12">
          <div class="form-group" style="margin-top:15px;">
            <label for="url">Enter URL to dlc:</label>
            <input type="text" class="form-control" id="url" name="dlcUrl" placeholder="e.g. http://ncrypt.in/container/dlc/698edd9235235jh23g5jh235235hg23.dlc">
          </div>
          <input type="submit" name="actionDlcUrl" value="Extract from DLC URL" class="btn btn-success">
          <div class="form-group" style="margin-top:15px;">
            <label for="actionDlcContents">Enter DLC contents:</label>
            <textarea class="form-control" rows="3" wrap="auto" cols="77" name="dlcContents" placeholder="e.g. mMmqWutI4D3XDkr5xa1RzXjNPYJMQhj....."></textarea>
          </div>
          <input type="submit" name="actionDlcContents" value="Extract from DLC contents" class="btn btn-success">
          <div class="form-group" style="margin-top:15px;">
            <label for="dlcFile">Upload DLC file:</label>
            <input type="file" id="dlcFile" name="dlcFile">
            <p class="help-block">Upload a DLC File.</p>
          </div>              
          <input type="submit" name="actionDlcFile" value="Extract from DLC file" class="btn btn-success">
        </div>

        <div class="col-lg-12">
        <br><br>
            <p>Nothing of the above. Take me straight to where I can enter my links directly:</p>
            <input type="submit" name="actionEnterLinks" class="btn btn-success" value="ENTER LINKS MANUALLY!" >
        </div>
        </form>    
    </div>
    <script src="js/bootstrap.js"></script>
    <script src="js/jquery.js"></script>
    </body>
</html>
HTML;
    echo $html;  
});