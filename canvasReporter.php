<?php
/** includes and instantiation */
do {
    include '/../includes/reportTop.php';
    require '/../includes/CanvasHelper.php';
    require '/../includes/reportForms.php';
    require_once '/../../plugins/Twig-1.24.0/lib/Twig/autoloader.php';

    /** register twig */
    Twig_Autoloader::register();

    /** set foler for twig templates */
    $loader = new Twig_Loader_Filesystem('C:\inetpub\LocalUser\registrar6\pages\includes\templates\twig');

    /** set twig environment */
    $twig = new Twig_Environment($loader);
    
    $module;
    
    if($_POST) {
        $module = $_POST['module'];
    }else{
        $module = $_GET['module'];
    }
    
    $cF1 = new CanvasHelper($module, $username);
} while (FALSE);

/** GET */
if (!$_POST) {
    $forms = new reportFormGroups();

    /** render page */
    echo $twig->render(
            'reportGET.twig', array(
                "formGroups" => $forms->getFormGroups($cF1->get("module")),
                "myTitle" => $cF1->get("module"),
                "module" => $cF1->get("module")
            )
        );
}

/** POST */
else {
    /** token validation */
    if ($cF1->isTokenExpired()) {
        $cF1->refreshToken();
    }

    /** attendance */
    if ($cF1->get("module") === "attendance") {
        $students = $cF1->getStuList();

        $date = $_POST['date'];
        
        $attendArr = array();
        $errArray = array();

        foreach ($students as $student) {

            $res = $cF1->inAttendance($date, $student['id']);

            /** function inAttendance returns bool on success or str on error */
            if (gettype($res) == 'boolean') {
                $present = $res ? "Present" : "Absent";

                //put into an array for later.
                $attendArr[] = array('name' => $student['name'],
                    'Canvas ID' => $student['id'],
                    'Login ID' => $student['loginId'],
                    'Attendance' => $present
                );
            } 
            else {
                $errArray[] = array('name' => $student['name'],
                    'Canvas ID' => $student['id'],
                    'Login ID' => $student['loginId']
                );
            }
        }

        /** build table of successful results */
        echo $twig->render(
                'attendanceTableBuilder.twig',
                array(
                    "date" => $date,
                    "attendArr" => $attendArr
                )
            );

        /** if there were errors returned from inital attendance checks
         * build error table
         */
        if (count($errArray) > 0) {
            echo $twig->render(
                    'attendanceErrorTableBuilder.twig',
                    array(
                        "count" => count($errArray),
                        "errArr" => $errArray,
                        "token" => $cF1->get("token"),
                        "date" => $date,
                        "module" => "perStuAttendance"
                    )
                );
        }
    }

    /** get First Login */
    if ($cF1->get("module") === "getFirstLogin") {
        
        /** get user */
        $bossIdParts = explode(" ", $_POST['id']);
        $bossId = "sis_login_id:" . end($bossIdParts);

        $firstLog = $cF1->getFirstLogin($bossId);
        
        /** if user not found, first retry with <user>@go2boss.com */
        if (isset($firstLog['status'])) {
            if ($firstLog['status'] == 404) {
                $bossId .="@go2boss.com";
                $firstLog = $cF1->getFirstLogin($bossId);
            }
        }

        /** output results
         * on error
         */
        if (isset($firstLog['status'])) {
            
            /** will be status 404 if user is not found */
            if ($firstLog['status'] == 404) {
                $msg = "User " . $firstLog['user'] . " was not found.";
            }
            else {
                $msg = "There was an error";
            }
            
            /** Build out for all other errors */
            echo $twig->render(
                    'firstLogError.twig',
                    array(
                        "message" => $msg,
                        "status" => $firstLog['status']
                    )
                );
        }
        else {
            
            /** if user has never logged in */
            if (isset($firstLog['notice'])) {
                $msg = $firstLog['notice'];
            }
            
            /** if user has logged in */
            else {
                $msg = $firstLog['user'] . "'s first login was on " . $firstLog['date'] . " at " . $firstLog['time'];
            }
            
            echo $twig->render('firstLogSuccess.twig',array("message" => $msg));
        }

        echo $twig->render('getFirstLoginButtons.twig');
    }
    
    /** per student attendance */
    if ($cF1->get("module") === "perStuAttendance") {
        $date = $_SESSION['date'];
        $id = $_SESSION['id'];
        
        unset($_SESSION['date'], $_SESSION['id']);
        
        $res = $cF1->inAttendance($date, $id);
        
        var_dump($res);
        echo "<Br/><br/>";
        echo "Token: ".$cF1->get("token")."<br/>";
        echo "Code: ".$cF1->get("code")."<br/>";
        echo "Module: ".$cF1->get("module")."<br/>";
        echo "Client ID: ".$cF1->get("clientId")."<br/>";
        echo "User: ".$cF1->get("username")."<br/>";
        
        echo $cF1->isTokenExpired() ? "Token is expired<br/><br/>" : "Token is not expired<br?><br/>";
        
        /** if a the response is a boolean build success result */
        if (gettype($res) == 'boolean') {
            echo $twig->render(
                    'perStuSuccess.twig',
                    array(
                        "ID" => $id ,
                        "date" => $date,
                        "bool" => $res
                    )
                );
        }
        
        /** if failed build failed result */
        else{
            echo $twig->render(
                    'perStuFail.twig',
                    array(
                        "date" => $date,
                        "id" => $id,
                        "message" => "There was an error",
                        "status" => $res['status'],
                        "resUri" => $res['uri']
                    )
                );
        }
    }
    
    /** test check attendance times */
    if ($cF1->get("module")=== "getPageViews") {
        $date = $_POST['date'];
        
        $bossIdParts = explode(" ", $_POST['id']);
        $bossId = end($bossIdParts);
        
        $results = $cF1->getPageViews($date, $bossId);
        $arrayOfDates = array();
        
        foreach ($results as $resArr){
            foreach ($resArr as $arr) {
                //echo "URL:".$arr['url']."<br/>";
                //echo "Date:".$arr['created_at']."<br/>";
                $arrayOfDates[] = strtotime($arr['created_at']);
                //echo "Interaction Seconds:".$arr['interaction_seconds']."<br/>";
                //echo "User: ".$arr['links']['user']."<br/>";
                //echo "-----------------------------------------------<br/>";
            }
        }
        
        $newest = NULL;
        $oldest = NULL;
        foreach ($arrayOfDates as $date) {
            if ($newest === NULL) {
                $newest = $date;
            }
            if ($oldest === NULL) {
                $oldest = $date;
            }
            else {
                if ($date > $newest) {
                    $newest = $date;
                }
                else {
                    if ($date < $oldest) {
                        $oldest = $date;
                    }
                }
            }
        }
        
        $first = date('F j, Y - h:i:s', $oldest);
        $last  = date('F j, Y - h:i:s', $newest);
        echo "First page visit: ".$first."<br/>";
        echo "Last page visit: ".$last."<br><br/>";
        $span = $newest - $oldest;
        $seconds = $span % 60;
        $totalMinutes = $span / 60;
        $minutes = $totalMinutes % 60;
        $totalHours = $totalMinutes / 60;
        $hours = $totalHours % 24;
        echo "Hours: ".$hours."<br/>Minutes: ".$minutes."<br/>Seconds: ".$seconds."<br/>";
        
    }
}

include "/../includes/reportBottom.php";