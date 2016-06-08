<?PHP 
    do {
        //include report top
        include '/../includes/reportTop.php';

        //require canvas helper class
        require '/../includes/CanvasHelper.php';

        //require Twig render engine class
        require_once '/../../plugins/Twig-1.24.0/lib/Twig/autoloader.php';

        //register twig
        Twig_Autoloader::register();

        //set foler for twig templates
        $loader = new Twig_Loader_Filesystem('C:\inetpub\LocalUser\registrar6\pages\includes\templates\twig');

        //set twig environment
        $twig = new Twig_Environment($loader);

        //set $module var
        $module;
        
        if (isset($_GET['module'])) {
            $module = $_GET['module'];
        } else {
            //otherwise module will come from canvas oauth with module as $GET_['state'] variable
            if (isset($_GET['state'])) {
                $module = $_GET['state'];
            }
        }

        //instantiate CanvasHelper
        $canF1 = new CanvasHelper($module, $username);

        //this page
        $redirectUri = "http://some.domain.com/pages/admin/cAuth.php";

        //final destination
        $workPageRedirect = "http://some.domain.com/pages/admin/canvasReporter.php?module=" . $canF1->get("module");
    } while (FALSE);
    
    #where it all happens
    
    // if redirected from canvas oauth check if error is returned in query string
    if (isset($_GET['error'])) {
        echo $twig->render('cAuthErr.twig', array('error' => $_GET['error']));
        include "/../includes/reportBottom.php";
        exit;
    }
    
    //if there is no error check for 'code' in query string...
    else{
        
        if (isset($_GET['code'])) {
            $code = $_GET['code'];
        }
        
        //...try to grab token from canvasHelper obj
        $token = $canF1->get("token");
        
        //...if it returns empty...
        if (empty($token)) {
            
            //...and code exists set code to exchange for token
            if (isset($code)) {                
                
                //requestToken returns array array("token" => $token, "refreshToken" => $refreshToken)
                $canF1->requestToken($code);
                $token = $canF1->get("token");
                
                if (!empty($token)) {
                    $sql2 = "INSERT INTO canvas_auth (user_name, module, code, token, refresh_token)
                            VALUES ('".$canF1->get("username")."',
                                    '".$canF1->get("module")."',
                                    '".$canF1->get("code")."',
                                    '".$canF1->get("token")."',
                                    '".$canF1->get("refreshToken")."'
                                    );";

                    $getsome = $db->query($sql2);
                }
            }
            
            //...else go through full oauth
            else{
                $url = $canF1->get('url');
                $path = "/login/oauth2/auth";
                $query = "?client_id=".$canF1->get('clientId')
                        ."&response_type=code&redirect_uri=".$redirectUri
                        ."&state=".$canF1->get('module');
                
                $authUri = $url.$path.$query;
                
                //redirect to canvas oauth which will in turn redirect back here
                echo $twig->render('cAuthRedirect.twig', array("uri" => $authUri));
                
                //for debugging should never display
                echo $canF1->get('username')."<br/>";
                echo $canF1->get('module')."<br/>";
                echo "Client Id: ".$canF1->get('clientId')."<br/>";
                echo "Client Secret: ".$canF1->get('clientSecret')."<br/>";
                echo "Redirect:".$canF1->get('redirectUri')."<br/>";
                echo "query: ".$query."<br/>";
                
                include "/../includes/reportBottom.php";
                exit;
            }
        }
        
        //redirect to final destination
        echo $twig->render('cAuthRedirect.twig', array('uri' => $workPageRedirect));
        include "/../includes/reportBottom.php";
    }