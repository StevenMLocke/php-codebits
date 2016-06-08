<?PHP

    //Display all errors
    #ini_set('display_errors',1);
    #ini_set('display_startup_errors',1);
    #error_reporting(-1);

    function getRandomBytes($nbBytes = 32) {
        $bytes = openssl_random_pseudo_bytes($nbBytes, $strong);
        if (false !== $bytes && true === $strong) {
            return $bytes;
        }
        else {
            throw new \Exception("Unable to generate secure token from OpenSSL.");
        }
    }
    function generatePassword($length){
        return substr(preg_replace("/[^a-zA-Z0-9]/", "p", base64_encode(getRandomBytes($length+1))),0,$length);
    }

    session_start();
    if(!isset($_SESSION['regemail'])){
        header("location:http://");
        exit();
    }

    $username = current(explode("@", $_SESSION['regemail']));

    include "connect.php";

    $sql = "SELECT * FROM users_profile WHERE username LIKE '$username'";
    $result = $db->query($sql);
    $user = $result->fetch_array(MYSQLI_ASSOC);

    if ($user['role_admin'] != "Yes"){
        header("location:http://");
        exit();
    }

    include "accessToken.php";
    
    echo '<script type="text/javascript" src="https://code.jquery.com/jquery-2.2.0.min.js"></script>';

    //Powerschool endpoint
    $url = 'http://rest.api.com/student?expansions=addresses,phones,school_enrollment&pagesize=1000&q=school_enrollment.enroll_status_code==(0,-1)';
    //headers for curl options
    $headers = array(
            'Authorization: Bearer '.$token,
            'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
            'Accept: application/json'
        );

    //include connect.php
    include "../includes/connect.php";
    
    //initiate curl
    $ch = curl_init();

    //set curl options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

    //do the curl
    $data = curl_exec($ch);

    //close the curl_close
    curl_close($ch);

    //convert curl result from json to php array
    $json2php = json_decode($data, true);

    //Declare some default variables
    $stuadded = 0;
    $stualready = 0;
    $totalremoved = 0;
    $counter = 0;
    $powerstu = array();

    $studentsremoved = "<B>Students Made Inactive:</B><br/>";
    $studentsadded = "<B>Students Added:</B><br/>";

    //loop through student info and build table
    foreach ($json2php['students']['student'] as $val) {
        
        $powerid = $val['id'];
        $first = addslashes($val['name']['first_name']);
        $middle = addslashes($val['name']['middle_name']);
        $last = addslashes($val['name']['last_name']);
        
        $powerstu[] = $powerid;
        
        $counter++;
        
        $SQL = "SELECT * FROM students WHERE username LIKE '$powerid'";
        $result = $db->query($SQL) or die (mysqli_error($db));
        $rows = mysqli_num_rows($result);
        
        if ($rows == 1) {
            //Count++ for students already entered.
            $stualready++;
            
            $grade = $val["school_enrollment"]["grade_level"];
            $street = addslashes($val["addresses"]["mailing"]["street"]);
            $city = addslashes($val["addresses"]["mailing"]["city"]);
            $state = $val["addresses"]["mailing"]["state_province"];
            $zipcode = $val["addresses"]["mailing"]["postal_code"];
            $username = $val['student_username'];
            
            $SQL = "UPDATE students SET 
            first_name = '$first', 
            middle_name = '$middle', 
            enrollment = 'Active',  
            boss_id = '$username', 
            last_name = '$last', 
            grade_level = '$grade' 
            WHERE username LIKE '$powerid'";
            
            $db->query($SQL) or die (mysqli_error($db));
            
            $SQL = "UPDATE address SET 
            address1 = '$street', 
            city = '$city', 
            state = '$state', 
            zipcode = '$zipcode' 
            WHERE student_id LIKE '$powerid' AND address_type LIKE 'Mailing'";
            
            $db->query($SQL) or die (mysqli_error($db));
            
            $street = addslashes($val["addresses"]["physical"]["street"]);
            $city = addslashes($val["addresses"]["physical"]["city"]);
            $state = $val["addresses"]["physical"]["state_province"];
            $zipcode = $val["addresses"]["physical"]["postal_code"];
            
            $SQL = "UPDATE address SET 
            address1 = '$street', 
            city = '$city', 
            state = '$state', 
            zipcode = '$zipcode' 
            WHERE student_id LIKE '$powerid' AND address_type LIKE 'Physical'";
            
            $db->query($SQL) or die (mysqli_error($db));
            

            $arr = $val["phones"];

            if (is_array($arr))
                $info = $val["phones"]["main"]["number"];
            else
                $info = 'No phone listed';

            $SQL = "UPDATE contact_info SET info = '$info' WHERE student_id LIKE '$powerid' AND contact_name LIKE 'PowerSchool'";
            
            $db->query($SQL) or die (mysqli_error($db));
            
        }
        else {
            
            $powerid = $val['id'];
            $first = addslashes($val['name']['first_name']);
            $middle = addslashes($val['name']['middle_name']);
            $last = addslashes($val['name']['last_name']);
            
            //$username = strtolower($first[0].$last[0].$powerid);
        
            //Add to the student added variable
            $stuadded++;          

            ###########TODO
            /* put password generator right here  $pw = generatepassword() must be added in includes     */  
            $password = generatePassword(6);
            
            //Build the student added string of all students:
            $studentsadded .= "$powerid: $last, $first - $password<br/>";

            $firstint = strtolower($first[0]);
            $lastint = strtolower($last[0]);
            $username = $firstint.$lastint.$powerid;
            
            $enrollment = "Active";
            $grade = $val["school_enrollment"]["grade_level"];
            $street = addslashes($val["addresses"]["mailing"]["street"]);
            $city = addslashes($val["addresses"]["mailing"]["city"]);
            $state = $val["addresses"]["mailing"]["state_province"];
            $zipcode = $val["addresses"]["mailing"]["postal_code"];

            //Insert Student Data
            $SQL = "INSERT INTO students (boss_id, enrollment, username, password, first_name, middle_name, last_name, grade_level) VALUES 
            ('$username', '$enrollment', '$powerid', '$password', '$first', '$middle', '$last', '$grade')";

            $db->query($SQL) or die (mysqli_error($db));
            // END STUDENT DATA

            //Insert Mailing Address -------------------------------------------------------------------------------
            $street = addslashes($val["addresses"]["mailing"]["street"]);
            $city = addslashes($val["addresses"]["mailing"]["city"]);
            $state = $val["addresses"]["mailing"]["state_province"];
            $zipcode = $val["addresses"]["mailing"]["postal_code"];

            $SQL = "INSERT INTO address (student_id, address1, city, state, zipcode, address_type) VALUES 
            ('$powerid', '$street', '$city', '$state', '$zipcode', 'Mailing')";

            $db->query($SQL) or die (mysqli_error($db));
            //END MAILING ADDRESS -----------------------------------------------------------------------------------

            //INSERT PHYSICAL ADDRESS -------------------------------------------------------------------------------
            $street = addslashes($val["addresses"]["physical"]["street"]);
            $city = addslashes($val["addresses"]["physical"]["city"]);
            $state = $val["addresses"]["physical"]["state_province"];
            $zipcode = $val["addresses"]["physical"]["postal_code"];

            $SQL = "INSERT INTO address (student_id, address1, city, state, zipcode, address_type) VALUES 
            ('$powerid', '$street', '$city', '$state', '$zipcode', 'Physical')";

            $db->query($SQL) or die (mysqli_error($db));
            //END PHYSIVCAL -----------------------------------------------------------------------------------------

            //INSERT PHONE NUMBER -----------------------------------------------------------------------------------

            $arr = $val["phones"];

            if (is_array($arr))
                $info = $val["phones"]["main"]["number"];
            else
                $info = 'No phone listed';

            $SQL = "INSERT INTO contact_info (student_id, contact_type, contact_name, info, isdefault) VALUES 
            ('$powerid', 'Phone', 'PowerSchool', '$info', '1')";

            $db->query($SQL) or die (mysqli_error($db));
            //END ADDING PHONE -------------------------------------------------------------------------------------- */
        }
    }

    $SQL = "SELECT * FROM students WHERE enrollment LIKE 'Active'";
    $result = $db->query($SQL) or die (mysqli_error($db));

    while ($stuinfo = mysqli_fetch_array($result)){
        $powerschoolID = $stuinfo['username'];
            
        if (array_search($powerschoolID, $powerstu) > -1)
            echo "";
        else {
            $first = $stuinfo['first_name'];
            $last = $stuinfo['last_name'];
            $totalremoved++;
            $SQL = "UPDATE students SET enrollment = 'Inactive' WHERE username LIKE '$powerschoolID'";
            $db->query($SQL) or die (mysqli_error($db));
            $studentsremoved .= "$powerschoolID: $last, $first<br/>";
        }
    }
    
    include "../../header.php";
    include "../../sidebar.php";
?>


      <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <section class="content-header">
          <h1>
            Administrator Reports Page
          </h1>
          <ol class="breadcrumb">
            <li><a href="http://.com"><i class="fa fa-dashboard"></i> Home</a></li>
            <li class="active"><a href="#"> Admin Reports</a></li>
          </ol>
        </section>

        <!-- Main content -->
        <section class="content">
          <div class="row">
            <div class="col-xs-12">
                <?PHP
    echo "<B>The import/export ran successfully</B>:<br/>Students Added: $stuadded<br/>Students Made Inactive: $totalremoved<br/>Students Already Entered: $stualready<p/>";

    if ($stuadded > 0) {
        echo $studentsadded."<p/>";
        echo '  <br/><p><B>Create Google Accounts?</B></p>
                <a href="http://.com/pages/admin/createStuGAccout.php" class="btn btn-primary ladda-button" data-style="zoom-in">
                    <span class="ladda-label">
                        <i class="fa fa-download"></i> Create
                    </span>
                </a><br/><br/><br/>';
    }
    if ($totalremoved > 0)
        echo $studentsremoved."<p/>";
        
   echo "<P/><B><a href='reports.php'>Click Here</a></B> to go back to the admin reports.";
        ?>
              </div><!-- /.box -->
          </div><!-- /.row -->
        </section><!-- /.content -->
      </div><!-- /.content-wrapper -->

      <?PHP

      include "../../footer.php";

      ?>