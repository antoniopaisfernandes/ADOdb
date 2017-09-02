<?php

//==============================================================================================
// CLASS ADOConnection
//==============================================================================================

/**
 * Connection object. For connecting to databases, and executing queries.
 */
abstract class ADOConnection
{
    //
    // PUBLIC VARS
    //
    var $dataProvider = 'native';
    var $databaseType = '';		/// RDBMS currently in use, eg. odbc, mysql, mssql
    var $database = '';			/// Name of database to be used.
    var $host = '';				/// The hostname of the database server
    var $user = '';				/// The username which is used to connect to the database server.
    var $password = '';			/// Password for the username. For security, we no longer store it.
    var $debug = false;			/// if set to true will output sql statements
    var $maxblobsize = 262144;	/// maximum size of blobs or large text fields (262144 = 256K)-- some db's die otherwise like foxpro
    var $concat_operator = '+'; /// default concat operator -- change to || for Oracle/Interbase
    var $substr = 'substr';		/// substring operator
    var $length = 'length';		/// string length ofperator
    var $random = 'rand()';		/// random function
    var $upperCase = 'upper';		/// uppercase function
    var $fmtDate = "'Y-m-d'";	/// used by DBDate() as the default date format used by the database
    var $fmtTimeStamp = "'Y-m-d, h:i:s A'"; /// used by DBTimeStamp as the default timestamp fmt.
    var $true = '1';			/// string that represents TRUE for a database
    var $false = '0';			/// string that represents FALSE for a database
    var $replaceQuote = "\\'";	/// string to use to replace quotes
    var $nameQuote = '"';		/// string to use to quote identifiers and names
    var $charSet=false;			/// character set to use - only for interbase, postgres and oci8
    var $metaDatabasesSQL = '';
    var $metaTablesSQL = '';
    var $uniqueOrderBy = false; /// All order by columns have to be unique
    var $emptyDate = '&nbsp;';
    var $emptyTimeStamp = '&nbsp;';
    var $lastInsID = false;
    //--
    var $hasInsertID = false;		/// supports autoincrement ID?
    var $hasAffectedRows = false;	/// supports affected rows for update/delete?
    var $hasTop = false;			/// support mssql/access SELECT TOP 10 * FROM TABLE
    var $hasLimit = false;			/// support pgsql/mysql SELECT * FROM TABLE LIMIT 10
    var $readOnly = false;			/// this is a readonly database - used by phpLens
    var $hasMoveFirst = false;		/// has ability to run MoveFirst(), scrolling backwards
    var $hasGenID = false;			/// can generate sequences using GenID();
    var $hasTransactions = true;	/// has transactions
    //--
    var $genID = 0;					/// sequence id used by GenID();
    var $raiseErrorFn = false;		/// error function to call
    var $isoDates = false;			/// accepts dates in ISO format
    var $cacheSecs = 3600;			/// cache for 1 hour

    // memcache
    var $memCache = false; /// should we use memCache instead of caching in files
    var $memCacheHost; /// memCache host
    var $memCachePort = 11211; /// memCache port
    var $memCacheCompress = false; /// Use 'true' to store the item compressed (uses zlib)

    var $sysDate = false; /// name of function that returns the current date
    var $sysTimeStamp = false; /// name of function that returns the current timestamp
    var $sysUTimeStamp = false; // name of function that returns the current timestamp accurate to the microsecond or nearest fraction
    var $arrayClass = 'ADORecordSet_array'; /// name of class used to generate array recordsets, which are pre-downloaded recordsets

    var $noNullStrings = false; /// oracle specific stuff - if true ensures that '' is converted to ' '
    var $numCacheHits = 0;
    var $numCacheMisses = 0;
    var $pageExecuteCountRows = true;
    var $uniqueSort = false; /// indicates that all fields in order by must be unique
    var $leftOuter = false; /// operator to use for left outer join in WHERE clause
    var $rightOuter = false; /// operator to use for right outer join in WHERE clause
    var $ansiOuter = false; /// whether ansi outer join syntax supported
    var $autoRollback = false; // autoRollback on PConnect().
    var $poorAffectedRows = false; // affectedRows not working or unreliable

    var $fnExecute = false;
    var $fnCacheExecute = false;
    var $blobEncodeType = false; // false=not required, 'I'=encode to integer, 'C'=encode to char
    var $rsPrefix = "ADORecordSet_";

    var $autoCommit = true;		/// do not modify this yourself - actually private
    var $transOff = 0;			/// temporarily disable transactions
    var $transCnt = 0;			/// count of nested transactions

    var $fetchMode=false;

    var $null2null = 'null'; // in autoexecute/getinsertsql/getupdatesql, this value will be converted to a null
    var $bulkBind = false; // enable 2D Execute array
    //
    // PRIVATE VARS
    //
    var $_oldRaiseFn =  false;
    var $_transOK = null;
    var $_connectionID	= false;	/// The returned link identifier whenever a successful database connection is made.
    var $_errorMsg = false;		/// A variable which was used to keep the returned last error message.  The value will
    /// then returned by the errorMsg() function
    var $_errorCode = false;	/// Last error code, not guaranteed to be used - only by oci8
    var $_queryID = false;		/// This variable keeps the last created result link identifier

    var $_isPersistentConnection = false;	/// A boolean variable to state whether its a persistent connection or normal connection.	*/
    var $_bindInputArray = false; /// set to true if ADOConnection.Execute() permits binding of array parameters.
    var $_evalAll = false;
    var $_affected = false;
    var $_logsql = false;
    var $_transmode = ''; // transaction mode


    /**
     * Default Constructor.
     * We define it even though it does not actually do anything. This avoids
     * getting a PHP Fatal error:  Cannot call constructor if a subclass tries
     * to call its parent constructor.
     */
    public function __construct()
    {
    }

    /*
     * Additional parameters that may be passed to drivers in the connect string
     * Driver must be coded to accept the parameters
     */
    protected $connectionParameters = array();

    /**
     * Adds a parameter to the connection string.
     *
     * These parameters are added to the connection string when connecting,
     * if the driver is coded to use it.
     *
     * @param	string	$parameter	The name of the parameter to set
     * @param	string	$value		The value of the parameter
     *
     * @return null
     *
     * @example, for mssqlnative driver ('CharacterSet','UTF-8')
     */
    final public function setConnectionParameter($parameter,$value)
    {

        $this->connectionParameters[] = array($parameter=>$value);

    }

    static function Version() {
        global $ADODB_vers;

        // Semantic Version number matching regex
        $regex = '^[vV]?(\d+\.\d+\.\d+'         // Version number (X.Y.Z) with optional 'V'
            . '(?:-(?:'                         // Optional preprod version: a '-'
            . 'dev|'                            // followed by 'dev'
            . '(?:(?:alpha|beta|rc)(?:\.\d+))'  // or a preprod suffix and version number
            . '))?)(?:\s|$)';                   // Whitespace or end of string

        if (!preg_match("/$regex/", $ADODB_vers, $matches)) {
            // This should normally not happen... Return whatever is between the start
            // of the string and the first whitespace (or the end of the string).
            self::outp("Invalid version number: '$ADODB_vers'", 'Version');
            $regex = '^[vV]?(.*?)(?:\s|$)';
            preg_match("/$regex/", $ADODB_vers, $matches);
        }
        return $matches[1];
    }

    /**
    Get server version info...

    @returns An array with 2 elements: $arr['string'] is the description string,
    and $arr[version] is the version (also a string).
     */
    function ServerInfo() {
        return array('description' => '', 'version' => '');
    }

    function IsConnected() {
        return !empty($this->_connectionID);
    }

    function _findvers($str) {
        if (preg_match('/([0-9]+\.([0-9\.])+)/',$str, $arr)) {
            return $arr[1];
        } else {
            return '';
        }
    }

    /**
     * All error messages go through this bottleneck function.
     * You can define your own handler by defining the function name in ADODB_OUTP.
     */
    static function outp($msg,$newline=true) {
        global $ADODB_FLUSH,$ADODB_OUTP;

        if (defined('ADODB_OUTP')) {
            $fn = ADODB_OUTP;
            $fn($msg,$newline);
            return;
        } else if (isset($ADODB_OUTP)) {
            $fn = $ADODB_OUTP;
            $fn($msg,$newline);
            return;
        }

        if ($newline) {
            $msg .= "<br>\n";
        }

        if (isset($_SERVER['HTTP_USER_AGENT']) || !$newline) {
            echo $msg;
        } else {
            echo strip_tags($msg);
        }


        if (!empty($ADODB_FLUSH) && ob_get_length() !== false) {
            flush(); //  do not flush if output buffering enabled - useless - thx to Jesse Mullan
        }

    }

    function Time() {
        $rs = $this->_Execute("select $this->sysTimeStamp");
        if ($rs && !$rs->EOF) {
            return $this->UnixTimeStamp(reset($rs->fields));
        }

        return false;
    }

    /**
     * Connect to database
     *
     * @param [argHostname]		Host to connect to
     * @param [argUsername]		Userid to login
     * @param [argPassword]		Associated password
     * @param [argDatabaseName]	database
     * @param [forceNew]		force new connection
     *
     * @return true or false
     */
    function Connect($argHostname = "", $argUsername = "", $argPassword = "", $argDatabaseName = "", $forceNew = false) {
        if ($argHostname != "") {
            $this->host = $argHostname;
        }
        if ( strpos($this->host, ':') > 0 && isset($this->port) ) {
            list($this->host, $this->port) = explode(":", $this->host, 2);
        }
        if ($argUsername != "") {
            $this->user = $argUsername;
        }
        if ($argPassword != "") {
            $this->password = 'not stored'; // not stored for security reasons
        }
        if ($argDatabaseName != "") {
            $this->database = $argDatabaseName;
        }

        $this->_isPersistentConnection = false;

        if ($forceNew) {
            if ($rez=$this->_nconnect($this->host, $this->user, $argPassword, $this->database)) {
                return true;
            }
        } else {
            if ($rez=$this->_connect($this->host, $this->user, $argPassword, $this->database)) {
                return true;
            }
        }
        if (isset($rez)) {
            $err = $this->ErrorMsg();
            $errno = $this->ErrorNo();
            if (empty($err)) {
                $err = "Connection error to server '$argHostname' with user '$argUsername'";
            }
        } else {
            $err = "Missing extension for ".$this->dataProvider;
            $errno = 0;
        }
        if ($fn = $this->raiseErrorFn) {
            $fn($this->databaseType, 'CONNECT', $errno, $err, $this->host, $this->database, $this);
        }

        $this->_connectionID = false;
        if ($this->debug) {
            ADOConnection::outp( $this->host.': '.$err);
        }
        return false;
    }

    function _nconnect($argHostname, $argUsername, $argPassword, $argDatabaseName) {
        return $this->_connect($argHostname, $argUsername, $argPassword, $argDatabaseName);
    }


    /**
     * Always force a new connection to database - currently only works with oracle
     *
     * @param [argHostname]		Host to connect to
     * @param [argUsername]		Userid to login
     * @param [argPassword]		Associated password
     * @param [argDatabaseName]	database
     *
     * @return true or false
     */
    function NConnect($argHostname = "", $argUsername = "", $argPassword = "", $argDatabaseName = "") {
        return $this->Connect($argHostname, $argUsername, $argPassword, $argDatabaseName, true);
    }

    /**
     * Establish persistent connect to database
     *
     * @param [argHostname]		Host to connect to
     * @param [argUsername]		Userid to login
     * @param [argPassword]		Associated password
     * @param [argDatabaseName]	database
     *
     * @return return true or false
     */
    function PConnect($argHostname = "", $argUsername = "", $argPassword = "", $argDatabaseName = "") {

        if (defined('ADODB_NEVER_PERSIST')) {
            return $this->Connect($argHostname,$argUsername,$argPassword,$argDatabaseName);
        }

        if ($argHostname != "") {
            $this->host = $argHostname;
        }
        if ( strpos($this->host, ':') > 0 && isset($this->port) ) {
            list($this->host, $this->port) = explode(":", $this->host, 2);
        }
        if ($argUsername != "") {
            $this->user = $argUsername;
        }
        if ($argPassword != "") {
            $this->password = 'not stored';
        }
        if ($argDatabaseName != "") {
            $this->database = $argDatabaseName;
        }

        $this->_isPersistentConnection = true;

        if ($rez = $this->_pconnect($this->host, $this->user, $argPassword, $this->database)) {
            return true;
        }
        if (isset($rez)) {
            $err = $this->ErrorMsg();
            if (empty($err)) {
                $err = "Connection error to server '$argHostname' with user '$argUsername'";
            }
            $ret = false;
        } else {
            $err = "Missing extension for ".$this->dataProvider;
            $ret = 0;
        }
        if ($fn = $this->raiseErrorFn) {
            $fn($this->databaseType,'PCONNECT',$this->ErrorNo(),$err,$this->host,$this->database,$this);
        }

        $this->_connectionID = false;
        if ($this->debug) {
            ADOConnection::outp( $this->host.': '.$err);
        }
        return $ret;
    }

    function outp_throw($msg,$src='WARN',$sql='') {
        if (defined('ADODB_ERROR_HANDLER') &&  ADODB_ERROR_HANDLER == 'adodb_throw') {
            adodb_throw($this->databaseType,$src,-9999,$msg,$sql,false,$this);
            return;
        }
        ADOConnection::outp($msg);
    }

    // create cache class. Code is backward compat with old memcache implementation
    function _CreateCache() {
        global $ADODB_CACHE, $ADODB_CACHE_CLASS;

        if ($this->memCache) {
            global $ADODB_INCLUDED_MEMCACHE;

            if (empty($ADODB_INCLUDED_MEMCACHE)) {
                include_once(ADODB_DIR.'/adodb-memcache.lib.inc.php');
            }
            $ADODB_CACHE = new ADODB_Cache_MemCache($this);
        } else {
            $ADODB_CACHE = new $ADODB_CACHE_CLASS($this);
        }
    }

    // Format date column in sql string given an input format that understands Y M D
    function SQLDate($fmt, $col=false) {
        if (!$col) {
            $col = $this->sysDate;
        }
        return $col; // child class implement
    }

    /**
     * Should prepare the sql statement and return the stmt resource.
     * For databases that do not support this, we return the $sql. To ensure
     * compatibility with databases that do not support prepare:
     *
     *   $stmt = $db->Prepare("insert into table (id, name) values (?,?)");
     *   $db->Execute($stmt,array(1,'Jill')) or die('insert failed');
     *   $db->Execute($stmt,array(2,'Joe')) or die('insert failed');
     *
     * @param sql	SQL to send to database
     *
     * @return return FALSE, or the prepared statement, or the original sql if
     *         if the database does not support prepare.
     *
     */
    function Prepare($sql) {
        return $sql;
    }

    /**
     * Some databases, eg. mssql require a different function for preparing
     * stored procedures. So we cannot use Prepare().
     *
     * Should prepare the stored procedure  and return the stmt resource.
     * For databases that do not support this, we return the $sql. To ensure
     * compatibility with databases that do not support prepare:
     *
     * @param sql	SQL to send to database
     *
     * @return return FALSE, or the prepared statement, or the original sql if
     *         if the database does not support prepare.
     *
     */
    function PrepareSP($sql,$param=true) {
        return $this->Prepare($sql,$param);
    }

    /**
     * PEAR DB Compat
     */
    function Quote($s) {
        return $this->qstr($s,false);
    }

    /**
     * Requested by "Karsten Dambekalns" <k.dambekalns@fishfarm.de>
     */
    function QMagic($s) {
        return $this->qstr($s,get_magic_quotes_gpc());
    }

    function q(&$s) {
        //if (!empty($this->qNull && $s == 'null') {
        //	return $s;
        //}
        $s = $this->qstr($s,false);
    }

    /**
     * PEAR DB Compat - do not use internally.
     */
    function ErrorNative() {
        return $this->ErrorNo();
    }


    /**
     * PEAR DB Compat - do not use internally.
     */
    function nextId($seq_name) {
        return $this->GenID($seq_name);
    }

    /**
     * Lock a row, will escalate and lock the table if row locking not supported
     * will normally free the lock at the end of the transaction
     *
     * @param $table	name of table to lock
     * @param $where	where clause to use, eg: "WHERE row=12". If left empty, will escalate to table lock
     */
    function RowLock($table,$where,$col='1 as adodbignore') {
        return false;
    }

    function CommitLock($table) {
        return $this->CommitTrans();
    }

    function RollbackLock($table) {
        return $this->RollbackTrans();
    }

    /**
     * PEAR DB Compat - do not use internally.
     *
     * The fetch modes for NUMERIC and ASSOC for PEAR DB and ADODB are identical
     * for easy porting :-)
     *
     * @param mode	The fetchmode ADODB_FETCH_ASSOC or ADODB_FETCH_NUM
     * @returns		The previous fetch mode
     */
    function SetFetchMode($mode) {
        $old = $this->fetchMode;
        $this->fetchMode = $mode;

        if ($old === false) {
            global $ADODB_FETCH_MODE;
            return $ADODB_FETCH_MODE;
        }
        return $old;
    }


    /**
     * PEAR DB Compat - do not use internally.
     */
    function Query($sql, $inputarr=false) {
        $rs = $this->Execute($sql, $inputarr);
        if (!$rs && defined('ADODB_PEAR')) {
            return ADODB_PEAR_Error();
        }
        return $rs;
    }


    /**
     * PEAR DB Compat - do not use internally
     */
    function LimitQuery($sql, $offset, $count, $params=false) {
        $rs = $this->SelectLimit($sql, $count, $offset, $params);
        if (!$rs && defined('ADODB_PEAR')) {
            return ADODB_PEAR_Error();
        }
        return $rs;
    }


    /**
     * PEAR DB Compat - do not use internally
     */
    function Disconnect() {
        return $this->Close();
    }

    /**
     * Returns a placeholder for query parameters
     * e.g. $DB->Param('a') will return
     * - '?' for most databases
     * - ':a' for Oracle
     * - '$1', '$2', etc. for PostgreSQL
     * @param string $name parameter's name, false to force a reset of the
     *                     number to 1 (for databases that require positioned
     *                     params such as PostgreSQL; note that ADOdb will
     *                     automatically reset this when executing a query )
     * @param string $type (unused)
     * @return string query parameter placeholder
     */
    function Param($name,$type='C') {
        return '?';
    }

    /*
        InParameter and OutParameter are self-documenting versions of Parameter().
    */
    function InParameter(&$stmt,&$var,$name,$maxLen=4000,$type=false) {
        return $this->Parameter($stmt,$var,$name,false,$maxLen,$type);
    }

    /*
    */
    function OutParameter(&$stmt,&$var,$name,$maxLen=4000,$type=false) {
        return $this->Parameter($stmt,$var,$name,true,$maxLen,$type);

    }


    /*
    Usage in oracle
        $stmt = $db->Prepare('select * from table where id =:myid and group=:group');
        $db->Parameter($stmt,$id,'myid');
        $db->Parameter($stmt,$group,'group',64);
        $db->Execute();

        @param $stmt Statement returned by Prepare() or PrepareSP().
        @param $var PHP variable to bind to
        @param $name Name of stored procedure variable name to bind to.
        @param [$isOutput] Indicates direction of parameter 0/false=IN  1=OUT  2= IN/OUT. This is ignored in oci8.
        @param [$maxLen] Holds an maximum length of the variable.
        @param [$type] The data type of $var. Legal values depend on driver.

    */
    function Parameter(&$stmt,&$var,$name,$isOutput=false,$maxLen=4000,$type=false) {
        return false;
    }


    function IgnoreErrors($saveErrs=false) {
        if (!$saveErrs) {
            $saveErrs = array($this->raiseErrorFn,$this->_transOK);
            $this->raiseErrorFn = false;
            return $saveErrs;
        } else {
            $this->raiseErrorFn = $saveErrs[0];
            $this->_transOK = $saveErrs[1];
        }
    }

    /**
     * Improved method of initiating a transaction. Used together with CompleteTrans().
     * Advantages include:
     *
     * a. StartTrans/CompleteTrans is nestable, unlike BeginTrans/CommitTrans/RollbackTrans.
     *    Only the outermost block is treated as a transaction.<br>
     * b. CompleteTrans auto-detects SQL errors, and will rollback on errors, commit otherwise.<br>
     * c. All BeginTrans/CommitTrans/RollbackTrans inside a StartTrans/CompleteTrans block
     *    are disabled, making it backward compatible.
     */
    function StartTrans($errfn = 'ADODB_TransMonitor') {
        if ($this->transOff > 0) {
            $this->transOff += 1;
            return true;
        }

        $this->_oldRaiseFn = $this->raiseErrorFn;
        $this->raiseErrorFn = $errfn;
        $this->_transOK = true;

        if ($this->debug && $this->transCnt > 0) {
            ADOConnection::outp("Bad Transaction: StartTrans called within BeginTrans");
        }
        $ok = $this->BeginTrans();
        $this->transOff = 1;
        return $ok;
    }


    /**
    Used together with StartTrans() to end a transaction. Monitors connection
    for sql errors, and will commit or rollback as appropriate.

    @autoComplete if true, monitor sql errors and commit and rollback as appropriate,
    and if set to false force rollback even if no SQL error detected.
    @returns true on commit, false on rollback.
     */
    function CompleteTrans($autoComplete = true) {
        if ($this->transOff > 1) {
            $this->transOff -= 1;
            return true;
        }
        $this->raiseErrorFn = $this->_oldRaiseFn;

        $this->transOff = 0;
        if ($this->_transOK && $autoComplete) {
            if (!$this->CommitTrans()) {
                $this->_transOK = false;
                if ($this->debug) {
                    ADOConnection::outp("Smart Commit failed");
                }
            } else {
                if ($this->debug) {
                    ADOConnection::outp("Smart Commit occurred");
                }
            }
        } else {
            $this->_transOK = false;
            $this->RollbackTrans();
            if ($this->debug) {
                ADOCOnnection::outp("Smart Rollback occurred");
            }
        }

        return $this->_transOK;
    }

    /*
        At the end of a StartTrans/CompleteTrans block, perform a rollback.
    */
    function FailTrans() {
        if ($this->debug)
            if ($this->transOff == 0) {
                ADOConnection::outp("FailTrans outside StartTrans/CompleteTrans");
            } else {
                ADOConnection::outp("FailTrans was called");
                adodb_backtrace();
            }
        $this->_transOK = false;
    }

    /**
    Check if transaction has failed, only for Smart Transactions.
     */
    function HasFailedTrans() {
        if ($this->transOff > 0) {
            return $this->_transOK == false;
        }
        return false;
    }

    /**
     * Execute SQL
     *
     * @param string $sql SQL statement to execute, or possibly an array holding prepared statement ($sql[0] will hold sql text)
     * @param false|array $inputarr holds the input data to bind to. Null elements will be set to null.
     * @return false|ADORecordSet
     */
    public function Execute($sql, $inputarr = false) {
        if ($this->fnExecute) {
            $fn = $this->fnExecute;
            $ret = $fn($this,$sql,$inputarr);
            if (isset($ret)) {
                return $ret;
            }
        }
        if ($inputarr !== false) {
            if (!is_array($inputarr)) {
                $inputarr = array($inputarr);
            }

            $element0 = reset($inputarr);
            # is_object check because oci8 descriptors can be passed in
            $array_2d = $this->bulkBind && is_array($element0) && !is_object(reset($element0));

            //remove extra memory copy of input -mikefedyk
            unset($element0);

            if (!is_array($sql) && !$this->_bindInputArray) {
                // @TODO this would consider a '?' within a string as a parameter...
                $sqlarr = explode('?',$sql);
                $nparams = sizeof($sqlarr)-1;

                if (!$array_2d) {
                    // When not Bind Bulk - convert to array of arguments list
                    $inputarr = array($inputarr);
                } else {
                    // Bulk bind - Make sure all list of params have the same number of elements
                    $countElements = array_map('count', $inputarr);
                    if (1 != count(array_unique($countElements))) {
                        $this->outp_throw(
                            "[bulk execute] Input array has different number of params  [" . print_r($countElements, true) .  "].",
                            'Execute'
                        );
                        return false;
                    }
                    unset($countElements);
                }
                // Make sure the number of parameters provided in the input
                // array matches what the query expects
                $element0 = reset($inputarr);
                if ($nparams != count($element0)) {
                    $this->outp_throw(
                        "Input array has " . count($element0) .
                        " params, does not match query: '" . htmlspecialchars($sql) . "'",
                        'Execute'
                    );
                    return false;
                }

                // clean memory
                unset($element0);

                foreach($inputarr as $arr) {
                    $sql = ''; $i = 0;
                    //Use each() instead of foreach to reduce memory usage -mikefedyk
                    while(list(, $v) = each($arr)) {
                        $sql .= $sqlarr[$i];
                        // from Ron Baldwin <ron.baldwin#sourceprose.com>
                        // Only quote string types
                        $typ = gettype($v);
                        if ($typ == 'string') {
                            //New memory copy of input created here -mikefedyk
                            $sql .= $this->qstr($v);
                        } else if ($typ == 'double') {
                            $sql .= str_replace(',','.',$v); // locales fix so 1.1 does not get converted to 1,1
                        } else if ($typ == 'boolean') {
                            $sql .= $v ? $this->true : $this->false;
                        } else if ($typ == 'object') {
                            if (method_exists($v, '__toString')) {
                                $sql .= $this->qstr($v->__toString());
                            } else {
                                $sql .= $this->qstr((string) $v);
                            }
                        } else if ($v === null) {
                            $sql .= 'NULL';
                        } else {
                            $sql .= $v;
                        }
                        $i += 1;

                        if ($i == $nparams) {
                            break;
                        }
                    } // while
                    if (isset($sqlarr[$i])) {
                        $sql .= $sqlarr[$i];
                        if ($i+1 != sizeof($sqlarr)) {
                            $this->outp_throw( "Input Array does not match ?: ".htmlspecialchars($sql),'Execute');
                        }
                    } else if ($i != sizeof($sqlarr)) {
                        $this->outp_throw( "Input array does not match ?: ".htmlspecialchars($sql),'Execute');
                    }

                    $ret = $this->_Execute($sql);
                    if (!$ret) {
                        return $ret;
                    }
                }
            } else {
                if ($array_2d) {
                    if (is_string($sql)) {
                        $stmt = $this->Prepare($sql);
                    } else {
                        $stmt = $sql;
                    }

                    foreach($inputarr as $arr) {
                        $ret = $this->_Execute($stmt,$arr);
                        if (!$ret) {
                            return $ret;
                        }
                    }
                } else {
                    $ret = $this->_Execute($sql,$inputarr);
                }
            }
        } else {
            $ret = $this->_Execute($sql,false);
        }

        return $ret;
    }

    function _Execute($sql,$inputarr=false) {
        // ExecuteCursor() may send non-string queries (such as arrays),
        // so we need to ignore those.
        if( is_string($sql) ) {
            // Strips keyword used to help generate SELECT COUNT(*) queries
            // from SQL if it exists.
            $sql = ADODB_str_replace( '_ADODB_COUNT', '', $sql );
        }

        if ($this->debug) {
            global $ADODB_INCLUDED_LIB;
            if (empty($ADODB_INCLUDED_LIB)) {
                include_once(ADODB_DIR.'/adodb-lib.inc.php');
            }
            $this->_queryID = _adodb_debug_execute($this, $sql,$inputarr);
        } else {
            $this->_queryID = @$this->_query($sql,$inputarr);
        }

        // ************************
        // OK, query executed
        // ************************

        // error handling if query fails
        if ($this->_queryID === false) {
            if ($this->debug == 99) {
                adodb_backtrace(true,5);
            }
            $fn = $this->raiseErrorFn;
            if ($fn) {
                $fn($this->databaseType,'EXECUTE',$this->ErrorNo(),$this->ErrorMsg(),$sql,$inputarr,$this);
            }
            return false;
        }

        // return simplified recordset for inserts/updates/deletes with lower overhead
        if ($this->_queryID === true) {
            $rsclass = $this->rsPrefix.'empty';
            $rs = (class_exists($rsclass)) ? new $rsclass():  new ADORecordSet_empty();

            return $rs;
        }

        if ($this->dataProvider == 'pdo' && $this->databaseType != 'pdo') {
            // PDO uses a slightly different naming convention for the
            // recordset class if the database type is changed, so we must
            // treat it specifically. The mysql driver leaves the
            // databaseType as pdo
            $rsclass = $this->rsPrefix . 'pdo_' . $this->databaseType;
        } else {
            $rsclass = $this->rsPrefix . $this->databaseType;
        }

        // return real recordset from select statement
        $rs = new $rsclass($this->_queryID,$this->fetchMode);
        $rs->connection = $this; // Pablo suggestion
        $rs->Init();
        if (is_array($sql)) {
            $rs->sql = $sql[0];
        } else {
            $rs->sql = $sql;
        }
        if ($rs->_numOfRows <= 0) {
            global $ADODB_COUNTRECS;
            if ($ADODB_COUNTRECS) {
                if (!$rs->EOF) {
                    $rs = $this->_rs2rs($rs,-1,-1,!is_array($sql));
                    $rs->_queryID = $this->_queryID;
                } else
                    $rs->_numOfRows = 0;
            }
        }
        return $rs;
    }

    function CreateSequence($seqname='adodbseq',$startID=1) {
        if (empty($this->_genSeqSQL)) {
            return false;
        }
        return $this->Execute(sprintf($this->_genSeqSQL,$seqname,$startID));
    }

    function DropSequence($seqname='adodbseq') {
        if (empty($this->_dropSeqSQL)) {
            return false;
        }
        return $this->Execute(sprintf($this->_dropSeqSQL,$seqname));
    }

    /**
     * Generates a sequence id and stores it in $this->genID;
     * GenID is only available if $this->hasGenID = true;
     *
     * @param seqname		name of sequence to use
     * @param startID		if sequence does not exist, start at this ID
     * @return		0 if not supported, otherwise a sequence id
     */
    function GenID($seqname='adodbseq',$startID=1) {
        if (!$this->hasGenID) {
            return 0; // formerly returns false pre 1.60
        }

        $getnext = sprintf($this->_genIDSQL,$seqname);

        $holdtransOK = $this->_transOK;

        $save_handler = $this->raiseErrorFn;
        $this->raiseErrorFn = '';
        @($rs = $this->Execute($getnext));
        $this->raiseErrorFn = $save_handler;

        if (!$rs) {
            $this->_transOK = $holdtransOK; //if the status was ok before reset
            $createseq = $this->Execute(sprintf($this->_genSeqSQL,$seqname,$startID));
            $rs = $this->Execute($getnext);
        }
        if ($rs && !$rs->EOF) {
            $this->genID = reset($rs->fields);
        } else {
            $this->genID = 0; // false
        }

        if ($rs) {
            $rs->Close();
        }

        return $this->genID;
    }

    /**
     * @param $table string name of the table, not needed by all databases (eg. mysql), default ''
     * @param $column string name of the column, not needed by all databases (eg. mysql), default ''
     * @return  the last inserted ID. Not all databases support this.
     */
    function Insert_ID($table='',$column='') {
        if ($this->_logsql && $this->lastInsID) {
            return $this->lastInsID;
        }
        if ($this->hasInsertID) {
            return $this->_insertid($table,$column);
        }
        if ($this->debug) {
            ADOConnection::outp( '<p>Insert_ID error</p>');
            adodb_backtrace();
        }
        return false;
    }


    /**
     * Portable Insert ID. Pablo Roca <pabloroca#mvps.org>
     *
     * @return  the last inserted ID. All databases support this. But aware possible
     * problems in multiuser environments. Heavy test this before deploying.
     */
    function PO_Insert_ID($table="", $id="") {
        if ($this->hasInsertID){
            return $this->Insert_ID($table,$id);
        } else {
            return $this->GetOne("SELECT MAX($id) FROM $table");
        }
    }

    /**
     * @return int|false       # rows affected by UPDATE/DELETE
     */
    function Affected_Rows() {
        if ($this->hasAffectedRows) {
            if ($this->fnExecute === 'adodb_log_sql') {
                if ($this->_logsql && $this->_affected !== false) {
                    return $this->_affected;
                }
            }
            $val = $this->_affectedrows();
            return ($val < 0) ? false : $val;
        }

        if ($this->debug) {
            ADOConnection::outp( '<p>Affected_Rows error</p>',false);
        }
        return false;
    }


    /**
     * @return string   the last error message
     */
    function ErrorMsg() {
        if ($this->_errorMsg) {
            return '!! '.strtoupper($this->dataProvider.' '.$this->databaseType).': '.$this->_errorMsg;
        } else {
            return '';
        }
    }


    /**
     * @return int       the last error number. Normally 0 means no error.
     */
    function ErrorNo() {
        return ($this->_errorMsg) ? -1 : 0;
    }

    function MetaError($err=false) {
        include_once(ADODB_DIR."/adodb-error.inc.php");
        if ($err === false) {
            $err = $this->ErrorNo();
        }
        return adodb_error($this->dataProvider,$this->databaseType,$err);
    }

    function MetaErrorMsg($errno) {
        include_once(ADODB_DIR."/adodb-error.inc.php");
        return adodb_errormsg($errno);
    }

    /**
     * @returns array|false    an array with the primary key columns in it.
     */
    function MetaPrimaryKeys($table, $owner=false) {
        // owner not used in base class - see oci8
        $p = array();
        $objs = $this->MetaColumns($table);
        if ($objs) {
            foreach($objs as $v) {
                if (!empty($v->primary_key)) {
                    $p[] = $v->name;
                }
            }
        }
        if (sizeof($p)) {
            return $p;
        }
        if (function_exists('ADODB_VIEW_PRIMARYKEYS')) {
            return ADODB_VIEW_PRIMARYKEYS($this->databaseType, $this->database, $table, $owner);
        }
        return false;
    }

    /**
     * @returns array|false    assoc array where keys are tables, and values are foreign keys
     */
    function MetaForeignKeys($table, $owner=false, $upper=false) {
        return false;
    }
    /**
     * Choose a database to connect to. Many databases do not support this.
     *
     * @param string $dbName the name of the database to select
     * @return bool
     */
    function SelectDB($dbName) {return false;}


    /**
     * Will select, getting rows from $offset (1-based), for $nrows.
     * This simulates the MySQL "select * from table limit $offset,$nrows" , and
     * the PostgreSQL "select * from table limit $nrows offset $offset". Note that
     * MySQL and PostgreSQL parameter ordering is the opposite of the other.
     * eg.
     *  SelectLimit('select * from table',3); will return rows 1 to 3 (1-based)
     *  SelectLimit('select * from table',3,2); will return rows 3 to 5 (1-based)
     *
     * Uses SELECT TOP for Microsoft databases (when $this->hasTop is set)
     * BUG: Currently SelectLimit fails with $sql with LIMIT or TOP clause already set
     *
     * @param sql
     * @param [offset]	is the row to start calculations from (1-based)
     * @param [nrows]		is the number of rows to get
     * @param [inputarr]	array of bind variables
     * @param [secs2cache]		is a private parameter only used by jlim
     * @return		the recordset ($rs->databaseType == 'array')
     */
    function SelectLimit($sql,$nrows=-1,$offset=-1, $inputarr=false,$secs2cache=0) {
        $nrows = (int)$nrows;
        $offset = (int)$offset;

        if ($this->hasTop && $nrows > 0) {
            // suggested by Reinhard Balling. Access requires top after distinct
            // Informix requires first before distinct - F Riosa
            $ismssql = (strpos($this->databaseType,'mssql') !== false);
            if ($ismssql) {
                $isaccess = false;
            } else {
                $isaccess = (strpos($this->databaseType,'access') !== false);
            }

            if ($offset <= 0) {
                // access includes ties in result
                if ($isaccess) {
                    $sql = preg_replace(
                        '/(^\s*select\s+(distinctrow|distinct)?)/i',
                        '\\1 '.$this->hasTop.' '.$nrows.' ',
                        $sql
                    );

                    if ($secs2cache != 0) {
                        $ret = $this->CacheExecute($secs2cache, $sql,$inputarr);
                    } else {
                        $ret = $this->Execute($sql,$inputarr);
                    }
                    return $ret; // PHP5 fix
                } else if ($ismssql){
                    $sql = preg_replace(
                        '/(^\s*select\s+(distinctrow|distinct)?)/i',
                        '\\1 '.$this->hasTop.' '.$nrows.' ',
                        $sql
                    );
                } else {
                    $sql = preg_replace(
                        '/(^\s*select\s)/i',
                        '\\1 '.$this->hasTop.' '.$nrows.' ',
                        $sql
                    );
                }
            } else {
                $nn = $nrows + $offset;
                if ($isaccess || $ismssql) {
                    $sql = preg_replace(
                        '/(^\s*select\s+(distinctrow|distinct)?)/i',
                        '\\1 '.$this->hasTop.' '.$nn.' ',
                        $sql
                    );
                } else {
                    $sql = preg_replace(
                        '/(^\s*select\s)/i',
                        '\\1 '.$this->hasTop.' '.$nn.' ',
                        $sql
                    );
                }
            }
        }

        // if $offset>0, we want to skip rows, and $ADODB_COUNTRECS is set, we buffer  rows
        // 0 to offset-1 which will be discarded anyway. So we disable $ADODB_COUNTRECS.
        global $ADODB_COUNTRECS;

        $savec = $ADODB_COUNTRECS;
        $ADODB_COUNTRECS = false;


        if ($secs2cache != 0) {
            $rs = $this->CacheExecute($secs2cache,$sql,$inputarr);
        } else {
            $rs = $this->Execute($sql,$inputarr);
        }

        $ADODB_COUNTRECS = $savec;
        if ($rs && !$rs->EOF) {
            $rs = $this->_rs2rs($rs,$nrows,$offset);
        }
        //print_r($rs);
        return $rs;
    }

    /**
     * Create serializable recordset. Breaks rs link to connection.
     *
     * @param rs			the recordset to serialize
     */
    function SerializableRS(&$rs) {
        $rs2 = $this->_rs2rs($rs);
        $ignore = false;
        $rs2->connection = $ignore;

        return $rs2;
    }

    /**
     * Convert database recordset to an array recordset
     * input recordset's cursor should be at beginning, and
     * old $rs will be closed.
     *
     * @param rs			the recordset to copy
     * @param [nrows]	number of rows to retrieve (optional)
     * @param [offset]	offset by number of rows (optional)
     * @return			the new recordset
     */
    function &_rs2rs(&$rs,$nrows=-1,$offset=-1,$close=true) {
        if (! $rs) {
            return false;
        }
        $dbtype = $rs->databaseType;
        if (!$dbtype) {
            $rs = $rs;  // required to prevent crashing in 4.2.1, but does not happen in 4.3.1 -- why ?
            return $rs;
        }
        if (($dbtype == 'array' || $dbtype == 'csv') && $nrows == -1 && $offset == -1) {
            $rs->MoveFirst();
            $rs = $rs; // required to prevent crashing in 4.2.1, but does not happen in 4.3.1-- why ?
            return $rs;
        }
        $flds = array();
        for ($i=0, $max=$rs->FieldCount(); $i < $max; $i++) {
            $flds[] = $rs->FetchField($i);
        }

        $arr = $rs->GetArrayLimit($nrows,$offset);
        //print_r($arr);
        if ($close) {
            $rs->Close();
        }

        $arrayClass = $this->arrayClass;

        $rs2 = new $arrayClass();
        $rs2->connection = $this;
        $rs2->sql = $rs->sql;
        $rs2->dataProvider = $this->dataProvider;
        $rs2->InitArrayFields($arr,$flds);
        $rs2->fetchMode = isset($rs->adodbFetchMode) ? $rs->adodbFetchMode : $rs->fetchMode;
        return $rs2;
    }

    /*
    * Return all rows. Compat with PEAR DB
    */
    function GetAll($sql, $inputarr=false) {
        $arr = $this->GetArray($sql,$inputarr);
        return $arr;
    }

    /**
     * @param string $sql
     * @param false|array $inputarr
     * @param bool $force_array
     * @param bool $first2cols
     * @return false|array
     */
    public function GetAssoc($sql, $inputarr = false, $force_array = false, $first2cols = false) {
        global $ADODB_FETCH_MODE;

        $rs = $this->Execute($sql, $inputarr);

        if (!$rs) {
            /*
            * Execution failure
            */
            return false;
        }
        return $rs->GetAssoc($force_array, $first2cols);
    }

    /**
     * @param int $secs2cache
     * @param false|string $sql
     * @param false|array $inputarr
     * @param bool $force_array
     * @param bool $first2cols
     * @return false|array
     */
    public function CacheGetAssoc($secs2cache, $sql = false, $inputarr = false,$force_array = false, $first2cols = false) {
        if (!is_numeric($secs2cache)) {
            $first2cols = $force_array;
            $force_array = $inputarr;
        }
        $rs = $this->CacheExecute($secs2cache, $sql, $inputarr);
        if (!$rs) {
            return false;
        }
        return $rs->GetAssoc($force_array, $first2cols);
    }

    /**
     * Return first element of first row of sql statement. Recordset is disposed
     * for you.
     *
     * @param string		$sql		SQL statement
     * @param array|bool	$inputarr	input bind array
     * @return mixed
     */
    public function GetOne($sql, $inputarr=false) {
        global $ADODB_COUNTRECS,$ADODB_GETONE_EOF;

        $crecs = $ADODB_COUNTRECS;
        $ADODB_COUNTRECS = false;

        $ret = false;
        $rs = $this->Execute($sql,$inputarr);
        if ($rs) {
            if ($rs->EOF) {
                $ret = $ADODB_GETONE_EOF;
            } else {
                $ret = reset($rs->fields);
            }

            $rs->Close();
        }
        $ADODB_COUNTRECS = $crecs;
        return $ret;
    }

    // $where should include 'WHERE fld=value'
    function GetMedian($table, $field,$where = '') {
        $total = $this->GetOne("select count(*) from $table $where");
        if (!$total) {
            return false;
        }

        $midrow = (integer) ($total/2);
        $rs = $this->SelectLimit("select $field from $table $where order by 1",1,$midrow);
        if ($rs && !$rs->EOF) {
            return reset($rs->fields);
        }
        return false;
    }


    function CacheGetOne($secs2cache,$sql=false,$inputarr=false) {
        global $ADODB_GETONE_EOF;

        $ret = false;
        $rs = $this->CacheExecute($secs2cache,$sql,$inputarr);
        if ($rs) {
            if ($rs->EOF) {
                $ret = $ADODB_GETONE_EOF;
            } else {
                $ret = reset($rs->fields);
            }
            $rs->Close();
        }

        return $ret;
    }

    function GetCol($sql, $inputarr = false, $trim = false) {

        $rs = $this->Execute($sql, $inputarr);
        if ($rs) {
            $rv = array();
            if ($trim) {
                while (!$rs->EOF) {
                    $rv[] = trim(reset($rs->fields));
                    $rs->MoveNext();
                }
            } else {
                while (!$rs->EOF) {
                    $rv[] = reset($rs->fields);
                    $rs->MoveNext();
                }
            }
            $rs->Close();
        } else {
            $rv = false;
        }
        return $rv;
    }

    function CacheGetCol($secs, $sql = false, $inputarr = false,$trim=false) {
        $rs = $this->CacheExecute($secs, $sql, $inputarr);
        if ($rs) {
            $rv = array();
            if ($trim) {
                while (!$rs->EOF) {
                    $rv[] = trim(reset($rs->fields));
                    $rs->MoveNext();
                }
            } else {
                while (!$rs->EOF) {
                    $rv[] = reset($rs->fields);
                    $rs->MoveNext();
                }
            }
            $rs->Close();
        } else
            $rv = false;

        return $rv;
    }

    function Transpose(&$rs,$addfieldnames=true) {
        $rs2 = $this->_rs2rs($rs);
        if (!$rs2) {
            return false;
        }

        $rs2->_transpose($addfieldnames);
        return $rs2;
    }

    /*
        Calculate the offset of a date for a particular database and generate
            appropriate SQL. Useful for calculating future/past dates and storing
            in a database.

        If dayFraction=1.5 means 1.5 days from now, 1.0/24 for 1 hour.
    */
    function OffsetDate($dayFraction,$date=false) {
        if (!$date) {
            $date = $this->sysDate;
        }
        return  '('.$date.'+'.$dayFraction.')';
    }


    /**
     *
     * @param sql			SQL statement
     * @param [inputarr]		input bind array
     */
    function GetArray($sql,$inputarr=false) {
        global $ADODB_COUNTRECS;

        $savec = $ADODB_COUNTRECS;
        $ADODB_COUNTRECS = false;
        $rs = $this->Execute($sql,$inputarr);
        $ADODB_COUNTRECS = $savec;
        if (!$rs)
            if (defined('ADODB_PEAR')) {
                $cls = ADODB_PEAR_Error();
                return $cls;
            } else {
                return false;
            }
        $arr = $rs->GetArray();
        $rs->Close();
        return $arr;
    }

    function CacheGetAll($secs2cache,$sql=false,$inputarr=false) {
        $arr = $this->CacheGetArray($secs2cache,$sql,$inputarr);
        return $arr;
    }

    function CacheGetArray($secs2cache,$sql=false,$inputarr=false) {
        global $ADODB_COUNTRECS;

        $savec = $ADODB_COUNTRECS;
        $ADODB_COUNTRECS = false;
        $rs = $this->CacheExecute($secs2cache,$sql,$inputarr);
        $ADODB_COUNTRECS = $savec;

        if (!$rs)
            if (defined('ADODB_PEAR')) {
                $cls = ADODB_PEAR_Error();
                return $cls;
            } else {
                return false;
            }
        $arr = $rs->GetArray();
        $rs->Close();
        return $arr;
    }

    function GetRandRow($sql, $arr= false) {
        $rezarr = $this->GetAll($sql, $arr);
        $sz = sizeof($rezarr);
        return $rezarr[abs(rand()) % $sz];
    }

    /**
     * Return one row of sql statement. Recordset is disposed for you.
     * Note that SelectLimit should not be called.
     *
     * @param sql			SQL statement
     * @param [inputarr]		input bind array
     */
    function GetRow($sql,$inputarr=false) {
        global $ADODB_COUNTRECS;

        $crecs = $ADODB_COUNTRECS;
        $ADODB_COUNTRECS = false;

        $rs = $this->Execute($sql,$inputarr);

        $ADODB_COUNTRECS = $crecs;
        if ($rs) {
            if (!$rs->EOF) {
                $arr = $rs->fields;
            } else {
                $arr = array();
            }
            $rs->Close();
            return $arr;
        }

        return false;
    }

    function CacheGetRow($secs2cache,$sql=false,$inputarr=false) {
        $rs = $this->CacheExecute($secs2cache,$sql,$inputarr);
        if ($rs) {
            if (!$rs->EOF) {
                $arr = $rs->fields;
            } else {
                $arr = array();
            }

            $rs->Close();
            return $arr;
        }
        return false;
    }

    /**
     * Insert or replace a single record. Note: this is not the same as MySQL's replace.
     * ADOdb's Replace() uses update-insert semantics, not insert-delete-duplicates of MySQL.
     * Also note that no table locking is done currently, so it is possible that the
     * record be inserted twice by two programs...
     *
     * $this->Replace('products', array('prodname' =>"'Nails'","price" => 3.99), 'prodname');
     *
     * $table		table name
     * $fieldArray	associative array of data (you must quote strings yourself).
     * $keyCol		the primary key field name or if compound key, array of field names
     * autoQuote		set to true to use a hueristic to quote strings. Works with nulls and numbers
     *					but does not work with dates nor SQL functions.
     * has_autoinc	the primary key is an auto-inc field, so skip in insert.
     *
     * Currently blob replace not supported
     *
     * returns 0 = fail, 1 = update, 2 = insert
     */

    function Replace($table, $fieldArray, $keyCol, $autoQuote=false, $has_autoinc=false) {
        global $ADODB_INCLUDED_LIB;
        if (empty($ADODB_INCLUDED_LIB)) {
            include_once(ADODB_DIR.'/adodb-lib.inc.php');
        }

        return _adodb_replace($this, $table, $fieldArray, $keyCol, $autoQuote, $has_autoinc);
    }


    /**
     * Will select, getting rows from $offset (1-based), for $nrows.
     * This simulates the MySQL "select * from table limit $offset,$nrows" , and
     * the PostgreSQL "select * from table limit $nrows offset $offset". Note that
     * MySQL and PostgreSQL parameter ordering is the opposite of the other.
     * eg.
     *  CacheSelectLimit(15,'select * from table',3); will return rows 1 to 3 (1-based)
     *  CacheSelectLimit(15,'select * from table',3,2); will return rows 3 to 5 (1-based)
     *
     * BUG: Currently CacheSelectLimit fails with $sql with LIMIT or TOP clause already set
     *
     * @param [secs2cache]	seconds to cache data, set to 0 to force query. This is optional
     * @param sql
     * @param [offset]	is the row to start calculations from (1-based)
     * @param [nrows]	is the number of rows to get
     * @param [inputarr]	array of bind variables
     * @return		the recordset ($rs->databaseType == 'array')
     */
    function CacheSelectLimit($secs2cache,$sql,$nrows=-1,$offset=-1,$inputarr=false) {
        if (!is_numeric($secs2cache)) {
            if ($sql === false) {
                $sql = -1;
            }
            if ($offset == -1) {
                $offset = false;
            }
            // sql,	nrows, offset,inputarr
            $rs = $this->SelectLimit($secs2cache,$sql,$nrows,$offset,$this->cacheSecs);
        } else {
            if ($sql === false) {
                $this->outp_throw("Warning: \$sql missing from CacheSelectLimit()",'CacheSelectLimit');
            }
            $rs = $this->SelectLimit($sql,$nrows,$offset,$inputarr,$secs2cache);
        }
        return $rs;
    }

    /**
     * Flush cached recordsets that match a particular $sql statement.
     * If $sql == false, then we purge all files in the cache.
     */
    function CacheFlush($sql=false,$inputarr=false) {
        global $ADODB_CACHE_DIR, $ADODB_CACHE;

        # Create cache if it does not exist
        if (empty($ADODB_CACHE)) {
            $this->_CreateCache();
        }

        if (!$sql) {
            $ADODB_CACHE->flushall($this->debug);
            return;
        }

        $f = $this->_gencachename($sql.serialize($inputarr),false);
        return $ADODB_CACHE->flushcache($f, $this->debug);
    }


    /**
     * Private function to generate filename for caching.
     * Filename is generated based on:
     *
     *  - sql statement
     *  - database type (oci8, ibase, ifx, etc)
     *  - database name
     *  - userid
     *  - setFetchMode (adodb 4.23)
     *
     * When not in safe mode, we create 256 sub-directories in the cache directory ($ADODB_CACHE_DIR).
     * Assuming that we can have 50,000 files per directory with good performance,
     * then we can scale to 12.8 million unique cached recordsets. Wow!
     */
    function _gencachename($sql,$createdir) {
        global $ADODB_CACHE, $ADODB_CACHE_DIR;

        if ($this->fetchMode === false) {
            global $ADODB_FETCH_MODE;
            $mode = $ADODB_FETCH_MODE;
        } else {
            $mode = $this->fetchMode;
        }
        $m = md5($sql.$this->databaseType.$this->database.$this->user.$mode);
        if (!$ADODB_CACHE->createdir) {
            return $m;
        }
        if (!$createdir) {
            $dir = $ADODB_CACHE->getdirname($m);
        } else {
            $dir = $ADODB_CACHE->createdir($m, $this->debug);
        }

        return $dir.'/adodb_'.$m.'.cache';
    }


    /**
     * Execute SQL, caching recordsets.
     *
     * @param [secs2cache]	seconds to cache data, set to 0 to force query.
     *					  This is an optional parameter.
     * @param sql		SQL statement to execute
     * @param [inputarr]	holds the input data  to bind to
     * @return		RecordSet or false
     */
    function CacheExecute($secs2cache,$sql=false,$inputarr=false) {
        global $ADODB_CACHE;

        if (empty($ADODB_CACHE)) {
            $this->_CreateCache();
        }

        if (!is_numeric($secs2cache)) {
            $inputarr = $sql;
            $sql = $secs2cache;
            $secs2cache = $this->cacheSecs;
        }

        if (is_array($sql)) {
            $sqlparam = $sql;
            $sql = $sql[0];
        } else
            $sqlparam = $sql;


        $md5file = $this->_gencachename($sql.serialize($inputarr),true);
        $err = '';

        if ($secs2cache > 0){
            $rs = $ADODB_CACHE->readcache($md5file,$err,$secs2cache,$this->arrayClass);
            $this->numCacheHits += 1;
        } else {
            $err='Timeout 1';
            $rs = false;
            $this->numCacheMisses += 1;
        }

        if (!$rs) {
            // no cached rs found
            if ($this->debug) {
                if (get_magic_quotes_runtime() && !$this->memCache) {
                    ADOConnection::outp("Please disable magic_quotes_runtime - it corrupts cache files :(");
                }
                if ($this->debug !== -1) {
                    ADOConnection::outp( " $md5file cache failure: $err (this is a notice and not an error)");
                }
            }

            $rs = $this->Execute($sqlparam,$inputarr);

            if ($rs) {
                $eof = $rs->EOF;
                $rs = $this->_rs2rs($rs); // read entire recordset into memory immediately
                $rs->timeCreated = time(); // used by caching
                $txt = _rs2serialize($rs,false,$sql); // serialize

                $ok = $ADODB_CACHE->writecache($md5file,$txt,$this->debug, $secs2cache);
                if (!$ok) {
                    if ($ok === false) {
                        $em = 'Cache write error';
                        $en = -32000;

                        if ($fn = $this->raiseErrorFn) {
                            $fn($this->databaseType,'CacheExecute', $en, $em, $md5file,$sql,$this);
                        }
                    } else {
                        $em = 'Cache file locked warning';
                        $en = -32001;
                        // do not call error handling for just a warning
                    }

                    if ($this->debug) {
                        ADOConnection::outp( " ".$em);
                    }
                }
                if ($rs->EOF && !$eof) {
                    $rs->MoveFirst();
                    //$rs = csv2rs($md5file,$err);
                    $rs->connection = $this; // Pablo suggestion
                }

            } else if (!$this->memCache) {
                $ADODB_CACHE->flushcache($md5file);
            }
        } else {
            $this->_errorMsg = '';
            $this->_errorCode = 0;

            if ($this->fnCacheExecute) {
                $fn = $this->fnCacheExecute;
                $fn($this, $secs2cache, $sql, $inputarr);
            }
            // ok, set cached object found
            $rs->connection = $this; // Pablo suggestion
            if ($this->debug){
                if ($this->debug == 99) {
                    adodb_backtrace();
                }
                $inBrowser = isset($_SERVER['HTTP_USER_AGENT']);
                $ttl = $rs->timeCreated + $secs2cache - time();
                $s = is_array($sql) ? $sql[0] : $sql;
                if ($inBrowser) {
                    $s = '<i>'.htmlspecialchars($s).'</i>';
                }

                ADOConnection::outp( " $md5file reloaded, ttl=$ttl [ $s ]");
            }
        }
        return $rs;
    }


    /*
        Similar to PEAR DB's autoExecute(), except that
        $mode can be 'INSERT' or 'UPDATE' or DB_AUTOQUERY_INSERT or DB_AUTOQUERY_UPDATE
        If $mode == 'UPDATE', then $where is compulsory as a safety measure.

        $forceUpdate means that even if the data has not changed, perform update.
     */
    function AutoExecute($table, $fields_values, $mode = 'INSERT', $where = false, $forceUpdate = true, $magicq = false) {
        if (empty($fields_values)) {
            $this->outp_throw('AutoExecute: Empty fields array', 'AutoExecute');
            return false;
        }
        if ($where === false && ($mode == 'UPDATE' || $mode == 2 /* DB_AUTOQUERY_UPDATE */) ) {
            $this->outp_throw('AutoExecute: Illegal mode=UPDATE with empty WHERE clause', 'AutoExecute');
            return false;
        }

        $sql = "SELECT * FROM $table";
        $rs = $this->SelectLimit($sql, 1);
        if (!$rs) {
            return false; // table does not exist
        }

        $rs->tableName = $table;
        if ($where !== false) {
            $sql .= " WHERE $where";
        }
        $rs->sql = $sql;

        switch($mode) {
            case 'UPDATE':
            case DB_AUTOQUERY_UPDATE:
                $sql = $this->GetUpdateSQL($rs, $fields_values, $forceUpdate, $magicq);
                break;
            case 'INSERT':
            case DB_AUTOQUERY_INSERT:
                $sql = $this->GetInsertSQL($rs, $fields_values, $magicq);
                break;
            default:
                $this->outp_throw("AutoExecute: Unknown mode=$mode", 'AutoExecute');
                return false;
        }
        return $sql && $this->Execute($sql);
    }


    /**
     * Generates an Update Query based on an existing recordset.
     * $arrFields is an associative array of fields with the value
     * that should be assigned.
     *
     * Note: This function should only be used on a recordset
     *	   that is run against a single table and sql should only
     *		 be a simple select stmt with no groupby/orderby/limit
     *
     * "Jonathan Younger" <jyounger@unilab.com>
     */
    function GetUpdateSQL(&$rs, $arrFields,$forceUpdate=false,$magicq=false,$force=null) {
        global $ADODB_INCLUDED_LIB;

        // ********************************************************
        // This is here to maintain compatibility
        // with older adodb versions. Sets force type to force nulls if $forcenulls is set.
        if (!isset($force)) {
            global $ADODB_FORCE_TYPE;
            $force = $ADODB_FORCE_TYPE;
        }
        // ********************************************************

        if (empty($ADODB_INCLUDED_LIB)) {
            include_once(ADODB_DIR.'/adodb-lib.inc.php');
        }
        return _adodb_getupdatesql($this,$rs,$arrFields,$forceUpdate,$magicq,$force);
    }

    /**
     * Generates an Insert Query based on an existing recordset.
     * $arrFields is an associative array of fields with the value
     * that should be assigned.
     *
     * Note: This function should only be used on a recordset
     *       that is run against a single table.
     */
    function GetInsertSQL(&$rs, $arrFields,$magicq=false,$force=null) {
        global $ADODB_INCLUDED_LIB;
        if (!isset($force)) {
            global $ADODB_FORCE_TYPE;
            $force = $ADODB_FORCE_TYPE;
        }
        if (empty($ADODB_INCLUDED_LIB)) {
            include_once(ADODB_DIR.'/adodb-lib.inc.php');
        }
        return _adodb_getinsertsql($this,$rs,$arrFields,$magicq,$force);
    }


    /**
     * Update a blob column, given a where clause. There are more sophisticated
     * blob handling functions that we could have implemented, but all require
     * a very complex API. Instead we have chosen something that is extremely
     * simple to understand and use.
     *
     * Note: $blobtype supports 'BLOB' and 'CLOB', default is BLOB of course.
     *
     * Usage to update a $blobvalue which has a primary key blob_id=1 into a
     * field blobtable.blobcolumn:
     *
     *	UpdateBlob('blobtable', 'blobcolumn', $blobvalue, 'blob_id=1');
     *
     * Insert example:
     *
     *	$conn->Execute('INSERT INTO blobtable (id, blobcol) VALUES (1, null)');
     *	$conn->UpdateBlob('blobtable','blobcol',$blob,'id=1');
     */
    function UpdateBlob($table,$column,$val,$where,$blobtype='BLOB') {
        return $this->Execute("UPDATE $table SET $column=? WHERE $where",array($val)) != false;
    }

    /**
     * Usage:
     *	UpdateBlob('TABLE', 'COLUMN', '/path/to/file', 'ID=1');
     *
     *	$blobtype supports 'BLOB' and 'CLOB'
     *
     *	$conn->Execute('INSERT INTO blobtable (id, blobcol) VALUES (1, null)');
     *	$conn->UpdateBlob('blobtable','blobcol',$blobpath,'id=1');
     */
    function UpdateBlobFile($table,$column,$path,$where,$blobtype='BLOB') {
        $fd = fopen($path,'rb');
        if ($fd === false) {
            return false;
        }
        $val = fread($fd,filesize($path));
        fclose($fd);
        return $this->UpdateBlob($table,$column,$val,$where,$blobtype);
    }

    function BlobDecode($blob) {
        return $blob;
    }

    function BlobEncode($blob) {
        return $blob;
    }

    function GetCharSet() {
        return $this->charSet;
    }

    function SetCharSet($charset) {
        $this->charSet = $charset;
        return true;
    }

    function IfNull( $field, $ifNull ) {
        return " CASE WHEN $field is null THEN $ifNull ELSE $field END ";
    }

    function LogSQL($enable=true) {
        include_once(ADODB_DIR.'/adodb-perf.inc.php');

        if ($enable) {
            $this->fnExecute = 'adodb_log_sql';
        } else {
            $this->fnExecute = false;
        }

        $old = $this->_logsql;
        $this->_logsql = $enable;
        if ($enable && !$old) {
            $this->_affected = false;
        }
        return $old;
    }

    /**
     * Usage:
     *	UpdateClob('TABLE', 'COLUMN', $var, 'ID=1', 'CLOB');
     *
     *	$conn->Execute('INSERT INTO clobtable (id, clobcol) VALUES (1, null)');
     *	$conn->UpdateClob('clobtable','clobcol',$clob,'id=1');
     */
    function UpdateClob($table,$column,$val,$where) {
        return $this->UpdateBlob($table,$column,$val,$where,'CLOB');
    }

    // not the fastest implementation - quick and dirty - jlim
    // for best performance, use the actual $rs->MetaType().
    function MetaType($t,$len=-1,$fieldobj=false) {

        if (empty($this->_metars)) {
            $rsclass = $this->rsPrefix.$this->databaseType;
            $this->_metars = new $rsclass(false,$this->fetchMode);
            $this->_metars->connection = $this;
        }
        return $this->_metars->MetaType($t,$len,$fieldobj);
    }


    /**
     *  Change the SQL connection locale to a specified locale.
     *  This is used to get the date formats written depending on the client locale.
     */
    function SetDateLocale($locale = 'En') {
        $this->locale = $locale;
        switch (strtoupper($locale))
        {
            case 'EN':
                $this->fmtDate="'Y-m-d'";
                $this->fmtTimeStamp = "'Y-m-d H:i:s'";
                break;

            case 'US':
                $this->fmtDate = "'m-d-Y'";
                $this->fmtTimeStamp = "'m-d-Y H:i:s'";
                break;

            case 'PT_BR':
            case 'NL':
            case 'FR':
            case 'RO':
            case 'IT':
                $this->fmtDate="'d-m-Y'";
                $this->fmtTimeStamp = "'d-m-Y H:i:s'";
                break;

            case 'GE':
                $this->fmtDate="'d.m.Y'";
                $this->fmtTimeStamp = "'d.m.Y H:i:s'";
                break;

            default:
                $this->fmtDate="'Y-m-d'";
                $this->fmtTimeStamp = "'Y-m-d H:i:s'";
                break;
        }
    }

    /**
     * GetActiveRecordsClass Performs an 'ALL' query
     *
     * @param mixed $class This string represents the class of the current active record
     * @param mixed $table Table used by the active record object
     * @param mixed $whereOrderBy Where, order, by clauses
     * @param mixed $bindarr
     * @param mixed $primkeyArr
     * @param array $extra Query extras: limit, offset...
     * @param mixed $relations Associative array: table's foreign name, "hasMany", "belongsTo"
     * @access public
     * @return void
     */
    function GetActiveRecordsClass(
        $class, $table,$whereOrderBy=false,$bindarr=false, $primkeyArr=false,
        $extra=array(),
        $relations=array())
    {
        global $_ADODB_ACTIVE_DBS;
        ## reduce overhead of adodb.inc.php -- moved to adodb-active-record.inc.php
        ## if adodb-active-recordx is loaded -- should be no issue as they will probably use Find()
        if (!isset($_ADODB_ACTIVE_DBS)) {
            include_once(ADODB_DIR.'/adodb-active-record.inc.php');
        }
        return adodb_GetActiveRecordsClass($this, $class, $table, $whereOrderBy, $bindarr, $primkeyArr, $extra, $relations);
    }

    function GetActiveRecords($table,$where=false,$bindarr=false,$primkeyArr=false) {
        $arr = $this->GetActiveRecordsClass('ADODB_Active_Record', $table, $where, $bindarr, $primkeyArr);
        return $arr;
    }

    /**
     * Close Connection
     */
    function Close() {
        $rez = $this->_close();
        $this->_connectionID = false;
        return $rez;
    }

    /**
     * Begin a Transaction. Must be followed by CommitTrans() or RollbackTrans().
     *
     * @return true if succeeded or false if database does not support transactions
     */
    function BeginTrans() {
        if ($this->debug) {
            ADOConnection::outp("BeginTrans: Transactions not supported for this driver");
        }
        return false;
    }

    /* set transaction mode */
    function SetTransactionMode( $transaction_mode ) {
        $transaction_mode = $this->MetaTransaction($transaction_mode, $this->dataProvider);
        $this->_transmode  = $transaction_mode;
    }
    /*
    http://msdn2.microsoft.com/en-US/ms173763.aspx
    http://dev.mysql.com/doc/refman/5.0/en/innodb-transaction-isolation.html
    http://www.postgresql.org/docs/8.1/interactive/sql-set-transaction.html
    http://www.stanford.edu/dept/itss/docs/oracle/10g/server.101/b10759/statements_10005.htm
    */
    function MetaTransaction($mode,$db) {
        $mode = strtoupper($mode);
        $mode = str_replace('ISOLATION LEVEL ','',$mode);

        switch($mode) {

            case 'READ UNCOMMITTED':
                switch($db) {
                    case 'oci8':
                    case 'oracle':
                        return 'ISOLATION LEVEL READ COMMITTED';
                    default:
                        return 'ISOLATION LEVEL READ UNCOMMITTED';
                }
                break;

            case 'READ COMMITTED':
                return 'ISOLATION LEVEL READ COMMITTED';
                break;

            case 'REPEATABLE READ':
                switch($db) {
                    case 'oci8':
                    case 'oracle':
                        return 'ISOLATION LEVEL SERIALIZABLE';
                    default:
                        return 'ISOLATION LEVEL REPEATABLE READ';
                }
                break;

            case 'SERIALIZABLE':
                return 'ISOLATION LEVEL SERIALIZABLE';
                break;

            default:
                return $mode;
        }
    }

    /**
     * If database does not support transactions, always return true as data always commited
     *
     * @param $ok  set to false to rollback transaction, true to commit
     *
     * @return true/false.
     */
    function CommitTrans($ok=true) {
        return true;
    }


    /**
     * If database does not support transactions, rollbacks always fail, so return false
     *
     * @return true/false.
     */
    function RollbackTrans() {
        return false;
    }


    /**
     * return the databases that the driver can connect to.
     * Some databases will return an empty array.
     *
     * @return an array of database names.
     */
    function MetaDatabases() {
        global $ADODB_FETCH_MODE;

        if ($this->metaDatabasesSQL) {
            $save = $ADODB_FETCH_MODE;
            $ADODB_FETCH_MODE = ADODB_FETCH_NUM;

            if ($this->fetchMode !== false) {
                $savem = $this->SetFetchMode(false);
            }

            $arr = $this->GetCol($this->metaDatabasesSQL);
            if (isset($savem)) {
                $this->SetFetchMode($savem);
            }
            $ADODB_FETCH_MODE = $save;

            return $arr;
        }

        return false;
    }

    /**
     * List procedures or functions in an array.
     * @param procedureNamePattern  a procedure name pattern; must match the procedure name as it is stored in the database
     * @param catalog a catalog name; must match the catalog name as it is stored in the database;
     * @param schemaPattern a schema name pattern;
     *
     * @return array of procedures on current database.
     *
     * Array(
     *   [name_of_procedure] => Array(
     *     [type] => PROCEDURE or FUNCTION
     *     [catalog] => Catalog_name
     *     [schema] => Schema_name
     *     [remarks] => explanatory comment on the procedure
     *   )
     * )
     */
    function MetaProcedures($procedureNamePattern = null, $catalog  = null, $schemaPattern  = null) {
        return false;
    }


    /**
     * @param ttype can either be 'VIEW' or 'TABLE' or false.
     *		If false, both views and tables are returned.
     *		"VIEW" returns only views
     *		"TABLE" returns only tables
     * @param showSchema returns the schema/user with the table name, eg. USER.TABLE
     * @param mask  is the input mask - only supported by oci8 and postgresql
     *
     * @return  array of tables for current database.
     */
    function MetaTables($ttype=false,$showSchema=false,$mask=false) {
        global $ADODB_FETCH_MODE;

        if ($mask) {
            return false;
        }
        if ($this->metaTablesSQL) {
            $save = $ADODB_FETCH_MODE;
            $ADODB_FETCH_MODE = ADODB_FETCH_NUM;

            if ($this->fetchMode !== false) {
                $savem = $this->SetFetchMode(false);
            }

            $rs = $this->Execute($this->metaTablesSQL);
            if (isset($savem)) {
                $this->SetFetchMode($savem);
            }
            $ADODB_FETCH_MODE = $save;

            if ($rs === false) {
                return false;
            }
            $arr = $rs->GetArray();
            $arr2 = array();

            if ($hast = ($ttype && isset($arr[0][1]))) {
                $showt = strncmp($ttype,'T',1);
            }

            for ($i=0; $i < sizeof($arr); $i++) {
                if ($hast) {
                    if ($showt == 0) {
                        if (strncmp($arr[$i][1],'T',1) == 0) {
                            $arr2[] = trim($arr[$i][0]);
                        }
                    } else {
                        if (strncmp($arr[$i][1],'V',1) == 0) {
                            $arr2[] = trim($arr[$i][0]);
                        }
                    }
                } else
                    $arr2[] = trim($arr[$i][0]);
            }
            $rs->Close();
            return $arr2;
        }
        return false;
    }


    function _findschema(&$table,&$schema) {
        if (!$schema && ($at = strpos($table,'.')) !== false) {
            $schema = substr($table,0,$at);
            $table = substr($table,$at+1);
        }
    }

    /**
     * List columns in a database as an array of ADOFieldObjects.
     * See top of file for definition of object.
     *
     * @param $table	table name to query
     * @param $normalize	makes table name case-insensitive (required by some databases)
     * @schema is optional database schema to use - not supported by all databases.
     *
     * @return  array of ADOFieldObjects for current table.
     */
    function MetaColumns($table,$normalize=true) {
        global $ADODB_FETCH_MODE;

        if (!empty($this->metaColumnsSQL)) {
            $schema = false;
            $this->_findschema($table,$schema);

            $save = $ADODB_FETCH_MODE;
            $ADODB_FETCH_MODE = ADODB_FETCH_NUM;
            if ($this->fetchMode !== false) {
                $savem = $this->SetFetchMode(false);
            }
            $rs = $this->Execute(sprintf($this->metaColumnsSQL,($normalize)?strtoupper($table):$table));
            if (isset($savem)) {
                $this->SetFetchMode($savem);
            }
            $ADODB_FETCH_MODE = $save;
            if ($rs === false || $rs->EOF) {
                return false;
            }

            $retarr = array();
            while (!$rs->EOF) { //print_r($rs->fields);
                $fld = new ADOFieldObject();
                $fld->name = $rs->fields[0];
                $fld->type = $rs->fields[1];
                if (isset($rs->fields[3]) && $rs->fields[3]) {
                    if ($rs->fields[3]>0) {
                        $fld->max_length = $rs->fields[3];
                    }
                    $fld->scale = $rs->fields[4];
                    if ($fld->scale>0) {
                        $fld->max_length += 1;
                    }
                } else {
                    $fld->max_length = $rs->fields[2];
                }

                if ($ADODB_FETCH_MODE == ADODB_FETCH_NUM) {
                    $retarr[] = $fld;
                } else {
                    $retarr[strtoupper($fld->name)] = $fld;
                }
                $rs->MoveNext();
            }
            $rs->Close();
            return $retarr;
        }
        return false;
    }

    /**
     * List indexes on a table as an array.
     * @param table  table name to query
     * @param primary true to only show primary keys. Not actually used for most databases
     *
     * @return array of indexes on current table. Each element represents an index, and is itself an associative array.
     *
     * Array(
     *   [name_of_index] => Array(
     *     [unique] => true or false
     *     [columns] => Array(
     *       [0] => firstname
     *       [1] => lastname
     *     )
     *   )
     * )
     */
    function MetaIndexes($table, $primary = false, $owner = false) {
        return false;
    }

    /**
     * List columns names in a table as an array.
     * @param table	table name to query
     *
     * @return  array of column names for current table.
     */
    function MetaColumnNames($table, $numIndexes=false,$useattnum=false /* only for postgres */) {
        $objarr = $this->MetaColumns($table);
        if (!is_array($objarr)) {
            return false;
        }
        $arr = array();
        if ($numIndexes) {
            $i = 0;
            if ($useattnum) {
                foreach($objarr as $v)
                    $arr[$v->attnum] = $v->name;

            } else
                foreach($objarr as $v) $arr[$i++] = $v->name;
        } else
            foreach($objarr as $v) $arr[strtoupper($v->name)] = $v->name;

        return $arr;
    }

    /**
     * Different SQL databases used different methods to combine strings together.
     * This function provides a wrapper.
     *
     * param s	variable number of string parameters
     *
     * Usage: $db->Concat($str1,$str2);
     *
     * @return concatenated string
     */
    function Concat() {
        $arr = func_get_args();
        return implode($this->concat_operator, $arr);
    }


    /**
     * Converts a date "d" to a string that the database can understand.
     *
     * @param d	a date in Unix date time format.
     *
     * @return  date string in database date format
     */
    function DBDate($d, $isfld=false) {
        if (empty($d) && $d !== 0) {
            return 'null';
        }
        if ($isfld) {
            return $d;
        }
        if (is_object($d)) {
            return $d->format($this->fmtDate);
        }

        if (is_string($d) && !is_numeric($d)) {
            if ($d === 'null') {
                return $d;
            }
            if (strncmp($d,"'",1) === 0) {
                $d = _adodb_safedateq($d);
                return $d;
            }
            if ($this->isoDates) {
                return "'$d'";
            }
            $d = ADOConnection::UnixDate($d);
        }

        return adodb_date($this->fmtDate,$d);
    }

    function BindDate($d) {
        $d = $this->DBDate($d);
        if (strncmp($d,"'",1)) {
            return $d;
        }

        return substr($d,1,strlen($d)-2);
    }

    function BindTimeStamp($d) {
        $d = $this->DBTimeStamp($d);
        if (strncmp($d,"'",1)) {
            return $d;
        }

        return substr($d,1,strlen($d)-2);
    }


    /**
     * Converts a timestamp "ts" to a string that the database can understand.
     *
     * @param ts	a timestamp in Unix date time format.
     *
     * @return  timestamp string in database timestamp format
     */
    function DBTimeStamp($ts,$isfld=false) {
        if (empty($ts) && $ts !== 0) {
            return 'null';
        }
        if ($isfld) {
            return $ts;
        }
        if (is_object($ts)) {
            return $ts->format($this->fmtTimeStamp);
        }

        # strlen(14) allows YYYYMMDDHHMMSS format
        if (!is_string($ts) || (is_numeric($ts) && strlen($ts)<14)) {
            return adodb_date($this->fmtTimeStamp,$ts);
        }

        if ($ts === 'null') {
            return $ts;
        }
        if ($this->isoDates && strlen($ts) !== 14) {
            $ts = _adodb_safedate($ts);
            return "'$ts'";
        }
        $ts = ADOConnection::UnixTimeStamp($ts);
        return adodb_date($this->fmtTimeStamp,$ts);
    }

    /**
     * Also in ADORecordSet.
     * @param $v is a date string in YYYY-MM-DD format
     *
     * @return date in unix timestamp format, or 0 if before TIMESTAMP_FIRST_YEAR, or false if invalid date format
     */
    static function UnixDate($v) {
        if (is_object($v)) {
            // odbtp support
            //( [year] => 2004 [month] => 9 [day] => 4 [hour] => 12 [minute] => 44 [second] => 8 [fraction] => 0 )
            return adodb_mktime($v->hour,$v->minute,$v->second,$v->month,$v->day, $v->year);
        }

        if (is_numeric($v) && strlen($v) !== 8) {
            return $v;
        }
        if (!preg_match( "|^([0-9]{4})[-/\.]?([0-9]{1,2})[-/\.]?([0-9]{1,2})|", $v, $rr)) {
            return false;
        }

        if ($rr[1] <= TIMESTAMP_FIRST_YEAR) {
            return 0;
        }

        // h-m-s-MM-DD-YY
        return @adodb_mktime(0,0,0,$rr[2],$rr[3],$rr[1]);
    }


    /**
     * Also in ADORecordSet.
     * @param $v is a timestamp string in YYYY-MM-DD HH-NN-SS format
     *
     * @return date in unix timestamp format, or 0 if before TIMESTAMP_FIRST_YEAR, or false if invalid date format
     */
    static function UnixTimeStamp($v) {
        if (is_object($v)) {
            // odbtp support
            //( [year] => 2004 [month] => 9 [day] => 4 [hour] => 12 [minute] => 44 [second] => 8 [fraction] => 0 )
            return adodb_mktime($v->hour,$v->minute,$v->second,$v->month,$v->day, $v->year);
        }

        if (!preg_match(
            "|^([0-9]{4})[-/\.]?([0-9]{1,2})[-/\.]?([0-9]{1,2})[ ,-]*(([0-9]{1,2}):?([0-9]{1,2}):?([0-9\.]{1,4}))?|",
            ($v), $rr)) return false;

        if ($rr[1] <= TIMESTAMP_FIRST_YEAR && $rr[2]<= 1) {
            return 0;
        }

        // h-m-s-MM-DD-YY
        if (!isset($rr[5])) {
            return  adodb_mktime(0,0,0,$rr[2],$rr[3],$rr[1]);
        }
        return @adodb_mktime($rr[5],$rr[6],$rr[7],$rr[2],$rr[3],$rr[1]);
    }

    /**
     * Also in ADORecordSet.
     *
     * Format database date based on user defined format.
     *
     * @param v		is the character date in YYYY-MM-DD format, returned by database
     * @param fmt	is the format to apply to it, using date()
     *
     * @return a date formated as user desires
     */
    function UserDate($v,$fmt='Y-m-d',$gmt=false) {
        $tt = $this->UnixDate($v);

        // $tt == -1 if pre TIMESTAMP_FIRST_YEAR
        if (($tt === false || $tt == -1) && $v != false) {
            return $v;
        } else if ($tt == 0) {
            return $this->emptyDate;
        } else if ($tt == -1) {
            // pre-TIMESTAMP_FIRST_YEAR
        }

        return ($gmt) ? adodb_gmdate($fmt,$tt) : adodb_date($fmt,$tt);

    }

    /**
     *
     * @param v		is the character timestamp in YYYY-MM-DD hh:mm:ss format
     * @param fmt	is the format to apply to it, using date()
     *
     * @return a timestamp formated as user desires
     */
    function UserTimeStamp($v,$fmt='Y-m-d H:i:s',$gmt=false) {
        if (!isset($v)) {
            return $this->emptyTimeStamp;
        }
        # strlen(14) allows YYYYMMDDHHMMSS format
        if (is_numeric($v) && strlen($v)<14) {
            return ($gmt) ? adodb_gmdate($fmt,$v) : adodb_date($fmt,$v);
        }
        $tt = $this->UnixTimeStamp($v);
        // $tt == -1 if pre TIMESTAMP_FIRST_YEAR
        if (($tt === false || $tt == -1) && $v != false) {
            return $v;
        }
        if ($tt == 0) {
            return $this->emptyTimeStamp;
        }
        return ($gmt) ? adodb_gmdate($fmt,$tt) : adodb_date($fmt,$tt);
    }

    function escape($s,$magic_quotes=false) {
        return $this->addq($s,$magic_quotes);
    }

    /**
     * Quotes a string, without prefixing nor appending quotes.
     */
    function addq($s,$magic_quotes=false) {
        if (!$magic_quotes) {
            if ($this->replaceQuote[0] == '\\') {
                // only since php 4.0.5
                $s = adodb_str_replace(array('\\',"\0"),array('\\\\',"\\\0"),$s);
                //$s = str_replace("\0","\\\0", str_replace('\\','\\\\',$s));
            }
            return  str_replace("'",$this->replaceQuote,$s);
        }

        // undo magic quotes for "
        $s = str_replace('\\"','"',$s);

        if ($this->replaceQuote == "\\'" || ini_get('magic_quotes_sybase')) {
            // ' already quoted, no need to change anything
            return $s;
        } else {
            // change \' to '' for sybase/mssql
            $s = str_replace('\\\\','\\',$s);
            return str_replace("\\'",$this->replaceQuote,$s);
        }
    }

    /**
     * Correctly quotes a string so that all strings are escaped. We prefix and append
     * to the string single-quotes.
     * An example is  $db->qstr("Don't bother",magic_quotes_runtime());
     *
     * @param string $s			the string to quote
     * @param [magic_quotes]	if $s is GET/POST var, set to get_magic_quotes_gpc().
     *				This undoes the stupidity of magic quotes for GPC.
     *
     * @return string           quoted string to be sent back to database
     */
    function qstr($s,$magic_quotes=false) {
        if (!$magic_quotes) {
            if ($this->replaceQuote[0] == '\\'){
                // only since php 4.0.5
                $s = adodb_str_replace(array('\\',"\0"),array('\\\\',"\\\0"),$s);
                //$s = str_replace("\0","\\\0", str_replace('\\','\\\\',$s));
            }
            return  "'".str_replace("'",$this->replaceQuote,$s)."'";
        }

        // undo magic quotes for "
        $s = str_replace('\\"','"',$s);

        if ($this->replaceQuote == "\\'" || ini_get('magic_quotes_sybase')) {
            // ' already quoted, no need to change anything
            return "'$s'";
        } else {
            // change \' to '' for sybase/mssql
            $s = str_replace('\\\\','\\',$s);
            return "'".str_replace("\\'",$this->replaceQuote,$s)."'";
        }
    }


    /**
     * Will select the supplied $page number from a recordset, given that it is paginated in pages of
     * $nrows rows per page. It also saves two boolean values saying if the given page is the first
     * and/or last one of the recordset. Added by Iván Oliva to provide recordset pagination.
     *
     * See docs-adodb.htm#ex8 for an example of usage.
     *
     * @param sql
     * @param nrows		is the number of rows per page to get
     * @param page		is the page number to get (1-based)
     * @param [inputarr]	array of bind variables
     * @param [secs2cache]		is a private parameter only used by jlim
     * @return		the recordset ($rs->databaseType == 'array')
     *
     * NOTE: phpLens uses a different algorithm and does not use PageExecute().
     *
     */
    function PageExecute($sql, $nrows, $page, $inputarr=false, $secs2cache=0) {
        global $ADODB_INCLUDED_LIB;
        if (empty($ADODB_INCLUDED_LIB)) {
            include_once(ADODB_DIR.'/adodb-lib.inc.php');
        }
        if ($this->pageExecuteCountRows) {
            $rs = _adodb_pageexecute_all_rows($this, $sql, $nrows, $page, $inputarr, $secs2cache);
        } else {
            $rs = _adodb_pageexecute_no_last_page($this, $sql, $nrows, $page, $inputarr, $secs2cache);
        }
        return $rs;
    }


    /**
     * Will select the supplied $page number from a recordset, given that it is paginated in pages of
     * $nrows rows per page. It also saves two boolean values saying if the given page is the first
     * and/or last one of the recordset. Added by Iván Oliva to provide recordset pagination.
     *
     * @param secs2cache	seconds to cache data, set to 0 to force query
     * @param sql
     * @param nrows		is the number of rows per page to get
     * @param page		is the page number to get (1-based)
     * @param [inputarr]	array of bind variables
     * @return		the recordset ($rs->databaseType == 'array')
     */
    function CachePageExecute($secs2cache, $sql, $nrows, $page,$inputarr=false) {
        /*switch($this->dataProvider) {
        case 'postgres':
        case 'mysql':
            break;
        default: $secs2cache = 0; break;
        }*/
        $rs = $this->PageExecute($sql,$nrows,$page,$inputarr,$secs2cache);
        return $rs;
    }

    /**
     * Returns the maximum size of a MetaType C field. If the method
     * is not defined in the driver returns ADODB_STRINGMAX_NOTSET
     *
     * @return int
     */
    function charMax()
    {
        return ADODB_STRINGMAX_NOTSET;
    }

    /**
     * Returns the maximum size of a MetaType X field. If the method
     * is not defined in the driver returns ADODB_STRINGMAX_NOTSET
     *
     * @return int
     */
    function textMax()
    {
        return ADODB_STRINGMAX_NOTSET;
    }

    /**
     * Returns a substring of a varchar type field
     *
     * Some databases have variations of the parameters, which is why
     * we have an ADOdb function for it
     *
     * @param	string	$fld	The field to sub-string
     * @param	int		$start	The start point
     * @param	int		$length	An optional length
     *
     * @return	The SQL text
     */
    function substr($fld,$start,$length=0) {
        $text = "{$this->substr}($fld,$start";
        if ($length > 0)
            $text .= ",$length";
        $text .= ')';
        return $text;
    }

    /*
     * Formats the date into Month only format MM with leading zeroes
     *
     * @param	string		$fld	The name of the date to format
     *
     * @return	string				The SQL text
     */
    function month($fld) {
        $x = $this->sqlDate('m',$fld);

        return $x;
    }

    /*
     * Formats the date into Day only format DD with leading zeroes
     *
     * @param	string		$fld	The name of the date to format
     * @return	string		The SQL text
     */
    function day($fld) {
        $x = $this->sqlDate('d',$fld);
        return $x;
    }

    /*
     * Formats the date into year only format YYYY
     *
     * @param	string		$fld The name of the date to format
     *
     * @return	string		The SQL text
     */
    function year($fld) {
        $x = $this->sqlDate('Y',$fld);
        return $x;
    }
}