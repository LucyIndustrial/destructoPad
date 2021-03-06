<?php
/* DestructoPad data layer
 * By Josh Lucy <josh{at}lucyindustrial_dawt_com>
 */

 // Error display code...
 //ini_set('display_errors', 1); 
 //error_reporting(E_ALL);
 
class destructoPadData {
    
    /********************
     * Config and setup *
     ********************/
    
    // Constants
    const DP_MODE_MYSQL = 0; // Put the data layer in MySQL mode.
    
    // Data layer mode
    private $dlMode = NULL;
    
    // MySQL configuration
    private $mysqlDbHost = "localhost";
    private $mysqlDbUser = "padProc";
    private $mysqlDbPass = "Blah@ASD4q5FA4asb";
    private $mysqlDbName = "destructoPad";
    
    // Class constructor
    function destructoPadData($t_mode) {
        // Set up a reference to ourself.
        global $glolbalref;
        $glolbalref[] = &$this;
        
        // Set the mode. We're not using this yet.
        $this->dlMode = $t_mode; // Should be one of the DP_MODE_ constants
    }
    
    /*********************
     * Private functions *
     *********************/
    
    // SECTION: MySQL
    
    // Open a connection to our MySQL DB
    // and return the MySQL link ID.
    private function mysqlCreateConn() {
        // Set up return value.
        $retVal['success'] = FALSE;
        $retVal['error'] = NULL;
        $retVla['conn'] = NULL;
        
        // Open a MySQLi connection using our configured parameters
        $retVal['conn'] = new mysqli($this->mysqlDbHost, $this->mysqlDbUser, $this->mysqlDbPass, $this->mysqlDbName);
        
        // If some sort of error just occured...
        if ($retVal['conn']->connect_errno) {
            $retVal['error'] = "MySQL error on connection: " . $retVal['conn']->connect_errno . " - " . $retVal['conn']->connect_error;
        }
        else {
            // If we had no error that means we're looking good.
            $retVal['success'] = TRUE;
        }
        
        // Return results.
        return $retVal;
    }
    
    // Close our MySQL connection
    private function mysqlCloseConn($t_conn) {
        // Set up return value
        $retVal['success'] = FALSE;
        $retVal['error'] = NULL;
        
        // Close the connection
        $t_conn->close();
        if ($t_conn->errno) {
            $retVal['error'] = "MySQL error on closing connection: " . $t_conn->errno . " - " . $t_conn->connect_error;
        }
        else {
            $retVal['success'] = TRUE;
        }
        
        // Return results.
        return $retVal;
    }
    
    // Use MySQL to add a pad.
    private function mysqlAddPad($t_hash, $t_expire, $t_data) {
        // Set up our return values.
        $retVal['success'] = FALSE;
        $retVal['error'] = NULL;
                
        // Prepare input
        $input = array($t_hash, $t_expire, $t_data);
		
        // Error check
        if(empty($input[0])) { 
            $retVal['error'] = "Pad add error: hash is empty."; 
        } elseif(empty($input[1])) {
            $retVal['error'] = "Pad add error: expire is empty.";
        } elseif(empty($input[2])) {
            $retVal['error'] = "Pad add error: data is empty.";
	} else {
	    // Open the database.
            $openEngine = $this->mysqlCreateConn();
	}
        
        // If the engine opened...
        if ($openEngine['success'] == TRUE) {
            // Set our engine object using the returned reference.
            $dbEngine = $openEngine['conn'];
            
            // Initialize our statement creator.
            $addStmt = $dbEngine->stmt_init();
            
            // Prepare our sproc call and bind variables.
            $addStmt = $dbEngine->prepare("CALL addPad(?, ?, ?);");
            $addStmt->bind_param('sis', $input[0], $input[1], $input[2]);
            
            // Try to execute the prepared statement.
            if($addStmt->execute()) {
                // If it works flag the response.
                $retVal['success'] = TRUE;
            }
            else {
                // If we have a failure flag the response and set the error.
                $retVal['success'] = FALSE;
                $retVal['error'] = "MySQL error on adding pad: " . $dbEngine->errno . " - " . $dbEngine->error;
            }
            
            // Close our "add statement" down.
            $addStmt->close();
            
            // Close DB connection properly.
            $dbEngine->close();
        }
        
        // Return results.
        return $retVal;
    }
    
    // Use MySQL to get a pad.
    private function mysqlGetPad($t_hash) {
        // Set up our return values.
        $retVal['encryptedBlock'] = NULL;
        $retVal['success'] = FALSE;
        $retVal['error'] = NULL;
        
        // If we have a hash
        if (!empty($t_hash)) {
            // Open the database.
            $openEngine = $this->mysqlCreateConn();
            
            // If the engine opened...
            if ($openEngine['success'] === TRUE) {
                // Set our engine object using the returned reference.
                $dbEngine = $openEngine['conn'];
                
                // Attempt to build our statement object.
                if ($getStmt = $dbEngine->prepare("CALL getPad(?);")) {
                    // 
                    $getStmt->bind_param('s', $t_hash);
                    
                    // ... and go!
                    $getStmt->execute();
                    
                    // Bind the value to the return value.
                    $getStmt->bind_result($retVal['encryptedBlock']);
                    
                    // Fetch the result. We should only have one so don't use a while().
                    $getStmt->fetch(); 
                    
                    // If our encrypted block isn't empty
                    if(!empty($retVal['encryptedBlock'])) {
                        // Declare success!
                        $retVal['success'] = TRUE;
                    }
                    
                    // Close down our statement object.
                    $getStmt->close();
                }
                else {
                    // Dump the error.
                    $retVal['error'] = "MySQL error on getting pad during statement prep: " . $getStmt->errno . " - " . $getStmt->error;
                }
                
            }
            else {
                // Dump error and bail out!
                $retVal['error'] = "MySQL error on getting pad during DB engine initialization: " . $dbEngine->errno . " - " . $dbEngine->error;
            }
            
        }
        else {
            // There's no point even creating the object so generate an error.
            $retVal['error'] = "Error on getting pad: input hash is empty.";
        }
        
        // Return results
        return $retVal;
    }

    // Use MySQL to add a pad.
    private function mysqlExpirePad() {
        // Set up our return values.
        $retVal['success'] = FALSE;
        $retVal['error'] = NULL;
        
        // Connect to MySQL
        $openEngine = $this->mysqlCreateConn();
	
        // If the engine opened...
        if ($openEngine['success'] == TRUE) {
            // Set our engine object using the returned reference.
            $dbEngine = $openEngine['conn'];
            
            // Initialize our statement creator.
            $expireStmt = $dbEngine->stmt_init();
            
            // Prepare our sproc call and bind variables.
            $expireStmt = $dbEngine->prepare("CALL expirePad();");
            
            // Try to execute the prepared statement.
            if($expireStmt->execute()) {
                // If it works flag the response.
                $retVal['success'] = TRUE;
            }
            else {
                // If we have a failure flag the response and set the error.
                $retVal['success'] = FALSE;
                $retVal['error'] = "MySQL error on expiring pad: " . $dbEngine->errno . " - " . $dbEngine->error;
            }
            
            // Close our "expire statement" down.
            $expireStmt->close();
            
            // Close DB connection properly.
            $dbEngine->close();
        }
        
        // Return results.
        return $retVal;
    }

    
    /********************
     * Public functions *
     ********************/
    
    // Override DB login creds. This is useful for the MySQL pad expiration.
    public function overrideMysqlCreds($t_user, $t_pass) {
        // Set up the return value with a default false meaning failure.
        $retVal = FALSE;
        
        // If we provide creds and are in the right mode then execute and call it good.
        if(!empty($t_user) && !empty($t_pass) && $this->dlMode === destructoPadData::DP_MODE_MYSQL) {
            // Assign creds.
            $this->mysqlDbUser = $t_user;
            $this->mysqlDbPass = $t_pass;
            $retVal = TRUE;
        }
        
        // Return TRUE if we reassigned the creds, false if we didn't.
        return $retVal;
    }
    
    // Generic function to store a newly-created pad
    public function addPad($t_messageID, $t_expire, $t_encryptedPad) {
        // Set up return value... each return value should contain these
        // values.
        $retVal['success'] = FALSE;
        $retVal['error'] = NULL;
        
        // Determine what mode I'm in.
        switch($this->dlMode) {
            // If I'm in MySQL mode
            case self::DP_MODE_MYSQL:
                // Write the data using MySQL
                $retVal = $this->mysqlAddPad($t_messageID, $t_expire, $t_encryptedPad);
                break;
            default:
                // Do nothing since we don't know what to do.
                $retVal['error'] = "Pad add error: invalid data layer mode.";
                break;
        }
        
        // Return the value.
        return $retVal;
    }
    
    // Generic function to retrieve and destroy a stored pad.
    public function getPad($t_messageID) {
        // Set up return value... each return value should contain these
        // values.
        $retVal['encryptedBlock'] = NULL;
        $retVal['success'] = FALSE;
        $retVal['error'] = NULL;
        
	$pid = $t_messageID;
        
        // Determine what mode I'm in.
        switch($this->dlMode) {
            // If I'm in MySQL mode
            case self::DP_MODE_MYSQL:
                // Get the data using MySQL
                $retVal = $this->mysqlGetPad($pid);
                break;
            default:
                // Do nothing, since we don't know what to do.
                $retVal['error'] = "Pad get error: invalid data layer mode.";
                break;
        }
        
        // Return the value.
        return $retVal;
    }
    
    // Generic function to exprire stored pads.
    public function expirePad() {
        // Set up return value... each return value should contain these
        // values.
        $retVal['success'] = FALSE;
        $retVal['error'] = NULL;
        
        // Determine what mode I'm in.
        switch($this->dlMode) {
            // If I'm in MySQL mode
            case self::DP_MODE_MYSQL:
                // Expire pads using MySQL
                $retVal = $this->mysqlExpirePad();
                break;
            default:
                // Do nothing, since we don't know what to do.
                $retVal['error'] = "Expire get error: invalid data layer mode.";
                break;
        }
        
        // Return the value.
        return $retVal;
    }
}
