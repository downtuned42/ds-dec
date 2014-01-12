<?php

require_once 'config.inc.php';
require_once 'lib.inc.php';

$passwdMasker = new StringMasker(DS_API_PASSWD);
set_exception_handler(function ($e) use ($passwdMasker) {
    /* @var Exception $e */
    if ($e instanceof ExpectedException) {
        $msg = $e->getMessage();
    } else {
        $msg = "AN UNEXPECTED EXCEPTION OCCURRED:\n\n" . $e->getMessage() . "\n\n" . $e->getTraceAsString();
    }
    // mask password
    $msg = $passwdMasker->mask($msg);
    $msg = strip_tags($msg);

    $tpl = new Pmte('error.phtml');
    echo $tpl->render(array('msg' => $msg));
});

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
        'hosterList' => $GLOBALS['filenameScraper'],
        'links' => $links
    );
    echo $tpl->render($subst);

} else if (isset($_REQUEST['filenameLookup'])) { // linkform has been submitted to lookup filenames for links

    $links = trim($_REQUEST['links']);
    $pattern = $GLOBALS['filenameScraper'][$_REQUEST['hoster']][1];

    $linkArr = array();
    $filenameArr = array();
    LinkParser::parseLinkStr($links, $linkArr, $filenameArr);

    $scr = new Scraper($pattern, $linkArr);
    $res = $scr->scrape(10);
    $linkList = array();
    foreach ($res as $linkRes) {
        $linkList[] =  $linkRes->url . ((!empty($linkRes->match)) ? "\t" . '[' . $linkRes->match  .']' : '');
    }

    $tpl = new Pmte('links.phtml');
    $subst = array(
        'hosterList' => $GLOBALS['filenameScraper'],
        'links' => implode("\n", $linkList)
    );
    echo $tpl->render($subst);
} else if ($_REQUEST['action'] == 'addLinks') {

    $links = trim($_REQUEST['links']);
    $linkArr = array();
    $filenameArr = array();
    LinkParser::parseLinkStr($links, $linkArr, $filenameArr);

    if (empty($linkArr)) {
        throw new ExpectedException('No links to add!');
    }

    $unpackPasswd = trim($_REQUEST['unpackPasswd']);

    $api = new SynoWebApi(DS_API_ENDPOINT);

    $passwd = trim($_REQUEST['passwd']);
    if (!$passwd) {
        $passwd = DS_API_PASSWD;
    } else {
        // if password was provided via form, update the masker so it may mask the passwd in case of an error
        $passwdMasker->setSubjectToMask($passwd);
    }

    $res = $api->login(DS_API_USER, $passwd);
    $msg = "SYNO.API.Auth::login: SUCCESS";
    if (DEBUG) {
        $msg .= "\nREQUEST-INFO:\n" . print_r($api->lastRequestInfo, true);
    }

    $api->addLinks(implode(',', $linkArr), $unpackPasswd);
    $msg .= "\n" . "SYNO.DownloadStation.Task::create: SUCCESS";
    if (DEBUG) {
        $msg .= "\nREQUEST-INFO:\n" . print_r($api->lastRequestInfo, true);
    }

    $msg .= "\n\n" . "Links successfully added to DownloadStation!";

    $tpl = new Pmte('result.phtml');
    $subst = array(
        'msg' => $passwdMasker->mask($msg)
    );
    echo $tpl->render($subst);
}