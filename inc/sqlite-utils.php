<?php
/**
 * SQLite utility
 *  
 * Description: SQlite3 custom class
 * 
 * Version: 1.2.0 
 * Author: enomoto@celtislab
 * Author URI: https://celtislab.net/
 * License: GPLv2
 * 
 */

namespace celtislab\v1_2;

class Celtis_sqlite {
    
    private $db;
    private $inTransaction = false;
    private $maxretry = 3;

    //Specify the path of the database file in $db_path (general extensions are .db and .sqlite)
    //$db_path can also be used to create an in-memory database :memory: or a private & temporary database with an empty string specified.
    public function __construct($db_path) {
        try {
            if (!class_exists('\SQLite3')) {
                throw new \Exception('SQLite3 class not exsist');
            }
            
            $this->db = new \SQLite3( $db_path );
            if(!empty($this->db)){
                //new SQLite3() Re-check when instantiation does not pass through error
                //false if a file other than SQLite3 is specified
                $this->db->enableExceptions(true);
                $retry = $this->maxretry;
                while ($retry > 0) {       
                    try {
                        $result = $this->db->querySingle("SELECT name FROM sqlite_master WHERE type='table' LIMIT 1");
                        break;
                    } catch (\Exception $e) {
                        if(! $this->is_retry($retry)){
                            $result = false;
                            break;
                        } else {
                            usleep(100000); //wait 100ms
                        }
                    }            
                }        
                if ($result === false) {
                    $this->close();
                    throw new \Exception('Invalid SQLite database');
                }         
            }
        } catch (\Exception $e) {
            return false;
        }     
    }

    public function is_open() {
        return (!empty($this->db))? true : false;
    }

    public function close() {
        if ($this->is_open()) {
            try {        
                $this->db->close();
                $this->db = null;
                return true;
            } catch (\Exception $e) {
                return false;
            }             
        }
    }
    
    /**
     * Confirm the presence of the table
     * 
     * @return boolean
     */
    public function is_table_exist($table) {
        //Get the already created table structure from sqlite_master
        $result = $this->sql_get_row("SELECT * FROM sqlite_master WHERE type = 'table' AND name = ?", array( $table ), \SQLITE3_ASSOC );
        return $result ? true : false;
    }

    /**
     * Execute PRAGMA command that returns no results
     * @param string $cmd
     * @return true/false
     */
    public function command($cmd) {
        try {        
            return $this->db->exec($cmd);

        } catch (\Exception $e) {
            if ($this->inTransaction()) {
                throw new \Exception('Transaction in progress');
            } else {            
                return false;
            }
        }        
    }
    //Execute PRAGMA command that returns result
    public function get_command($cmd) {
        try {        
            $result = $this->db->query($cmd);

            $row = $result->fetchArray(\SQLITE3_NUM);                  
            if(isset($row[0])){
                return $row[0];
            } else {
                return false;
            }
        } catch (\Exception $e) {
            if ($this->inTransaction()) {
                throw new \Exception('Transaction in progress');
            } else {            
                return false;
            }
        }        
    }    

    //sql prepared query (parameter bind value)
    private function prepare_sql($sql, $params = array()) {
        $stmt = $this->db->prepare($sql);
        $n = 1;
        foreach ($params as $value) {
            if (is_int($value)) {
                $stmt->bindValue($n, $value, \SQLITE3_INTEGER);
            } elseif (is_float($value)) {
                $stmt->bindValue($n, $value, \SQLITE3_FLOAT);
            } elseif (is_bool($value)) {
                $stmt->bindValue($n, $value, \SQLITE3_INTEGER);
            } elseif (is_null($value)) {
                $stmt->bindValue($n, null, \SQLITE3_NULL);
            } elseif (is_string($value) && ! preg_match('/\x00/', $value)) {
                //Strings are automatically escaped by bindValue
                $stmt->bindValue($n, $value, \SQLITE3_TEXT);
            } else {
                //PDO 使用時は text 型に BLOB データを保存出来ていたが、sqlite3 モジュールではヌル文字までになる不具合あり
                //BLOB 型データとして明示的に指定する                
                $stmt->bindValue($n, $value, \SQLITE3_BLOB);
            }
            $n++;
        }        
        $result = $stmt->execute();
        return $result;
    }

    //Retry judgment when the database is locked
    //'PRAGMA busy_timeout' presets msec until database is no longer locked or timeout is reached
    private function is_retry(&$count) {
        if($count > 0 ){       
            $count--;         
            $sqlite_err = $this->db->lastErrorCode();
            //https://sqlite.org/rescode.html 
            //SQLITE_BUSY(5), SQLITE_LOCKED(6) For some reason it is undefined, so check it directly with the value
            if ($sqlite_err === 5 || $sqlite_err === 6) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
        
    /**
     * Execute sql prepared query that returns no results
     * @param string $sql    eg. "INSERT INTO table (id, title, author, date) VALUES ( ?, ?, ?, ?)"
     * @param array  $params eg array($id, $title, $author, $now->format('Y-m-d H:i:s')
     * @return true/false
     */   
    public function sql_exec($sql, $params = array()) {
        $retry = $this->maxretry;
        while ($retry > 0) {              
            try {
                if(empty($params)){
                    return $this->db->exec($sql);                
                } else {
                    return $this->prepare_sql($sql, $params);
                }
            } catch (\Exception $e) {
                if(! $this->is_retry($retry)){
                    if ($this->inTransaction()) {
                        throw new \Exception('Transaction in progress');
                    } else {
                        return false;                        
                    }                    
                }
            }
        }
        return false;  
    }

    /**
     * sql prepared query that return data variable acquisition
     * @param string $sql
     * @return t
     */
    public function sql_get_var($sql, $col_num=0, $params = array()) {
        $retry = $this->maxretry;
        while ($retry > 0) {       
            try {
                if(empty($params)){
                    $result = $this->db->query($sql);           
                } else {
                    $result = $this->prepare_sql($sql, $params);
                }
                $row = $result->fetchArray(\SQLITE3_NUM);                  
                if(isset($row[$col_num])){
                    return $row[$col_num];
                } else {
                    return false;
                }

            } catch (\Exception $e) {
                if(! $this->is_retry($retry)){
                    if ($this->inTransaction()) {
                        throw new \Exception('Transaction in progress');
                    } else {
                        return false;                        
                    }
                }
            }            
        }
        return false;  
    }
    
    /**
     * sql prepared query that returns single row
     * @param string $sql      eg. "INSERT INTO table (id, title, author, date) VALUES ( ?, ?, ?, ?)"
     * @param array  $ar_param eg array($id, $title, $author, $now->format('Y-m-d H:i:s')
     * @param $output format 0:object, 1(SQLITE3_ASSOC):array[name], 2(SQLITE3_NUM):array[number], 3(SQLITE3_BOTH):both array[name] and array[number] 
     * @return 
     */    
    public function sql_get_row($sql, $params = array(), $outputFormat = 0) {
        $retry = $this->maxretry;
        while ($retry > 0) {   
            try {        
                if(empty($params)){
                    $result = $this->db->query($sql);                
                } else {            
                    $result = $this->prepare_sql($sql, $params);
                }
                if (empty($outputFormat)) {
                    $row = $result->fetchArray(\SQLITE3_ASSOC);
                    return (empty($row))? false : (object) $row;                
                } else {
                    $row = $result->fetchArray($outputFormat);
                    return $row;
                }            
            } catch (\Exception $e) {
                if(! $this->is_retry($retry)){
                    if ($this->inTransaction()) {
                        throw new \Exception('Transaction in progress');
                    } else {
                        return false;                        
                    }
                }
            }
        }
        return false;     
    }    

    /**
     * sql prepared query that returns all row
     * @param string $sql      eg. "INSERT INTO table (id, title, author, date) VALUES ( ?, ?, ?, ?)"
     * @param array  $ar_param eg array($id, $title, $author, $now->format('Y-m-d H:i:s')
     * @param $output format 0:object, 1(SQLITE3_ASSOC):array[name], 2(SQLITE3_NUM):array[number], 3(SQLITE3_BOTH):both array[name] and array[number] 
     * @return 
     */   
    public function sql_get_results($sql, $params = array(), $outputFormat = 0) {
        $retry = $this->maxretry;
        while ($retry > 0) {  
            try {
                if(empty($params)){
                    $result = $this->db->query($sql);                
                } else {
                    $result = $this->prepare_sql($sql, $params);
                }
                $rows = array();
                if (empty($outputFormat)) {
                    //There is no method equivalent to PDO's fechAll, so be careful as performance will deteriorate unless processing inside the loop is kept to a minimum.
                    while ($row = $result->fetchArray(\SQLITE3_ASSOC)) {
                        $rows[] = (object) $row;
                    }
                } else {
                    while ($row = $result->fetchArray($outputFormat)) {
                        $rows[] = $row;
                    }
                }             
                return $rows;

            } catch (\Exception $e) {
                if(! $this->is_retry($retry)){
                    if ($this->inTransaction()) {
                        throw new \Exception('Transaction in progress');
                    } else {
                        return false;                        
                    }
                }
            }
        }
        return false;        
    }
    
    //Transaction related definitions for compatibility with pdo
    //IMMEDIATE/EXCLUSIVE/DEFERRED https://www.sqlite.org/lang_transaction.html
    public function beginTransaction( $type = 'DEFERRED' ) {
        if(!in_array($type, array('DEFFERED', 'IMMEDIATE', 'EXCLUSIVE'))){
            $type = 'DEFERRED';            
        }
        $retry = $this->maxretry;
        while ($retry > 0) {          
            try {        
                $this->db->exec('BEGIN ' . $type );
                $this->inTransaction = true;
                return true;
            } catch (\Exception $e) {
                if(! $this->is_retry($retry)){
                    return false;
                }
            }
        }
        return false;        
    }
    
    public function commit() {
        try {        
            $this->db->exec('COMMIT');
            $this->inTransaction = false;
            return true;
        } catch (\Exception $e) {
            return false;
        }           
    }

    public function rollback() {
        try {        
            $this->db->exec('ROLLBACK');
            $this->inTransaction = false;
            return true;
        } catch (\Exception $e) {
            return false;
        }           
    }

    public function inTransaction() {
        return $this->inTransaction;
    }
}    
