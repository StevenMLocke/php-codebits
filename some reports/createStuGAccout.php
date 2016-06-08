<?PHP
    include '/../includes/reportTop.php';
    
    //add gapi lib to path
    $path = 'C:\\Program Files (x86)\\PHP\\v5.3\\extras\\gapilib\\google-api-php-client\\src';
    set_include_path(get_include_path() . PATH_SEPARATOR . $path);

    //declare functions
    //stuId validator function
    function iDidator($userName) {
        $valid = true;
        $firstBit = substr($userName, 0, 2);
        $lastBit = substr($userName, 2);
        if (!preg_match('/^[a-zA-Z]+$/', $firstBit) || !preg_match('/^[0-9]+$/', $lastBit)) {
            $valid = !$valid;
        }
        return $valid;
    }

    //all fields validation
    function StuIdator($stu) {
        $fields = array('last', 'first', 'stuId', 'pass');

        foreach ($fields as $value) {
            if (empty($stu[$value])) {
               return false;
            }
        }

        if (!iDidator($stu['stuId'])) {
            return false;
        }

        return true;
    }

    //declare arrays to be used
    $regStuArr = array();        //list of stus in reg6 - array('last'=>last, 'first'=>first, 'stuId'=>stuid, 'pass'=>pass)
    $gapiStuArr = array();       //list of stus in gafe - array('last'=>last, 'first'=>first, 'stuId'=>stuid, 'email'=>email)
    $diffArr = array();          //2d array to hold reg6 stus not found in gafe - array(array('last'=>last, 'first'=>first, 'stuId'=>stuid, 'pass'=>pass))
    $validStus = array();        //after diff'ing, stus that are ready to have accts created - array(array('last'=>last, 'first'=>first, 'stuId'=>stuid, 'pass'=>pass))
    $invalidStus = array();      //after diff'ing stus that need to have data fixed - array(array('last'=>last, 'first'=>first, 'stuId'=>stuid, 'pass'=>pass))
    $gooStusToRemove = array();  //list of stus that need removed from gafe - array(array('last'=>last, 'first'=>first, 'stuId'=>stuid, 'email'=>email))
    $removed = array();          //list of stus successfully removed


    //create gafe creds and service and other stuff that i don't understand yet. This section per google docs

    $path = 'C:\Program Files (x86)\PHP\v5.3\extras\gapilib\google-api-php-client\src';
    require_once $path.'\Google\autoload.php';

    $client_email = 'gufgafe2@get-users-from-gafe.iam.gserviceaccount.com';
    $private_key = file_get_contents('c:\creds\Get Users From GAFE-10b023ccbfad.p12');
    $scopes = array('https://www.googleapis.com/auth/admin.directory.user');
    $user_to_impersonate = 'aUser@gaDomain.com';
    $credentials = new Google_Auth_AssertionCredentials(
        $client_email,
        $scopes,
        $private_key,
        'notasecret',                                 // Default P12 password_get_info
        'http://oauth.net/grant_type/jwt/1.0/bearer', // Default grant type
        $user_to_impersonate
    );

    $client = new Google_Client();
    $client->setAssertionCredentials($credentials);

    if ($client->getAuth()->isAccessTokenExpired()) {
        $client->getAuth()->refreshTokenWithAssertion();
    }

    $dirQuery = new Google_Service_Directory($client);

    //get list of students in gafe
    //set params for query
    $optParams = array('domain' => 'go2boss.com',
                    'maxResults' => 500,
                    'query' => 'orgUnitPath=/students',
                    'viewType' => 'admin_view',
                    'fields' => 'nextPageToken,users(id,name,primaryEmail,suspended)'
                    );

    //loop through until you have all results (max 500 per query)
    do {
        //This is the main asker of things from the gapi
        $result = $dirQuery->users->listUsers($optParams);

        //set next page token if more results need to be grabbed
        $optParams['pageToken'] = $result['nextPageToken'];

        //pushes looped result object to an storage array
        foreach ($result['users'] as $user) {
            if (!$user['suspended']) {
                $last = $user['name']['familyName'];
                $first = $user['name']['givenName'];
                $email = $user['primaryEmail'];
                $subLength = strpos($email, '@');
                $stuId = substr($email, 0, $subLength);
                $gapiStuArr[] = array('last' => $last,
                                      'first' => $first,
                                      'stuId' => $stuId,
                                      'email' => $email);
            }
        }                            
    //keep going as long as next page token is set
    } while (!empty($optParams['pageToken']));

    //get list of students in registrar6
    $sql = "SELECT last_name,
                   first_name,
                   boss_id,
                   password
            FROM students
            WHERE enrollment like 'Active'
            ORDER BY last_name ASC";

    $result = $db->query($sql);

    while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
        $regStuArr[] = array('last' => $row['last_name'],
                             'first' => $row['first_name'],
                             'stuId' => $row['boss_id'],
                             'pass' => $row['password']
                             );
    }

    //check which stus in registar6 aren't in gafe
    foreach ($regStuArr as $regStu) {
        $found = false;
        foreach ($gapiStuArr as $gapiStu) {
            if (strtolower($gapiStu['stuId']) == strtolower($regStu['stuId'])) {
                $found = true;
            }
        }
        //if stu not found in BOTH arrays push to difference array
        if (!$found) {
            $diffArr[] = $regStu;
        }
    }

    //check which stus in gafe aren't in registar6
    foreach ($gapiStuArr as $gapiStu) {
        $found = false;
        foreach ($regStuArr as $regStu) {
            if (strtolower($gapiStu['stuId']) == strtolower($regStu['stuId'])) {
                $found = true;
            }
        }
        //if stu not found in BOTH arrays push to difference array
        if (!$found) {
            $gooStusToRemove[] = $gapiStu;
        }
    }

    //validate student data for students that need gafe accounts created
    //i see you doingk loopss!!
    foreach ($diffArr as $stu) {
        //if valid push to valid stu array
        if (stuIdator($stu)) {
            $validStus[] = $stu;
        //if not valid push to invalid stu array
        }else{
           $invalidStus[] = $stu; 
        }
    }

    //create gafe accounts for valid student entries
    //first, check if there are any accounts that need to be created
    if (count($validStus) > 0) {
        //new thingy
        $service = new Google_Service_Directory($client);

        //loop over array of stus to have accounts created
        foreach ($validStus as $stu) {
            $user = new Google_Service_Directory_User();
            $name = new Google_Service_Directory_UserName();

            $name->setGivenName($stu['first']);
            $name->setFamilyName($stu['last']);

            $user->setName($name);
            $user->setPrimaryEmail($stu['stuId']."@go2boss.com");
            $user->setPassword("boss".$stu['pass']);
            $user->setOrgUnitPath('/Students');
            $user->setSuspended(FALSE);

            try { 
                    $result = $service->users->insert($user);
            } 
            catch (Google_IO_Exception $gioe) { 
                    echo "Error in connection: ".$gioe->getMessage(); 
            } 
            catch (Google_Service_Exception $gse) { 
                echo "User already exists: ".$gse->getMessage(); 
            } 
        }

        //create and display table of validated students in reg6 with no gafe account
        echo "  <h3>Google Accounts Created for the Following Students</h3>
                <table class='table table-bordered table-striped'>
                    <thead>
                        <tr>
                            <th>Num</th>
                            <th>Last</th>
                            <th>first</th>
                            <th>username</th>
                            <th>pass</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th>Num</th>
                            <th>Last</th>
                            <th>first</th>
                            <th>username</th>
                            <th>pass</th>
                        </tr>
                    </tfoot>
                    <tbody>";

        $iter = 1;
        foreach ($validStus as $arr) {
            echo "      <tr>
                            <td>".$iter."</td>
                            <td>".$arr['last']."</td>
                            <td>".$arr['first']."</td>
                            <td>".$arr['stuId']."</td>
                            <td>boss".$arr['pass']."</td>
                        </tr>";
            $iter++;
        } 

        echo '          </tbody>
                    </table>';
    }else{
        echo "<h3>Student Google Accounts are Up to Date.</h3><br/>";
    }

    //create and display table of students in reg6 with invalid data
    $count = count($invalidStus);
    if ($count) {
        echo "  <h3>Students in Registrar6 with invalid data</h3>
                    <table class='table table-bordered table-striped'>
                        <thead>
                            <tr>
                                <th>Num</th>
                                <th>Last</th>
                                <th>first</th>
                                <th>username</th>
                                <th>pass</th>
                            </tr>
                        </thead>
                        <tfoot>
                            <tr>
                                <th>Num</th>
                                <th>Last</th>
                                <th>first</th>
                                <th>username</th>
                                <th>pass</th>
                            </tr>
                        </tfoot>
                        <tbody>";

        $iter = 1;
        foreach ($invalidStus as $arr) {
            echo "          <tr>
                                <td>".$iter."</td>
                                <td>".$arr['last']."</td>
                                <td>".$arr['first']."</td>
                                <td>".$arr['stuId']."</td>
                                <td>".$arr['pass']."</td>
                            </tr>";
            $iter++;
        } 

        echo '          </tbody>
                </table>';
    }

    //suspend gafe accounts for inactive students
    //first, check if there are any accounts that need to be removed
    if (count($gooStusToRemove) > 0 && count($invalidStus) == 0) {
        $suspServ = new Google_Service_Directory($client);

        //loop over array of stus to have accounts suspended
        
        foreach ($gooStusToRemove as $stu) {
            $uEmail = $stu['email'];
            $Google_user = $suspServ->users->get($uEmail);
            $Google_user->suspended = TRUE;

            try { 
                    $result = $suspServ->users->update($uEmail, $Google_user);
                    $removed[] = $stu;
            } 
            catch (Google_IO_Exception $gioe) { 
                    echo "Error in connection: ".$gioe->getMessage(); 
            } 
            catch (Google_Service_Exception $gse) { 
                echo "User already exists: ".$gse->getMessage(); 
            } 
        }
        
        //create and display table of inactive reg6 students whose gafe accounts were removed
        echo "  <h3>Google Accounts Removed for the Following Inactive Students</h3>
                <table class='table table-bordered table-striped'>
                    <thead>
                        <tr>
                            <th>Num</th>
                            <th>Last</th>
                            <th>first</th>
                            <th>stu Id</th>
                            <th>email</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th>Num</th>
                            <th>Last</th>
                            <th>first</th>
                            <th>stu Id</th>
                            <th>email</th>
                        </tr>
                    </tfoot>
                    <tbody>";

        $iter = 1;
        foreach ($removed as $arr) {
            echo "      <tr>
                            <td>".$iter."</td>
                            <td>".$arr['last']."</td>
                            <td>".$arr['first']."</td>
                            <td>".$arr['stuId']."</td>
                            <td>".$arr['email']."</td>
                        </tr>";
            $iter++;
        } 

        echo '          </tbody>
                    </table>';

    }else{
        if (count($invalidStus) > 0) {
            echo "<h3>Google Accounts Can Not Be Removed Until All Student Data Errors Are Fixed</h3>";
        }
    }
    
    include '/../includes/reportBottom.php';