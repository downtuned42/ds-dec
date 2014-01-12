<?php


//http://stackoverflow.com/questions/3431703/having-trouble-limiting-download-size-of-phps-curl-function?lq=1

echo "<pre>";
/*
$fp = fopen("http://uploaded.net/file/0wjxfbm3", 'r');
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
*/

/*
function get_html($url){
    $ch = curl_init();
    $res = new stdClass;
    $res->content = '';
    $res->name = '';
    $res->bytesRead = 0;

    $callback = function ($ch, $str) use ($res) {
        $res->content .= $str;
        $resLength = strlen($str);
        $res->bytesRead += $resLength;
        $found = preg_match_all("|<a .*id=\"filename\".*>(.*)</a>|", $res->content, $matches);
        if ($found) {
            $res->name = $matches[1][0];
            return -1;
        }

        echo "resLength: " . $resLength . "<br>";

        return $resLength; //return the exact length
    };
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, $callback);
    curl_exec($ch);
    curl_close($ch);

    return $res;
}

$urls = array(
    'http://uploaded.net/file/t97w0xje',
    'http://uploaded.net/file/fadaxh19',
    'http://uploaded.net/file/1ae6ddhv',
    'http://uploaded.net/file/6uxnij08',
    'http://uploaded.net/file/n44sr3i0'
);

$res = get_html($urls[0]);
echo "<pre>". var_export(strip_tags($res->name), true) . "<br>bytesRead: " . $res->bytesRead;
*/

/*
class Scraper
{
    private $result = array();
    private $pattern;

    public function __construct($pattern) {
        $this->pattern = $pattern;
    }

    function scrape($urls)
    {
        $curly = array();
        $mh = curl_multi_init();
        foreach ($urls as $key => $url) {
            $callback = $this->getWriteFunction($key);
            $curly[$key] = curl_init();
            curl_setopt($curly[$key], CURLOPT_URL, $url);
            curl_setopt($curly[$key], CURLOPT_HEADER, 0);
            curl_setopt($curly[$key], CURLOPT_WRITEFUNCTION, $callback);
            curl_multi_add_handle($mh, $curly[$key]);
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
        } while($running > 0);

        foreach($curly as $cnt) {
            curl_multi_remove_handle($mh, $cnt);
        }

        curl_multi_close($mh);

        return $this->result;
    }

    function getWriteFunction($key)
    {
        $this->result[$key] = new stdClass;
        $this->result[$key]->charsRead = 0;
        $this->result[$key]->content = '';
        $this->result[$key]->match = '';

        $res = $this->result[$key];
        $pattern = $this->pattern;

        $funky = function ($ch, $str) use ($res, $pattern) {
            $res->content .= $str;
            $length = strlen($str);
            $res->charsRead += $length;
            $found = preg_match_all($pattern, $res->content, $matches);
            if ($found) {
                $res->match = $matches[1][0];
                return -1;
            }
            return $length;
        };
        return $funky;
    }
}

$urls = array(
    'http://jdownloader.org',
    'http://uploaded.net/file/t97w0xje',
    'http://uploaded.net/file/fadaxh19',
    'http://uploaded.net/file/1ae6ddhv',
    'http://uploaded.net/file/6uxnij08',
    'http://uploaded.net/file/n44sr3i0'
);

$scr = new Scraper("|<a .*id=\"filename\".*>(.*)</a>|");
$res = $scr->scrape($urls);

echo "<pre>";
foreach ($res as $curr) {
    echo var_export($curr->match, true) . "<br>";
}
*/

require_once 'RollingCurl.php';

class Scraper
{
    private $result = array();
    private $pattern;
    private $rc;

    public function __construct($pattern, array $urls) {
        $this->pattern = $pattern;

        $rc = new RollingCurl();
        $result = array();
        foreach ($urls as $key => $url) {
            $request = new RollingCurlRequest($url);
            $callback = $this->getWriteFunction($key);
            $request->options = array(CURLOPT_WRITEFUNCTION => $callback);
            $rc->add($request);
        }
        $this->rc = $rc;
    }

    function scrape($window=5)
    {
        $this->rc->execute($window);
        return $this->result;
    }

    function getWriteFunction($key)
    {
        $this->result[$key] = new stdClass;
        $this->result[$key]->charsRead = 0;
        $this->result[$key]->content = '';
        $this->result[$key]->match = '';

        $res = $this->result[$key];
        $pattern = $this->pattern;

        $funky = function ($ch, $str) use ($res, $pattern) {
            $res->content .= $str;
            $length = strlen($str);
            $res->charsRead += $length;
            $found = preg_match_all($pattern, $res->content, $matches);
            if ($found) {
                $res->match = $matches[1][0];
                return -1;
            }
            return $length;
        };
        return $funky;
    }
}


$urls = array(
    'http://jdownloader.org',
    'http://uploaded.net/file/t97w0xje',
    'http://uploaded.net/file/fadaxh19',
    'http://uploaded.net/file/1ae6ddhv',
    'http://uploaded.net/file/6uxnij08',
    'http://uploaded.net/file/n44sr3i0'
);

$scr = new Scraper("|<a .*id=\"filename\".*>(.*)</a>|", $urls);
$res = $scr->scrape(2);

echo "<pre>";
foreach ($res as $curr) {
    echo var_export($curr->match, true) . "<br>";
}



