<?php

require_once 'config.inc.php';
require_once 'lib.inc.php';

if (empty($_REQUEST['action'])) {

    $tpl = new Pmte('index.phtml');
    $subst = array(
        'decDcrypt' => DlcDecrypter::TYPE_DCRYPTIT,
        'decLinkdecrypter' => DlcDecrypter::TYPE_LINKDECRYPTER
    );
    echo $tpl->render($subst);

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
        throw new ExpectedException('Please specify at least DLC-URL, DLC-contents or DLC-file!');
    }
    
    $subject = trim($subject);
    if (!empty($subject)) {
        $dec = new DlcDecrypter($_REQUEST['decType']);
        $links = $dec->decrypt($subject);
    }

    $tpl = new Pmte('links.phtml');
    $subst = array(
        'links' => $links
    );
    echo $tpl->render($subst);
} else if ($_REQUEST['action'] == 'addLinks') {

    $links = trim($_REQUEST['links']);
    $unpackPasswd = trim($_REQUEST['unpackPasswd']);
    $linkList = str_replace("\r\n", ',', $links);

    $api = new SynoWebApi(DS_API_ENDPOINT);

    $api->login(DS_API_USER, DS_API_PASSWD);
    $msg = "SYNO.API.Auth::login: SUCCESS";

    $api->addLinks($linkList, $unpackPasswd);
    $msg .= "\n" . "SYNO.DownloadStation.Task::create: SUCCESS";

    $msg .= "\n\n" . "Links successfully added to DownloadStation!";

    $tpl = new Pmte('result.phtml');
    $subst = array(
        'msg' => $msg
    );
    echo $tpl->render($subst);
}