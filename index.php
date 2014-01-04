<?php

require_once 'config.inc.php';
require_once 'lib.inc.php';

if (empty($_REQUEST['action'])) {
    $decTypes = array(DlcDecrypter::TYPE_DCRYPTIT, DlcDecrypter::TYPE_LINKDECRYPTER);
    $html = <<<HTML
<html>
    <head>
    <title>DownloadStation Decrypter</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
} else if ($_REQUEST['action'] == 'dlcDecrypt') { // decrypt form has been submitted
    if (isset($_REQUEST['actionDlcUrl'])) { // url
        $subject = $_REQUEST['dlcUrl'];
    } else if (isset($_REQUEST['actionDlcContents'])) { // contents
        $subject = $_REQUEST['dlcContents'];
    } else if (isset($_REQUEST['actionDlcFile'])) { // file
        $subject = $_FILES['dlcFile']['tmp_name'];
    }
    // one of url, contents or file submit-buttons has been clicked but nothing was specified -> error
    if (!isset($_REQUEST['actionEnterLinks']) && empty($subject)) {
        throw new FormErrorException('Please specify at least DLC-URL, DLC-contents or DLC-file!');
    }
    
    //****
    $subject = trim($subject); // for what !??
    if (!empty($subject)) {
        $dec = new DlcDecrypter($_REQUEST['decType']);
        $links = $dec->decrypt($subject);
    }
    // style="background: rgba(0,20,0,0.8);"
    $html = <<<HTML
<html>
    <head>
    <title>DownloadStation Decrypter</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-theme.min.css" rel="stylesheet">
    </head>
    <body>

<div class="container">
    <form action="{$_SERVER['PHP_SELF']}" method="POST" role="form">
      <div class="header">
        <ul class="nav nav-pills pull-right">       
        </ul>
        <h3 class="text-muted">DownloadStation Decrypter</h3>
      </div>

      <div class="jumbotron">
        <h2>You may edit the links prior submitting them to Download Station:</h2>             
			<textarea class="form-control" wrap="auto" rows="30" name="links">$links</textarea></br>
            <input type="text" name="unpackPasswd" class="form-control" placeholder="Password to unrar/unzip (optional)"><br/>
            <input type="hidden" name="action" value="addLinks">
            <br/><input type="submit" value="ADD LINKS (FINISH)" class="btn btn-success" >
        </form> 
        <span class="btn btn-warning" onClick="history.go(0)">Start over (RESET)</span>
      </div>
    <script src="js/bootstrap.js"></script>
    <script src="js/jquery.js"></script>
    </body>
</html>
HTML;
    echo $html;
} else if ($_REQUEST['action'] == 'addLinks') {
    
    $links = trim($_REQUEST['links']);
    $unpackPasswd = trim($_REQUEST['unpackPasswd']);
    $linkList = str_replace("\r\n", ',', $links);
    
    $api = new SynoWebApi(DS_API_ENDPOINT);

    echo "<html><head><style type=\"text/css\">body { }</style></head><body>";
    
    $res = $api->login(DS_API_USER, DS_API_PASSWD);
    
    echo "SYNO.API.Auth Response:<br/>";
    var_export($res);
    echo "<br/><br/>";

    $res = $api->addLinks($linkList, $unpackPasswd);
    
    echo "SYNO.DownloadStation.Task Response:<br/>";
    var_export($res);
    
    echo "<br/><br/>Links added to DownloadStation!";
    
    echo '</body></html>';
}