<?php
    class CanvasHelper {
        protected $module;
        protected $username;
        protected $token;
        protected $refreshToken;
        protected $code;
        protected $db;
        protected $url;
        protected $clientId;
        protected $clientSecret;
        protected $redirectUri;
        
        /** 
         * Constructor
         * 
         * Initialize CanvasHelper Object
         * 
         * @param string $module The current module
         * @param string $username The current user
         * 
         * @return void
         */
        public function __construct($module, $username) {
            $this->url = "https://some.domain.com";
            $this->db = new mysqli('localhost', 'registrar_admin', 'XXXXXXXXXXXXXXXX', 'registrar');
            $this->username = $username;
            $this->module = $module;
            
            $sql = "SELECT * FROM canvas_modules WHERE  module = '".$this->module."'";
            
            $result = $this->db->query($sql) or die(mysqli_error($this->db));
            $resArr = $result->fetch_array(MYSQLI_ASSOC);
            
            $this->clientId = $resArr['id'];
            $this->clientSecret = $resArr['secret'];
            
            unset($resArr, $sql, $result);
            
            $sql2 = "SELECT * from canvas_auth WHERE user_name = '".$this->username."' AND module = '".$this->module."'";
            
            $result2 = $this->db->query($sql2) or die(mysqli_error($this->db));
            $resArr2 = $result2->fetch_array(MYSQLI_ASSOC);
            
            $this->token = $resArr2['token'];
            $this->refreshToken = $resArr2['refresh_token'];
            $this->code = $resArr2['code'];
            
            unset($sql2,$result2,$resArr2);
        }
        
        /** 
         * Getter
         * 
         * getter method
         * 
         * @param string $prop The property to retrieve
         * 
         * @return string The value of the requested property
         */
        public function get($prop){
            if (property_exists($this, $prop)){
                return $this->$prop;
            }
        }
        
        /**
         * Setter
         * 
         * setter method
         * 
         * @param string $prop The name of the property to set the value of
         * @param mixed $val The value to set the property to
         * 
         * @return void
         */
        public function set($prop, $val){
            if (property_exists($this, $prop)){
                $this->$prop = $val;
            }
        }
        
        /**
         * doTheCurl
         * 
         * Fundamental cURL method.  Does all the server-to-server interaction
         * 
         * @param $uri as string
         * @param $options as array, default = NULL
         * 
         * @return array array(str header, str <json string> body, str status)
         */
        protected function doTheCurl($uri, array $options = NULL) {
            $ch = curl_init();

            /** if no $options parameter exists, set curl options */
            if ($options == NULL) {
                $headers = array(
                    'Authorization: Bearer ' . $this->token,
                    'Accept: application/json'
                );
                
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_HEADER, 1);
            } 
            
            /** else use $options array items as parameters */
            else {
                foreach ($options as $key => $value) {
                    curl_setopt($ch, $key, $value);
                }
                
                /** if no header option is specified in $options */
                if(!isset($options['CURLOPT_HTTPHEADER'])) {
                    $headers = array(
                        'Authorization: Bearer ' . $this->token,
                        'Accept: application/json'
                    );
                    
                    /** header required for correct functioning */
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                }
                
                /** headers in response required for correct functioning */
                if (!isset($options['CURLOPT_HEADER'])) {
                    curl_setopt($ch, CURLOPT_HEADER, 1);
                }
            }

            curl_setopt($ch, CURLOPT_URL, $uri);

            $data = curl_exec($ch);

            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            /** separate header and body */
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header = substr($data, 0, $header_size);
            $body = substr($data, $header_size);

            curl_close($ch);

            return array($header, $body, $status);
        }

        /**
         * verifyNext
         * 
         * checks if there is more stuff  ready to be transferred from Canvas API
         * 
         * @param string $header
         * 
         * @return boolean
         */
        protected function verifyNext($header) {
            if (strlen($header) == 0) {
                throw new \Exception("input must not be of zero length");
            }
            else {
                return !!strpos($header, '; rel="next"') ? true : false;
            }
        }

        /**
         * curlRepeater
         * 
         * Repeats doTheCurl method until all data is received
         * 
         * @param $path as string
         * @param array $qOpts optional additional query parameters
         * 
         * @return array 2D array of your stuff or on fail, an associative array("status" => http status code)
         */
        protected function curlRepeater($path, $qOpts = array()) {
            $page = 1;
            $perPage = 100;
            $list = array();
            $qDef = array("page" => $page, "per_page" => $perPage);
            $qArr = array_merge($qDef, $qOpts);
            $q = http_build_query($qArr);
            
            $uri = $this->url.$path."?".$q;
               
            do {
                $data = $this->doTheCurl($uri);
                
                $next = $this->verifyNext($data[0]);

                $status = $data[2];

                if ($status == 200) {
                    $decoded = json_decode($data[1], TRUE);
                    $list[] = $decoded;
                }
                else {
                    $list["status"] = $status;
                    $list['uri'] = $uri;
                }

                $page++;
                
                $qArr['page'] = $page;
                $q = http_build_query($qArr);
            
                $uri = $this->url.$path."?".$q;

            } while ($next);
            
            return $list;
        }

        /**
         * extractHeaders
         * 
         * Sort of formats response headers in easier to read string. I think.
         * 
         * @param string $headerString
         * 
         * @return string formatted headers
         */
        protected function extractHeaders($headerString) {
            $headers = explode("\r\n", $headerString);
            $ret = "";
            foreach ($headers as $header) {
                $ret .= "$header<br/>";
            }
            return $ret;
        }
    
        /**
         * requestToken
         * 
         * Requests new Oauth2 token from Canvas authentication endpoint
         * 
         * @param string $code
         * 
         * @return array array("token" => $token, "refreshToken" => $refreshToken) or array("status" => $status) 
         */
        public function requestToken($code = NULL) {
            $this->code = $code;
            
            $headers = array(
                'Accept: application/json'
            );

            $params = array("grant_type" => "authorization_code",
                "client_id" => $this->clientId,
                "client_secret" => $this->clientSecret,
                "redirect_uri" => $this->redirectUri,
                "code" => $this->code
            );

            $curlOpts = array(
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => $params,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_SSL_VERIFYPEER => FALSE,
                CURLOPT_FOLLOWLOCATION => 1
            );

            $path = "/login/oauth2/token";
            $uri = $this->url.$path;
            
            $results = $this->doTheCurl($uri, $curlOpts);

            $status = $results[2];

            if ($status == 200) {
                $body = json_decode($results[1], true);

                $this->token = $body['access_token'];
                $this->refreshToken = $body['refresh_token'];

                return array(
                    "status" => $status,
                    "token" => $this->token,
                    "refreshToken" => $this->refreshToken
                );
            }
            else {
                return array("status" => $status);
            }
        }

        /**
         * refrshToken
         * 
         * Method to get new token from Canvas Oauth endpoint, if current token is expired
         * 
         * @return array array("token" => $token, "refreshToken" => $refreshToken)
         */
        public function refreshToken() {
            
            /** get auth creds from sql */
            $sql = "SELECT * FROM canvas_modules
                    WHERE module = '" . $this->module . "'";

            $result = $this->db->query($sql) or die(mysqli_error($this->db));
            $resArr = $result->fetch_array(MYSQLI_ASSOC);

            $clientId = $resArr['id'];
            $clientSecret = $resArr['secret'];
            $redirectUri = $resArr['redirect_uri'];
            
            $headers = array(
                'Accept: application/json'
            );

            $params = array("grant_type" => "refresh_token",
                "client_id" => $clientId,
                "client_secret" => $clientSecret,
                "redirect_uri" => $redirectUri,
                "refresh_token" => $this->refreshToken
            );

            $curlOpts = array(
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => $params,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_SSL_VERIFYPEER => FALSE,
                CURLOPT_FOLLOWLOCATION => 1
            );

            $path = "/login/oauth2/token";
            $uri = $this->url.$path;
            
			$results = $this->doTheCurl($uri, $curlOpts);

            $status = $results[2];

            if ($status == 200) {
                $body = json_decode($results[1], true);

                $this->token = $body['access_token'];
                
                $sql = "UPDATE canvas_auth
                            SET token ='" . $this->token . "'
                            WHERE user_name = '" . $this->username . "'
                            AND module = '" . $this->module . "'";

                $this->db->query($sql);

                return $this->token;
            }
            else {
                $this->token = "";
                return $this->token;
            }
        }

        /**
         * inAttendance
         * 
         * Method to check attendance for a stundent on a given date
         * 
         * @param string $date iso formatted date string YYYY-MM-DD
         * @param int $id student Canvas ID
         * 
         * @return mixed boolean on 200 or response status as string on any other response
         */
        public function inAttendance($date, $id) {
            $path = "/api/v1/audit/authentication/users/" . $id;

            $uri = $this->url . $path . "?start_time=" . $date . "T00:00:00&end_time=" . $date . "T23:59:59";

            $data = $this->doTheCurl($uri);

            $json2php = json_decode($data[1], true);

            if ($data[2] == 200) {
                return (count($json2php['events']) == 0) ? false : true;
            } else {
                $retArr = array("status" => $data[2], "uri" => $uri);
                //return $data[2];
                return $retArr;
            }
        }

        /**
         * getStuList
         * 
         * Method to get list of students from Canvas
         * 
         * @return array array('name' => name, 'id' => id, 'loginId' => login id)
         */
        public function getStuList() {
            $path = "/api/v1/accounts/1/users";

            $stuList = array();

            $raw = $this->curlRepeater($path);

            /** checked for returned bad status */
            if (isset($raw['status'])) {
                return array("status" => $raw['status'], "uri" => $raw['uri']);
            }
            else {
                $peeps = array();
                foreach ($raw as $arr) {
                    foreach ($arr as $peep) {
                        $peeps[] = $peep;
                    }
                }
                foreach ($peeps as $stu) {
                    if (isset($stu['sis_user_id'])) {
                        if (!preg_match('/staff/', $stu['sis_user_id'])) {
                            $stuList[] = array('name' => $stu['sortable_name'],
                                'id' => $stu['id'],
                                'loginId' => $stu['login_id']
                            );
                        }
                    }
                }
            }
            
            return $stuList;
        }

        /**
         * getFirstLogin
         * 
         * Method to find first time student logged into Canvas
         * 
         * @param string $id student Canvas ID
         * 
         * @return array
         */
        public function getFirstLogin($id) {
            $path = "/api/v1/audit/authentication/users/" . $id;

            $user = "";

            $eventList = array();

            $raw = $this->curlRepeater($path);
            
            echo "<br/><br/>";
            
            /** checked for returned bad status */
            if (isset($raw['status'])) {
                $ret = array(
                    'status' => $raw['status'],
                    'user' => $id
                        );
                return $ret;
            }
            else {
                $ref = $raw[0];

                if (empty($user)) {
                    if (isset($ref['linked']['users'][0])) {
                        $user = $ref['linked']['users'][0]['name'];
                    } else {
                        $userParts = explode(":", $id);
                        $user = end($userParts);
                    }
                }

                foreach ($raw as $arr) {
                    foreach ($arr['events'] as $event) {
                        $eventList[] = $event;
                    }
                }
            }

            /** if user has never logged in */
            if (empty($eventList)) {
                $ret = array(
                    'notice' => "User " . $user . " has never logged in.",
                    'user' => $user
                        );
                
            }
            else {
                $first = end($eventList);

                /** format that shit all pretty */
                $date = date('F d, Y', strtotime($first['created_at']));
                $time = date('g:ia', strtotime($first['created_at']));
                
                $ret = array(
                    "date" => $date,
                    "time" => $time,
                    "user" => $user
                );
            }
            return $ret;
        }
        
        /**
         * validateToken
         * 
         * checks if current token is valid
         * 
         * @return string http status code
         */
        public function validateToken() {
                $path = "/api/v1/search/all_courses?access_token=".$this->token;
                $theURL = $this->url.$path;
                
                $curlOpts = array(
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_SSL_VERIFYPEER => 0,
                    CURLOPT_FOLLOWLOCATION => 1
                    );
                
                $res = $this->doTheCurl($theURL, $curlOpts);
                
                return $res[2];
        }
        
        /**
         * isTokenExpired
         * 
         * Checks if current token is expired
         * 
         * @return boolean true if expired false otherwise
         */
        public function isTokenExpired() {
            $path = "/api/v1/search/all_courses?access_token=".$this->token;
            $theURL = $this->url.$path;
                
            $curlOpts = array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_FOLLOWLOCATION => 1
                );
                
            $res = $this->doTheCurl($theURL, $curlOpts);

            return $res[2] == 401 ? true : false;
        }
        
        /**
        * getPageViews
        * 
        * Method to get page views for a student on a given date
        * 
        * @param string $date iso formatted date string YYYY-MM-DD
        * @param int $id student Canvas ID
        * 
        * @return mixed boolean on 200 or response status as string on any other response
        */
        public function getPageViews($date, $id) {
            $uid = "sis_login_id:".$id;
            $path = "/api/v1/users/".$uid."/page_views?start_time=" . $date . "T00:00:00&end_time=" . $date . "T23:59:59";

            $data = $this->curlRepeater($path);

            if (!isset($data['status'])) {
                return $data;
            } else {
                return $data['status'];
            }
        }
    }