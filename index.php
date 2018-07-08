<!DOCTYPE html>
<!--
To change this license header, choose License Headers in Project Properties.
To change this template file, choose Tools | Templates
and open the template in the editor.
-->

<html>
    <head>
        <meta charset="UTF-8">
        <title>ETSY API wrapper test</title>
    </head>
    <body>
        <?php
                          
            require_once(__DIR__ . "/etsy_app_V3.php");
            
            
            echo "<pre>"; // just for readability of output data

            $keystring = "insert here";  // set up the keystring and shared secret
            $shared_secret = "insert here";	

            $app = new etsy_app($keystring, $shared_secret); 
               
            $app_permissions = Array(
                "listings_w",
                "listings_r"
            );
                        
//            $app->request_access_token($app_permissions);  // we've already requested access token and recorded it, no need to request each time

            $app->set_access_token();
            print_r(json_decode($app->get_listing("insert listing number"), TRUE)); // just a test function
            
            echo "<br /><br />finished";
            echo "</pre>";
            
        ?>
    </body>
</html>
