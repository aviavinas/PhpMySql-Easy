<?php
/*
 * Author  Avinash Kumar  < aviavinash.official@gmail.com >
 * Copyright 2018 StanBuzz.Com
 *
 *
 * This class handles all Communication the
 * of Php to Mysql Database and makes error log
 */

// - -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- --

class Database {
		// Public Info
		# Database
		public $database ="my_db";
		# Optional Tables
		public $example_table = "mytable";

		// Private data
		private $serverhost = "localhost";
		private $username = "root";
		private $password = "";
		private $con;
		private $mysqli;
		public $database_selected;
		public $failed_query_count = 0;
		public $error_msg = array();

		public function __construct() {
			 $this->connect();
		}

    public function connect() {
        if(!$this->con) {
						$this->mysqli = @new MySQLi($this->serverhost,$this->username,$this->password);

		        if($this->mysqli && $this->ping()) {
							$this->con = true;
							if(@$this->runQuery("USE ".$this->database))
							    $this->database_selected = true;
							else
							    $this->database_selected = false;

							return true;
						} else {
							$this->con = false;
							return false;
						}
				}
				else {
						$this->con = true;
			      return true;
		    }
    }

    public function disconnect() {
				if($this->con) {
						$mysqli = $this->mysqli;
						if(@$mysqli->close()) {
						   $this->con = false;
					 		 return true;
						}
						else {
							 return false;
						}
				}
		}

		public function ping() {
				$mysqli = $this->mysqli;
				return @$mysqli->ping();
		}

		public function error() {
				$mysqli = $this->mysqli;
				return $mysqli->error;
		}

		private function tableExists($table) {
				$table = $this->esc_str($table);
	      $tablesInDb = @$this->runQuery('SHOW TABLES FROM '.$this->database.' LIKE "'.$table.'"');
	      if($tablesInDb) {
	          if($tablesInDb->num_rows==1)
	              return true;
	          else {
								$this->failed_query_count++;
								$this->error_msg[] = "Error : '".$table."' table doesn't exist in Database '".$this->database."' !";
		            return false;
						}
	      }
    }

    public function select($table, $rows = '*', $where = null, $order = null, $limit = null) {
			  if($this->tableExists($table)) {
					  $result = array();
					  $q = 'SELECT '.$rows.' FROM '.$table;
					  $debug = false;
					  if($limit==(-121)) { // secret Debugging Mode No.
							  $debug = true;
							  $limit = null;
					  }
					  if($where != null)
						  	$q .= ' WHERE '.$where;
					  if($order != null)
						  	$q .= ' ORDER BY '.$order;
					  if(($limit != null) && is_int($limit))
						  	$q .= ' LIMIT '.$limit;
					  $query = @$this->runQuery($q);
					  if($debug) var_dump($q);
					  if($query) {
							  $numResults = $query->num_rows;
							  for($i = 0; $i < $numResults; $i++) {
									  $r = $query->fetch_array(MYSQLI_ASSOC);
									  $key = array_keys($r);
									  for($x = 0; $x < count($key); $x++) {
											  if($query->num_rows > 1)
												  	$result[$i][$key[$x]] = $r[$key[$x]];
											  else if($query->num_rows < 1)
												  	$result = null;
											  else
												  	$result[$key[$x]] = $r[$key[$x]];
									  }
							  }
							  return $result;
					  }
					  else
						  	return false;
			  }
			  else
				  	return false;
    }

		public function exist($table, $col, $val) {
				$res = $this->select($table,"*",$col."='".$val."'");
				if(count($res)<1) {
						return false;
				} else {
						return count($res);
				}
		}

    public function insert($table,$values,$rows = null, $debug = 0) {
        if($this->tableExists($table)) {
            $insert = 'INSERT INTO '.$table;
            if($rows != null)
                $insert .= ' ('.$rows.')';

            for($i = 0; $i < count($values); $i++) {
								if(empty($values[$i]))
										$values[$i] = "NULL";
				        if(is_string($values[$i]))
				        		$values[$i] = '"'.$this->esc_str($values[$i]).'"';
            }
            $values = implode(',',$values);
            $insert .= ' VALUES ('.$values.')';
		   			if($debug==1)
					   		echo $insert;

						$ins = @$this->runQuery($insert);
						if($ins)
			      		return true;
			      else
								return false;
        }
				else
						return false;
    }

    public function delete($table,$where = null) {
        if($this->tableExists($table)) {
						if($where == null)
		            $delete = 'DELETE FROM '.$table;
		        else
		            $delete = 'DELETE FROM '.$table.' WHERE '.$where;

            $del = @$this->runQuery($delete);

            if($del)
                return true;
            else
                return false;
        }
        else
            return false;
    }

    public function update($table,$rows,$where,$debugging=0) {
        if($this->tableExists($table)) {
				    for($i = 0; $i < count($where); $i++) {
		            if($i%2 != 0) {
										if(@$where[$i+1] != NULL)
												$where[$i] = '"'.$where[$i].'" AND ';
										else
												$where[$i] = '"'.$where[$i].'"';
		        		}
		        }
		        $where = implode('=',$where);
		        $update = 'UPDATE '.$table.' SET ';
		        $keys = array_keys($rows);
		        for($i = 0; $i < count($rows); $i++) {
		            if(is_string($rows[$keys[$i]]))
		                $update .= $keys[$i].'="'.$this->esc_str($rows[$keys[$i]]).'"';
		            else
		                $update .= $keys[$i].'='.$this->esc_str($rows[$keys[$i]]);

		            // Parse to add commas
		            if($i != count($rows)-1)
		                $update .= ',';
		        }

				    $update .= ' WHERE '.$where;
						if($debugging==1)
								echo $update;
		        $query = @$this->runQuery($update);
		        if($query)
		            return true;
		        else
		            return false;
        }
        else
            return false;
    }

		public function esc_str($str) {
				if(!empty($str)) {
						$mysqli = $this->mysqli;
						return $mysqli->real_escape_string($str);
				}
		}

		public function safe($str) {
				return $this->esc_str($str);
		}

		public function escAll($list) { //Recursively Escape String to safe database Query
				if(!is_array($list) && !is_object($list)) {
						return $this->esc_str($list);
				}
				foreach ($list as $listName => $listVal) {
						if(is_object($list)) {
								$list->$listName = $this->escAll($listVal);
						} elseif (is_array($list)) {
								$list[$listName] = $this->escAll($listVal);
						}
				}
				return $list;
		}

		public function disable_auto_commit() {
				$mysqli = $this->mysqli;
				return $mysqli->autocommit(FALSE);
		}

		public function commit_transiction() {
				$mysqli = $this->mysqli;
				return $mysqli->commit();
		}

		public function rollback_transiction() {
				$mysqli = $this->mysqli;
				return $mysqli->rollback();
		}

		public function runQuery($sql_query) {
				if(empty($sql_query))
						exit(0);

				$mysqli = $this->mysqli;
				$mysqli->set_charset('utf8');
				$result = $mysqli->query($sql_query);
				if($result == false) {
						$this->failed_query_count++;
						$this->error_msg[] = $mysqli->error;
				}
				return $result;
		}
}

// Let's Initiate Database Library
$_db = new Database();

?>
