<?php

/* Notes:
 * 
 * The class constructor uses the following array to take details for the database:
 * 
 *   $database_details = Array(
 *     "host" => "host",
 *     "username" => "database username",
 *     "password" => "password",
 *     "name" => "table name"
 *   );
 *   Create a table to store credentials before using the class
 *    
 */

class credentials_database
{
/* A class specifically designed to store api credentials, access tokens, etc in MySQL database
 * 
 * To do:
 *      - See how wordpress store their database details
 *      - Is there any way we can increase the security of this? 
 * 
 * Notes:
 * 
 * The class constructor uses the following array to take details for the database:
 * 
 *   $database_details = Array(
 *     "host" => "host",
 *     "username" => "database username",
 *     "password" => "password",
 *     "name" => "table name"
 *   );
 *   You must create a table to store credentials before using the class    
 */
    

/* we do not store the database details for security purposes, we only need them in the construtor
Private $db_servername;
Private $db_username;
Private $db_password;
*/
    
Private $database_name;
Private $database_handle;
        
    public function __construct($database_details)
    {
    /* This function expects an array such as:
    *   $database_details = Array(
    *     "host" => "host",
    *     "username" => "database username",
    *     "password" => "password",
    *     "name" => "table name"
    *   );
    */    
        $this->database_name = $database_details["name"];
        
        // Create connection
        $this->database_handle = new mysqli($database_details["host"], $database_details["username"], $database_details["password"], $this->database_name);

        // Check connection
        if ($this->database_handle->connect_error) {
            die("could not create database handle: " . $this->database_handle->connect_error);
        } 
        //echo "Connected successfully <br />";          
    }
    
    public function store_api_creds($keystring, $shared_secret)
    {   
        //These details below are what the class expects when retrieving the data from the database
        $table_name = "api_creds";
        
        $credentials = Array(
            "global_keystring" => $keystring,
            "global_shared_secret" => $shared_secret
        );     
               
        $this->store_creds($table_name, $credentials);
    }     
    
    public function store_access_creds($access_token, $access_secret)
    {
        //These details below are what the class expects when retrieving the data from the database
        $table_name = "access_tokens";
        
        $credentials = Array(
            "access_token" => $access_token,
            "access_secret" => $access_secret
        );     
               
        $this->store_creds($table_name, $credentials);        
    }
    
    public function store_oauth_token_secret($oauth_token_secret)
    {
        //These details below are what the class expects when retrieving the data from the database
        $table_name = "oauth_token";
        
        $credentials = Array(
            "oauth_token_secret" => $oauth_token_secret
        );     
               
        $this->store_creds($table_name, $credentials);          
    }
    
    private function store_creds($table_name, $creds) // takes an array of credentials, inserts them into $table_name  only if it contains no data
    { 
        $this->create_credentials_table($table_name, $creds); // if the table doesn't exist, create it with this function. drop table if it already exists. 
        
        // build the query for inserting the credentials.
        
            //if (mysqli_num_rows($this->database_handle->query("SELECT * from " . $table_name)) < 1) // this is unnecessary but left over from old function. Doesn't hurt to count the rows in a freshly created database 
            //{                
                //the rest of this functions code is tough to read, basically the two loops builds the query
                $query = "INSERT INTO " . $table_name . " (";

                    for ($i = 0; $i < sizeof($creds); $i++) // build the query - the array can be any length, including just 1 element, these loops builds the query with correct SQL syntax
                    {
                    	$array_keys = array_keys($creds);
                        $query .= $array_keys[$i];

                            if ($i < sizeof($creds) - 1)  // this means it only adds commas when there is more data in creds, 
                            {
                                $query .= ", ";
                            }
                    }

                $query .= ") VALUES (";

                    for ($i = 0; $i < sizeof($creds); $i++) // build the query - the array can be any length, including just 1 element, this loop builds the query with correct SQL syntax
                    {
                    	$array_values = array_values($creds);
                        $query .= "'" . $array_values[$i] . "'";
                        // This shouldn't be necessary, for some reason it doesn't work when we just go $creds[$i]

                            if ($i < sizeof($creds) - 1)  // this means it only adds commas when there is more data in creds, 
                            {
                                $query .= ", ";
                            }
                    }

                $query .= ")";

                //echo "<br />Insert data query: " . $query . "<br/><br />";  // for debugging

                    if ($this->database_handle->query($query) === FALSE)  // runs the query, we don't need to do anything on TRUE
                    {
                        echo "error in storing api credentials<br />";
                    }                            
            //}                   
    }
    
    private function create_credentials_table($table_name, $creds)
    { // when storing credentials, we first create the table for it based on the array given in $creds using the $table_name as the table name. Please note, this will always delete the table if it already exists
        
        // start by checking that the tables exist or not. If it exists, we drop the table since credentials are stored once, and can change if new requests are made.
        $query = "SHOW TABLES LIKE '" . $table_name . "';";
        
            if (mysqli_num_rows($this->database_handle->query($query)) > 0) // this checks to see if the table exists first by counting rows, if < 1, then we must drop the table. 
            {               
                $query = "DROP TABLE " . $table_name;
                //echo "drop table query: " . $query;
                    
                    if ($this->database_handle->query($query) === FALSE)
                    {
                        echo "error in dropping table: " . $table_name;
                    }   
            }
        
        // once the original table is dropped, we can create it again from scratch
        $query = "CREATE TABLE `" . $this->database_name . "`.`" . $table_name . "` ( ";

             for ($i = 0; $i < sizeof($creds); $i++) // build the query - the array can be any length, including just 1 element, this loop builds the query with correct SQL syntax
             {
             	 $array_keys = array_keys($creds);
                 $query .= "`" . $array_keys[$i] . "` CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL";

                     if ($i < sizeof($creds) - 1)  // this means it only adds commas when there is more data in creds, 
                     {
                         $query .= " , ";
                     }
             }

         $query .= ") ENGINE = InnoDB CHARACTER SET ascii COLLATE ascii_bin;";

         //echo "Build Table query: " . $query . "<br >";  // for debugging

             if ($this->database_handle->query($query) === FALSE)  // runs the query , we don't need to do anything on TRUE
             {
                 echo "error in creating table for api credentials<br />";
                 return False;
             }        

         return True; // don't have a use for this return type just yet, but may come in handy. */            
    } 
    
    public function get_access_creds()
    {
        $data = $this->run_fetch_query("SELECT * FROM access_tokens");
                
        // returns an array of access_creds
        $access_creds = Array(
            "access_token" => $data["access_token"],
            "access_secret" => $data["access_secret"]
        );
        
        return $access_creds;
    }

    public function get_api_creds()
    {
        $data = $this->run_fetch_query("SELECT * FROM api_creds");
                
        // returns an array of api_creds
        $api_creds = Array(
            "keystring" => $data["global_keystring"],
            "shared_secret" => $data["global_shared_secret"]
        );
        
        return $api_creds;
    }
        
    public function get_oauth_token_secret()
    {
        $data = $this->run_fetch_query("SELECT * FROM oauth_token");
                
        // returns an array of oauth_token data
        /*$token_secret = Array(
            "shared_secret" => $data["oauth_token_secret"]
        );*/
        
        return $data["oauth_token_secret"]; // no need for an array, simply restore it
    }  
    
    private function run_fetch_query($query)  // note that this function does not sanitize data, data must be sanitized before hand to avoid any sql injection
    { 
        $results = $this->database_handle->query($query);
        
            if ($results != NULL) // if not used to retrieve data, you can simply ignore returned data
            {
                $fetched_data = $results->fetch_assoc();  // use fetch_assoc so that the index's are named keys                                
                return $fetched_data;
            }
            else
            {
                echo "error in fetch query<br />";
                return Null; 
            }
    }
    
    public function __destruct()
    {
        mysqli_close($this->database_handle);
    }    
}

/*
 * To do:
 *  - create delete functions
 * 
 */

/* usage example
// set up the database access details
$database_details = Array(
    "host" => "localhost",
    "username" => "etsy",
    "password" => "etsyTest1234",
    "name" => "etsy_api"
);

// instantiate the class
$database = new credentials_database($database_details);

//code for storing credentials, creates the tables, then inserts the data. Code below uses example credentials, no-one is silly enough to leave actual creds in their code are they?!
$database->store_api_creds("az79ueep91fbbby6zhyds12c", "5mer6nx3o5");
$database->store_access_creds("59e39a1c6e4851025e49d5f0165561", "a8c9ad86ee");
$database->store_oauth_token_secret("54321");

// you can retrieve the codes using these functions.
var_dump($database->get_api_creds());
echo "<br />";
var_dump($database->get_access_creds());
echo "<br />";
var_dump($database->get_oauth_token_secret());
*/

?>


