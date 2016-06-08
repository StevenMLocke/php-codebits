<?php //top reports page
    include "/../includes/reportTop.php";
    include "/../includes/CanvasHelper.php";
    
    //require Twig render engine class
    require_once '/../../plugins/Twig-1.24.0/lib/Twig/autoloader.php';

    //register twig
    Twig_Autoloader::register();

    //set foler for twig templates
    $loader = new Twig_Loader_Filesystem('C:\inetpub\LocalUser\registrar6\pages\includes\templates\twig');

    //set twig environment
    $twig = new Twig_Environment($loader);
    
    $cF1 = new CanvasHelper();
    
    $token = $_GET['token'];
    
    echo "token is: ".$token."<br/><br/>";
    
    $module = $_GET['module'];
    
    #token validation area----------------------------------------------------------
    //validate token (returns status) 401 is expired token
    $validated = $cF1->validateToken($token);

    //if not status 200 ok
    if ($validated != 200) {

        //if token is expired
        if ($validated == 401) {

            //get auth creds from sql
            $sql = "SELECT * FROM canvas_modules
                        WHERE module = '" . $module . "'";

            $result = $db->query($sql) or die(mysqli_error($db));
            $resArr = $result->fetch_array(MYSQLI_ASSOC);

            $clientId = $resArr['id'];
            $clientSecret = $resArr['secret'];
            $redirectUri = $resArr['redirect_uri'];

            //get new token with refresh token
            $ref = $cF1->refreshToken($clientId, $clientSecret, $redirectUri, $refreshToken);

            //if the refresh works
            if (isset($ref['token'])) {

                //set token
                $token = $ref['token'];

                //update sql with new token
                $sql = "UPDATE canvas_auth
                                SET token ='" . $token . "'
                                WHERE user_name = '" . $username . "'
                                AND module = '" . $module . "'";

                $db->query($sql);
            }
            //if the refresh does not work
            else {
                echo "<h3>Token Refresh Failed, Status: </h3><h1>" . $ref['status'] . "</h1>";

                //so it doesn't break the page on premature exit
                include "/../includes/reportBottom.php";
                exit;
            }
        }
    }
#-------------------------------------------------------------------------------
    
    $res = $cF1->inAttendance($_GET['date'], $token, $_GET['ID']);
    
    if (gettype($res) == 'boolean') {
        echo $twig->render('perStuSuccess.twig', array("ID" => $_POST['ID'] , "date" => $_POST['date'], "bool" => $res));
    }else{
        echo $twig->render('perStuFail.twig', array("message" => "There was an error", "status" => $res));
    }

    //bottom reports page
    include "/../includes/reportBottom.php";