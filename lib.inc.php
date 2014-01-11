<?php

require_once 'RollingCurl.php';

class ExpectedException extends Exception {
}

/**
 * Simple cURL based Http-Client
 */
class HttpClient
{

    public $lastRequestInfo;

    public function post($url, array $args=array(), $headers=array())
    {
        $query = '';
        if (is_array($args) && count($args)) {
            $query = http_build_query($args);
        }

        $curlOpt = array(
            CURLOPT_URL => $url,
            CURLOPT_POST =>  1,
            CURLOPT_POSTFIELDS => $query,
            CURLOPT_HEADER =>  1, // returns header AND response-body as one string
            CURLOPT_RETURNTRANSFER => true
        );
        if (is_array($headers) && count($headers)) {
            $curlOpt[CURLOPT_HTTPHEADER] = $headers;
        }
        if ((stripos($url, 'https') === 0) ? true : false) {
            $curlOpt[CURLOPT_SSL_VERIFYPEER] = false;
        }

        $ch = curl_init();
        curl_setopt_array($ch, $curlOpt);

        $response = curl_exec($ch);

        // split header and response-body
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        // get http-status of response
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $reqInfo = new stdClass;
        $reqInfo->url = $url;
        $reqInfo->query = $query;
        $reqInfo->requestHeader = $headers;
        $reqInfo->response = $body;
        $reqInfo->responseHeader = $header;
        $this->lastRequestInfo = $reqInfo;

        // Check if any error occurred
        if (curl_errno($ch) || $status != 200) {
            curl_close($ch);
            throw new \RuntimeException(
                "Failed issuing request:\n"
                . "REQUEST-INFO:\n" . print_r($this->lastRequestInfo, true)
            );
        }

        curl_close($ch);

        return $body;
    }
}

class DlcDecrypter extends HttpClient {
    const TYPE_LINKDECRYPTER = 1;
    const TYPE_DCRYPTIT = 2;
    
    private static $types = array(self::TYPE_LINKDECRYPTER, self::TYPE_DCRYPTIT);
    private $type;
    
    public function __construct($type)
    {
        if (!in_array($type, self::$types)) {
            throw new \RuntimeException("invalid decrypter-type [$type]. Check self::types for valid types");
        }
        $this->type = $type;
    }
    
    public function decrypt($dlc)
    {
        if ($this->type == self::TYPE_LINKDECRYPTER) {
            return $this->decLinkdecrypter($dlc);
        } else if($this->type == self::TYPE_DCRYPTIT) {
            return $this->decDcryptit($dlc);
        } else {
            throw new \RuntimeException("Couldn't determine decrypt implementation, shouldn't happen!");
        }
    }
    
    private function getDlcContents($dlc)
    {
        if (stripos($dlc, 'http://') !== false) {
            // URL
            $dlcContents = file_get_contents($dlc);
        // suppress warning e.g. "Warning: file_exists(): File name is longer than the maximum allowed path length on this platform (4096):"
        // if $dlc contains contents which is most likely if filename is too long.
        } else if (@file_exists($dlc)) {
            // File
            $dlcContents = file_get_contents($dlc);
        } else {
            // contents
            $dlcContents = $dlc;
        }
        
        return $dlcContents;
    }
    
    /**
     * Decrypts the given DLC-URL.
     * Decryption is done utilizing the fine service of http://linkdecrypter.com/ 
     * 
     * @param string $dlc The URL pointing to the DLC to decrypt, the DLC contents or an DLC file
     *
     * @return array Array, containing the links found in decrypted DLC
     */
    private function decLinkdecrypter($dlc)
    {
        $dlcContents = $this->getDlcContents($dlc);
        $url = 'http://linkdecrypter.com/?ck';
        $args = array('cnt_text' => $dlcContents);
        $headers = array(
            'Accept:text/html',
            'Connection:keep-alive',
            'User-Agent:Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.39 Safari/537.36'
        );
        
        $res = $this->post($url, $args, $headers);
        
        // extract links from textarea, because linkdecrypter.com offers no service-api we have to parse the html-response
        // note: this approach will fail if html-response changes in future!
        preg_match_all('#<textarea id="des".*>([^<]*)</textarea>#Usi', $res, $match);
        $res = explode("\n\n", $match[1][0]);

        return $res[1];
    }
    
    private function decDcryptit($dlc)
    {
        $dlcContents = $this->getDlcContents($dlc);
        $headers = array(
            'Accept:application/json, text/javascript, */*',
            'Connection:keep-alive',
            'Content-Type:application/x-www-form-urlencoded',
            'Host:dcrypt.it',
            'Origin:http://dcrypt.it',
            'Referer:http://dcrypt.it/',
            'User-Agent:Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/32.0.1700.55 Safari/537.36',
            'X-Requested-With:XMLHttpRequest'
        );
        $url = 'http://dcrypt.it/decrypt/paste';
        $args = array('content' => $dlcContents);
        
        $res =  $this->post($url, $args, $headers);

        $decRes = json_decode($res);
        if (is_object($decRes) && isset($decRes->success) && is_array($decRes->success->links)) {
            $links = $decRes->success->links;
        } else {
            throw new \RuntimeException(
                'Failed parsing response: ' . var_export($res, true)
            );
        }

        return implode("\n", $links);
    }    
}

class SynoWebApi extends HttpClient
{
    private $endpoint;
    private $sid;

    public function __construct($endpoint)
    {
        $this->endpoint = $endpoint;
    }

    public function login($user, $passwd)
    {
        $args = array(
            'version' => 2,
            'format' => 'cookie',
            'session' => 'DownloadStation',
            'api' => 'SYNO.API.Auth',
            'method' => 'login',
            'account' => $user,
            'passwd' => $passwd
        );

        $url = $this->endpoint . '/auth.cgi';
        $res = $this->post($url, $args);
        $res = json_decode($res);

        if (!isset($res->success) || !$res->success) {
            throw new RuntimeException(
                "Got error response from Syno-Api:\n"
                . "REQUEST-INFO:\n" . print_r($this->lastRequestInfo, true)
            );
        }

        $this->sid = $res->data->sid;

        return $res;
    }

    public function addLinks($linkList, $unpackPasswd='')
    {
        $args = array(
            'version' => 1,
            '_sid' => $this->sid,
            'api' => 'SYNO.DownloadStation.Task',
            'method' => 'create',
            'uri' => $linkList
        );
        if ($unpackPasswd) {
            $args['unzip_password'] = $unpackPasswd;
        }

        $url = $this->endpoint . '/' . 'DownloadStation/task.cgi';
        $res = $this->post($url, $args);
        $res = json_decode($res);

        if (!isset($res->success) || !$res->success) {
            throw new RuntimeException(
                "Got error response from Syno-Api:\n"
                . "REQUEST-INFO:\n" . print_r($this->lastRequestInfo, true)
            );
        }
        return $res;
    }
}

/**
 * (P)oor(M)ans(T)emplate(E)ngine
 */
class Pmte {
    
    private $templateFile;
    private $subst;
    
    public function __construct($templateFile) {
        $this->templateFile = $templateFile;
    }
    
    public function render(array $subst=array()) {
        $this->subst = $subst;
        $content = '';
        
        if (!file_exists($this->templateFile) || !is_readable($this->templateFile)) {
            throw new \RuntimeException("Template File [$this->templateFile] does not exist or is not readable!");
        }
        
        try {
            ob_start();
            include $this->templateFile;
            $content = ob_get_clean();
        } catch (\Exception $ex) {
            ob_end_clean();
            throw $ex;
        }
        return $content;
    }
    
    public function __get($name) {
        if (isset($this->subst[$name])) {
            return $this->subst[$name];
        } else {
            return null;
        }
    }
}

class Scraper
{
    private $result = array();
    private $pattern;
    private $rc;

    public function __construct($pattern, array $urls) {
        $this->pattern = $pattern;

        $rc = new RollingCurl();
        foreach ($urls as $key => $url) {
            $url = trim($url);
            $request = new RollingCurlRequest($url);
            $callback = $this->getWriteFunction($key, $url);
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

    function getWriteFunction($key, $url)
    {
        $this->result[$key] = new stdClass;
        $this->result[$key]->charsRead = 0;
        $this->result[$key]->content = '';
        $this->result[$key]->url = $url;
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
                //$res->content = null;
                return -1;
            }
            return $length;
        };
        return $funky;
    }
}

class StringMasker {

    private $subjectToMask;
    private $mask;

    public function __construct($subjectToMask, $mask='XXXXXX') {
        $this->subjectToMask = $subjectToMask;
        $this->mask = $mask;
    }
    public function mask($subject) {
        $subject = str_ireplace($this->subjectToMask, $this->mask, $subject);
        // mhh, this is not reliable. Arguments in traces get truncated after 15 characters e.g.:
        // #0 /volume1/web/cadd/index.php(47): SynoWebApi->login('admin', 'ENTER_PASSWORD_...')
        // do some hacking to mask longer strings as well :-/
        if (strlen($this->subjectToMask) > 15) {
            $subject = str_ireplace(substr($this->subjectToMask, 0, 15) . '...', $this->mask, $subject);
        }
        return $subject;
    }

    public function setSubjectToMask($subjectToMask) {
        $this->subjectToMask = $subjectToMask;
    }
}