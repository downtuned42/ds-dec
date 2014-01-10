<?php

class ExpectedException extends Exception {
}

class DlcDecrypter {
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
    
    private function post($url, array $args=array(), $headers=array())
    {
        $query = '';
        if (is_array($args) && count($args)) {
            $query = http_build_query($args);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        curl_setopt($ch, CURLOPT_HEADER, 1); // returns header AND response-body as one string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        if (is_array($headers) && count($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        
        // split header and response-body
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);        
        
        // get http-status of response
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);        
        
        if ($status != 200) {
            throw new \RuntimeException(
                'Failed issuing request, response headers: ' . var_export($header, true)
            );
        }
        
        return $body;
    }    
}

class SynoWebApi
{
    private $endpoint;
    private $sid;
    private $serverProtocol;
    public $lastRequestInfo;

    public function __construct($endpoint)
    {
        $this->endpoint = $endpoint;
        $this->serverProtocol = 'http';
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
        $res = $this->request('auth.cgi', 'GET', $args);
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
        
        $res = $this->request('DownloadStation/task.cgi', 'POST', $args);
        $res = json_decode($res);
        
        if (!isset($res->success) || !$res->success) {
          throw new RuntimeException(
              "Got error response from Syno-Api:\n"
              . "REQUEST-INFO:\n" . print_r($this->lastRequestInfo, true)
          );
        }
        return $res;
    }
    public function request($uri, $httpMethod, $args, $headers=array())
    {
        $query = '';
        if (is_array($args) && count($args)) {
            $query = http_build_query($args);
        }

        $context_options = array (
            'http' => array (
                'method' => $httpMethod,
                'protocol_version' => '1.1'
            )
        );
        
        $url = $this->endpoint . '/' . $uri;
        if ($httpMethod == 'GET' && $query) {
            $url.= '?' . trim($query);
        } else if($httpMethod == 'POST') {
            $context_options['http']['content'] = trim($query);
            $headers[] = 'Content-Length: ' . strlen($query);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        $context_options['http']['header'] = implode("\r\n", $headers);

        if ($this->serverProtocol === 'https') {
            $context_options['ssl'] = array(
                'verify_peer' => false,
                'allow_self_signed' => true
            );
        }
        $context = stream_context_create($context_options);
        
        $http_response_header = null;
        $response = file_get_contents($url, null, $context);

        $reqInfo = new stdClass;
        $reqInfo->url = $url;
        $reqInfo->query = $query;
        $reqInfo->requestHeader = $headers;
        $reqInfo->response = $response;
        $reqInfo->responseHeader = $http_response_header;
        $this->lastRequestInfo = $reqInfo;

        if ($response === false) {
            throw new \RuntimeException(
                "Failed issuing request:\n"
                . "REQUEST-INFO:\n" . print_r($this->lastRequestInfo, true)
            );
        }
        
        return $response;
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