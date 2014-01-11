<?php


//http://stackoverflow.com/questions/3431703/having-trouble-limiting-download-size-of-phps-curl-function?lq=1

echo "<pre>";

$fp = fopen("http://ul.to/erzfkz3m", 'r');
while(!feof($fp)) {
    $data .= fread($fp, 8192);
    $found = preg_match_all("|<a .*id=\"filename\".*>(.*)</a>|", $data, $matches);
    if ($found) {
        $name = $matches[1][0];
        break;
    }
}
fclose($fp);
echo "<pre>". var_export($name, true);