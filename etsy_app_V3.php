<?PHP
/*
 *      Changes between V1 and V2:
 *          1. Replaced the store oauth_token functions with ones that use the credentials database
 *          2. stored the access tokens in the credentials database, app no longer echos them out
 *          3. created new set_access_token function to take credentials from database instead of from argument
 *          4. changes to credentails database code, it now drops the table each time new creds are stored   
 * 
 *      Changes between V2 and V4:
 *          1. A tonne of useful new features, for example generic "get all data" type functions with automatic pagination, a useful export data to CSV function
 * 
 * 
 *      To do:
 *          -put all errors in error log. All fatal ones can still output to user
 * 
 */

require_once(__DIR__ . "/credentials_database.php"); 

class etsy_app  // contains all the basic functionality of accessing the ETSY api via oAuth. This class requires OAuth to be installed
{
const OUTPUT_LIMIT = 100;  // output limit from API call return data

///NOTE: ETSY API note: we use your api key as the OAuth consumer key, and your api key's shared secret as the consumer shared secret.
protected $keystring;  // also known as consumer key
protected $shared_secret;

protected $request_url;   // Please note that all request MUST use HTTPS to succeed with ETSY
protected $base_url;
protected $app_url; // only needs to be private, but set as protected because I can't see this being a security issue

protected $oauth; // oauth handler
protected $call_counter; // simple int to count the calls made in your script. 

private $credentials_database; // a database using the class "credentials database"

        public function __construct($keystring, $shared_secret)
        {
                // initiate some base variables
                $this->keystring = $keystring;  // set up the keystring and shared secret
                $this->shared_secret = $shared_secret;
                //echo "keystring: " . $keystring . "<br />";
                //echo "shared_secret: " . $shared_secret . "<br /><br />";  // for some reason, it just WONt take the argument from the construct...  
                $this->call_counter = 0;

                $this->base_url = "https://openapi.etsy.com/v2/";

                                // set up a database for our api credentials
                                $database_details = Array( // to do, move this to an include file outside of www/
                                        "host" => "host",       // please insert your own details into here
                                        "username" => "user",
                                        "password" => "password",
                                        "name" => "app_name"
                                );

                                // set up the credentials database class
                                $this->credentials_database = new credentials_database($database_details);   
				
                //set up oAuth for write access to the API
                        try 
                        {
                                $this->oauth = new OAuth($this->keystring, $this->shared_secret);	
                        }
                        catch(OAuthException2 $e)
                        {
                                echo "OAuth: Failed to instantiate oauth: " . $e->getMessage();
                                exit;				
                        }


                // define our app url for callback.                        
                $this->app_url = "http://" . filter_input(INPUT_SERVER, 'HTTP_HOST', FILTER_SANITIZE_STRING) . filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_STRING); // for callback url. NOTE: this might behave differently on other servers.			
        }

        public function request_access_token($permission_scope)
        {
                // if the oauth verifier is returned successfully via GET, then 
                if (is_string($_GET['oauth_verifier']))	
                {
                                        // Reach this part of the code if oauth_verified and oauth_token has been retrieved from GET	
                                        $this->retrieve_access_tokens();
                }	
                else
                {
                                        //if running for the first time, 
                                        $this->display_authorization_url($this->permission_array_to_string($permission_scope));
                }	
        }

        private function permission_array_to_string($permission_array)
        {   // converts an array of permission scope data into the string that request_acccess_token requires
            return implode("%20", $permission_array);
        }
        
        private function retrieve_access_tokens()
        {
        // record request tokens, request verifier and retrieve the request secret. 
        $request_oauth_token = filter_input(INPUT_GET, 'oauth_token', FILTER_SANITIZE_STRING);
        $request_oauth_verifier = filter_input(INPUT_GET, 'oauth_verifier', FILTER_SANITIZE_STRING);
        $request_oauth_token_secret = $this->credentials_database->get_oauth_token_secret(); // using the credentials database for temporary storage
        
        // for debugging:
        //echo "oauth token = " . $request_oauth_token . "<br />";
        //echo "oauth verifier = " . $request_oauth_verifier . "<br />";
        //echo "oauth token secret = " . $request_oauth_token_secret . "<br />";  // THIS IS NOT STORED!!

        // set the new token with request token credentials
        $this->oauth->setToken($request_oauth_token, $request_oauth_token_secret);

                try 
                {
                        // set the verifier and request Etsy's token credentials url
                        $acc_token = $this->oauth->getAccessToken("https://openapi.etsy.com/v2/oauth/access_token", null, $request_oauth_verifier);

                        //outputs the access token details in plain text - DEBUG ONLY
                        //echo "access token: " . $acc_token["oauth_token"] . "<br/>";
                        //echo "access secret: " . $acc_token["oauth_token_secret"];
                        
                        //record these token details in our credentials database
                        $this->credentials_database->store_access_creds($acc_token["oauth_token"], $acc_token["oauth_token_secret"]);
                        echo ("Access credentials successfully stored in database");
                } 
                catch (OAuthException $e) 
                {
                        echo "oAuth Token: Could not get access token: " . $e->getMessage() . "<br />";
                        exit;					
                }	            
            
        }
        
        private function display_authorization_url($permission_scope)
        {
            // set up oAuth request token with permission scope
            try 
            {
                    $request_token = $this->oauth->getRequestToken("https://openapi.etsy.com/v2/oauth/request_token?scope=" . $permission_scope, $this->app_url); 
                    echo "Click to authorize app: <a href='" . $request_token['login_url'] . "'>" . $request_token['login_url'] . "</a>";				

                    // took me a while to figure this out. When the callback url is called, we lose the oauth token secret. 		
                    $this->credentials_database->store_oauth_token_secret($request_token['oauth_token_secret']);

            }
            catch(OAuthException2 $e)
            {
                    echo "OAuth: Failed to get request token: " . $e->getMessage() . "<br />";
                    exit;				
            }            
       
        }
        
	public function set_access_token()
	{
		$this->oauth = new OAuth($this->keystring, $this->shared_secret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
        $access_credentials = $this->credentials_database->get_access_creds();
		return $this->oauth->setToken($access_credentials["access_token"], $access_credentials["access_secret"]);
	}        
		
	public function output_permission_scopes()
	{
		// test that your permission scopes have been set correctly
		$request_url = $this->base_url . "oauth/scopes";
		return $this->make_request($request_url, null, OAUTH_HTTP_METHOD_GET);	
	}
	
	public function get_shipping_template_ids()
	{
		// Get the available shipping ID's associated with the username
		$request_url = $this->base_url . "users/__SELF__/shipping/templates";
		return $this->make_request($request_url, null, OAUTH_HTTP_METHOD_GET);
	}
	
	public function get_shop_id()
	{
		// Get the ID's associated with the username
		$request_url = $this->base_url . "users/__SELF__/shops";
		return $this->make_request($request_url, null, OAUTH_HTTP_METHOD_GET);
	}	

	public function find_all_shop_section_ids($shop_id)
	{
		// Get the ID's associated with the username
		$request_url = $this->base_url . "shops/" . $shop_id . "/sections";
		return $this->make_request($request_url, null, OAUTH_HTTP_METHOD_GET);
	}
			
	public function get_listing($id) 
	{	
		$request_url = $this->base_url . "listings/" . $id;
		return $this->make_request($request_url, null, OAUTH_HTTP_METHOD_GET);		
	}	
	
	public function get_listing_images($id) 
	{	
		$request_url = $this->base_url . "listings/" . $id . "/images";
		return $this->make_request($request_url, null, OAUTH_HTTP_METHOD_GET);		
	}		
	
	public function find_all_top_categories()
	{
		// Find all top level categories from etsy
		$request_url = $this->base_url . "taxonomy/categories";
		return $this->make_request($request_url, null, OAUTH_HTTP_METHOD_GET);
	}		
	
	public function find_all_listing_images($id)
	{
		// Retrieves a set of ListingImage objects associated to a Listing.
		$request_url = $this->base_url . "/listings/" . $id . "/images";
		return $this->make_request($request_url, null, OAUTH_HTTP_METHOD_GET);	
	}
	
	protected function upload_images($listing_id, $filename, $rank = 1) // you do not need a rank, default is 1
	{ //uploads an image to ETSY
        $this->oauth->enableDebug();  // do we need this?
        $mimetype = "multipart/form-dataheader"; // This works.. for now.

                try 
                {
                        $source_file = dirname(realpath(__FILE__)) ."/images/" . $filename;
                        echo "Image source file: " . $source_file . "<br />";

                                if (!file_exists($source_file))
                                {
                                        return -1;
                                }

                        $url = "https://openapi.etsy.com/v2/listings/". $listing_id ."/images";
                        $params = array('@image' => '@' . $source_file . ';type=' . $mimetype, 
                                                        'rank' => $rank);

                        $this->oauth->fetch($url, $params, OAUTH_HTTP_METHOD_POST);

                        //for debugging
                        //$json = $this->oauth->getLastResponse();
                        //print_r(json_decode($json, true)); 

                } 
                catch (OAuthException $e) 
                {
                        //print $this->oauth->getLastResponse()."\n";
                        //print_r($this->oauth->debugInfo);
                        //die($e->getMessage());
                        echo "Problem uploading image: " . $e->getMessage();
                }	
	}
	
        protected function convert_epoch_date($epoch_seconds)
        { // converts epoch seconds to human readable dates
            $dt = new DateTime("@$epoch_seconds");  
            //return $dt->format('d-m-Y H:i:s'); // no real need for exact hours, minutes, seconds 
            return $dt->format('d-m-Y');     
        }	

        protected function make_request($request_url, $params, $method, $halt_on_oauth_exception = TRUE)
        {
            
            $this->call_counter++; // increment the call counter, important to know how many calls we are making. 
            
                try 
                {
                        //$query_string = "?" . http_build_query($listing_data, null, "&", PHP_QUERY_RFC3986); // a cool query builder which we don't need
                        //echo "running oauth fetch on URL: <br />" . $request_url . "<br /><br />";
                        $this->oauth->fetch($request_url, $params, $method);
                        $json = $this->oauth->getLastResponse();
                        //print_r(json_decode($json, true));
                        return $json; // return the json data for any decoding needs

                } 
                catch (OAuthException $e) 
                {
                    print_r($e);
                    error_log($e->getMessage());
                    error_log(print_r($this->oauth->getLastResponse(), true));
                    error_log(print_r($this->oauth->getLastResponseInfo(), true));

                        if ($halt_on_oauth_exception)
                        {
                            exit();
                        }
                }		
        }

        protected function generic_get_total(&$reference_to_total, $api_call)
        {
            /*This function runs an API call purely for the purpose of finding out the count*/                      
            //note this function only works with method GET

            if (empty($reference_to_total)) // only run this api call if we haven't already determined total already
            {   
                $request_url = $this->base_url . $api_call;
                //echo "request_url: " . $request_url . "<br />";                            
                $output = $this->make_request($request_url, null, OAUTH_HTTP_METHOD_GET);

                    if (empty($output))
                    {
                        error_log("error in generic_get_total, could not get " . $request_url);
                    }
                $output = json_decode($output, true);	// json decode it							
                $reference_to_total = $output["count"]; 

                    if (empty($reference_to_total))
                    {                                    
                        error_log("error in generic_get_total, could not get count of " . $request_url);
                    }
            }
            return $reference_to_total;
        }

        protected function generic_append_data(&$reference_to_storage, $data) 
        {            
                if (empty($reference_to_storage)) // we found that sometimes the data comes back empty. 
                {
                    $storage_length = 0;
                }
                else
                {
                    $storage_length = sizeof($reference_to_storage["results"]);
                }

                foreach($data["results"] as $key => $data_to_append)
                {
                    //new index = key + length
                    $reference_to_storage["results"][$storage_length + $key] = $data_to_append;
                } 
        }                    

        protected function generic_get_data(&$reference_to_storage, $api_call, $expected_total, $identifier, $id = NULL) 
        {
        $page = 0; 
        $limit = $this::OUTPUT_LIMIT; 
        $query_string_symbol = "?";  // if the api_call already contains a resource (association, field, scope), then the query string syntax becomes mangled
        
        
            if (strpos($api_call, "?") !== FALSE)  // there's a lot wrong with this language... Where is 'contains' or 'in' like Python? All I want to do is check if a character exists in a string
            {   // then it's not our first query
                $query_string_symbol = "&";
            }
            

        //echo "limit = " . $limit . "<br />";
        //echo "expected total = " . $expected_total . "<br / >";

            if (empty($reference_to_storage)) // we only run one api call, all consecutive queries do not require new api calls
            {
//                $reference_to_storage = [];  // instantiate the all_listings_data array for the first time
                $reference_to_storage = Array();  // instantiate the all_listings_data array for the first time


                    while (($page * $limit) < $expected_total)
                    {
                        $request_url = $this->base_url . $api_call . $query_string_symbol . "limit=" . $limit . "&offset=" . ($page * $limit);
                        //echo "request_url: " . $request_url . "<br />";
                        $data = $this->make_request($request_url, null, OAUTH_HTTP_METHOD_GET);

                            if (empty($data))
                            {
                                error_log("error in generic_get_data, could not get data from: " . $request_url);                                    
                            }                            

                        $decoded_data = json_decode($data, TRUE); // place it in an associative array

                        // append the listings_array
                        $this->generic_append_data($reference_to_storage, $decoded_data);                                   

                        $page++;
                    }
            }
            if ($id != NULL)
            {
                foreach ($reference_to_storage["results"] as $result) // search the multi for the country code                    
                {                          
                    if ($result[$identifier] == $id)
                    {
                        $output_data = $result;
                        break; // we break the loop because we've found what we're looking for
                    }
                }
                if (!empty($output_data))
                {
                    return $output_data;
                }
                else
                {
                    return -1; 
                }      
            }
            else
            {
                return 0; // all is okay                            
            }
        }

        protected function export_etsy_data_to_csv($filename, $data, $field_translations=null) // Promote to parent class
        {
            // this is a general purpose export to CSV function that fits exactly the json_decode'd version of the data 
            // that gets retuned from any etsy api call
            // The function shoul also work for any associative array that's structured similarily to it, 
            // all that is required is that the actual data is encapsulated in ["results"] and has aleast one key within it 

                if (sizeof($data) < 1)
                {
                    error_log("export_etsy_data_to_csv: no data input into function");
                    return -1;
                }

            $columns = array_keys($data["results"][0]); // use the first result to get the column names
            //to do, implement a "field translations" function, where the user can import their own names of fields. 

            // start building the array
            $output_data = array();
            $y = -1; // start at negative 1 so as to give the loop a chance to put the titles in. 

                while ($y < sizeof($data["results"]))
                {
                    $x = 0;
                    $output_data[$y] = array();

                        while ($x < sizeof($columns))
                        {                                            
                                if ($y >= 0) // we'll put the data in
                                {
                                    $data_type = gettype($data["results"][$y][$columns[$x]]);

                                        // in_array helps us make sure that the data type isn't one of the listed variables. The alternative was to 4 almost identical lines of code
                                        if (!in_array($data_type, array("array", "object", "resource", "null")))
                                        {
                                            $output_data[$y][$x] = $data["results"][$y][$columns[$x]];
                                        }
                                        else
                                        {
                                            $output_data[$y][$x] = "void";                                                        
                                        }
                                }
                                else // after the columns have been named
                                {
                                    $output_data[$y][$x] = $columns[$x];
                                } 
                            $x++;
                        }
                    $y++;
                }
            //  print_r ($output_data);
            echo "exporting data<br />";

            // once the array has been built, we'll export a CSV file
            ob_start(); // we'll use output buffering to stablize file creation

            $file_handle = fopen($filename, 'w'); // use write mode, this will overwrite existing files. 
            //fputcsv($file_handle, array_keys(reset($output_data))); // put in the column names first
            
                if (!$file_handle)
                {
                    echo "could not create file: " . $filename . "<br />";
                    error_log("could not create file: " . $filename . "<br />");
                    return -1;
                }

                foreach ($output_data as $row) // then export data row by row
                {
                    fputcsv($file_handle, $row);
                }
            fclose($file_handle); // close the file handle
            ob_clean(); // finish!                        
        } 
        
        public function count_calls() // promote to parent class
        {
            return $this->call_counter;                         
        }        
}
?>
