<?php
use time\StopWatch;

//Database object
global $db2;

class Query {
	private $db;
	private $sql;

	/**
	 * @var PDOStatement
	 */
	private $stmt;

	private $binds;
	private $resultClass;
	private $tablesClassMap;
	private $debug = false;

	public $id = 0;

	public function __construct($db, $sql) {
		$this->db = $db;
		$this->sql = $sql;
		$this->binds = array();
		$this->rowCount = 0;

		$this->id = Counter::getNextId("queryId");
	}

	/**
	 * @return PDOStatement
	 */
	public function getStmt() {
		return $this->stmt;
	}

	public function getSql() {
		return $this->sql;
	}

	function bind() {
		$args = func_get_args();
		if (count($args) > 0) {
			if (is_array($args[0])) {
				$this->binds = $args[0];
			} else if (count($args) == 2) {
				// key => value
				$this->binds[$args[0]] = $args[1];
			}
		}
	}

	function bindList($key, $list){
		if ($list == null || !is_array($list) || count($list) == 0) {
			return;
		}
		$i = 0;
		$keys = array();
		foreach($list as $val) {
			$key_i = $key."".$i;
			$this->binds[$key_i] = $val;
			$keys[] = ":".$key_i;
			$i++;
		}
		$keysStr = implode(", ", $keys);
		$this->sql = str_replace("(:{$key})", "($keysStr)", $this->sql);
	}

	function setTablesClassMap($tablesClassMap) {
		foreach ($tablesClassMap as $key => $value) {
			if (!class_exists($value, true)) {
				throw new \Exception("Class $value does not exist", 1, null);
			}
		}
		$this->tablesClassMap = $tablesClassMap;
	}

	function resultClass($className) {
		$this->resultClass = $className;
	}

	function debug() {
		$this->debug = true;
	}

	function prepare($start, $end) {
		if ($start == -1) {
			if (array_key_exists("start", $this->binds)) {
				$start = $this->binds["start"];
			}
		}
		if ($end == -1) {
			if (array_key_exists("end", $this->binds)) {
				$end = $this->binds["end"];
			}
		}
		if ($start!=-1 && $end!=-1) {
			$this->sql .= " LIMIT :start, :end";
		}

		// echo "start: $start<br />";
		// echo "end: $end<br />";
		//echo "SQL: {$this->sql}<br />";

		$this->stmt = $this->db->prepare($this->sql);

		if ($start!=-1 && $end!=-1) {
			$this->stmt->bindParam(':start', $start, \PDO::PARAM_INT);
			$this->stmt->bindParam(':end', $end, \PDO::PARAM_INT);
			unset($this->binds["start"]);
			unset($this->binds["end"]);
		}

		if ($this->debug) {
			echo "binds: ";
			dump($this->binds);
		}

		if (count($this->binds) > 0) {
			foreach($this->binds as $key=>$val) {
				$this->stmt->bindValue(":$key", $val);
			}
		}

		if ($this->tablesClassMap) {
			$this->stmt->setFetchMode(\PDO::FETCH_NUM);
		} else if ($this->resultClass) {
			$this->stmt->setFetchMode(\PDO::FETCH_CLASS, $this->resultClass);
		} else {
			$this->stmt->setFetchMode(\PDO::FETCH_ASSOC);
		}
	}

	function execute() {
		return $this->getList();
	}

	function getList($start = -1, $end = -1) {
		$stopWatch = new StopWatch();
		$stopWatch->start();
		\Logger::debug("getList($start, $end)", ["queryId"=>$this->id, "sql"=>$this->getSql()]);
		if ($this->tablesClassMap) {
			$result = $this->getTablesClassMapList($start, $end);
			$stopWatch->stop();
			\Logger::debug("getList duration with class map: " . $stopWatch->getTime() . " ms", ["queryId"=>$this->id]);

			return $result;
		}

		$this->prepare($start, $end);

		/*
		if (count($this->binds) > 0) {
			$this->stmt->execute($this->binds);
		} else {
			$this->stmt->execute();
		}
		*/

		if ($this->debug) {
			dump($this->stmt);
			echo "<pre>";
			$this->stmt->debugDumpParams();
		}
		// try {
			$this->stmt->execute();
		// } catch (Exception $e) {
		// 	echo "ERRR: $e<br />";
		// 	dump($this->stmt->errorInfo());
		// }
		if ($this->debug) {
			echo "</pre>";
		}

		//dump($this->stmt->debugDumpParams());

		if ($this->stmt->rowCount() > 0) {
			if ($this->stmt->columnCount() > 0) {
				$rows = array();
				$this->rowCount = $this->stmt->rowCount();
				if ($this->rowCount > 1000) {
					if (\Logger::isWarnEnabled()) {
						\Logger::warn("row count {$this->rowCount} > 1000", ["queryId"=>$this->id, "sql"=>$this->getSql()]);
					}
					return $this;
				}
				while($row = $this->stmt->fetch()) {
					$rows[] = $row;
				}
				$stopWatch->stop();
				\Logger::debug("getList duration: " . $stopWatch->getTime() . " ms", ["queryId"=>$this->id, "sql"=>$this->getSql()]);
				return $rows;
			} else {
				$this->lastInsertId = $this->db->lastInsertId();
			}
		}
		$this->stmt = null;
	}

	public function fetch() {
		return $this->stmt->fetch();
	}

	/**
	 * <p>Class ResultClass {
			Link link1;
			Link link2;
			Party party;
		}


		Class UtilA {
			method getBLABLABLA {
				select l1.*, l2.*, p.*
				from Link as l1, Link as l2, Party p
				where p.link1 = l1.id
					and p.link2 = l2.id

				res - [
					{l1: linkObj, l2: linkObj, p: partyObj},
					{l1: linkObj, l2: linkObj, p: partyObj},
					{l1: linkObj, l2: linkObj, p: partyObj},
					{l1: linkObj, l2: linkObj, p: partyObj},
				]

				newRes = []
				foreach (res) {
					newRes[i] = new ResultClass()
					newRes[i]->link1 = res->l1;
					newRes[i]->link2 = res->l2;
					newRes[i]->party = res->p;
				}
				return newRes
			}
		}

		list = UtilA::getBLABLABLA();
		list->
		</p>
	 * @param unknown $start
	 * @param unknown $end
	 * @return multitype:multitype:mixed
	 */
	function getTablesClassMapList($start = -1, $end = -1) {
		$this->prepare($start, $end);
		$this->stmt->setFetchMode(\PDO::FETCH_NUM);
		$this->stmt->execute();

		$colCnt = $this->stmt->columnCount();
		$columns = array();
		for ($i=0; $i < $colCnt; $i++) {
			$column = $this->stmt->getColumnMeta($i);
			$columns[] = $column;
		}

		if ($this->stmt->rowCount() > 0) {
			if ($this->stmt->columnCount() > 0) {
				$rows = array();
				while($row = $this->stmt->fetch()) {
					$tableValues = array();

					foreach ($row as $colNumber => $val) {
						$column = $columns[$colNumber];
						$table = $column["table"];
						$columnName = $column["name"];
						if (!isset($tableValues[$table])) {
							$tableValues[$table] = new \stdClass();
						}
						$tableValues[$table]->{$columnName} = $val;
					}

					$objects = array();
					foreach ($this->tablesClassMap as $tableAlias => $className) {
						$object = $this->getMappedObjects($row, $tableValues, $tableAlias);
						$objects[$tableAlias] = $object;
					}

					$rows[] = $objects;
				}
				return $rows;
			}
		}
	}

	function getMappedObjects($row, $tableValues, $tableAlias) {


		$table = $tableAlias;
		$className = $this->tablesClassMap[$table];
		//$object = new $className;

		$object = unserialize(
				sprintf('O:%d:"%s":0:{}', strlen($className), $className)
		);

		$class_of_object= get_class($object);
		//echo "-- table: $table, $class_of_object\n";
		$class_keys = get_class_vars($class_of_object);

		$tableVal = $tableValues[$table];
		$obj = new \ReflectionObject($tableVal);

		foreach ($class_keys as $class_key => $v) {
			if ($obj->hasProperty($class_key)) {
				$val = $tableVal->{$class_key};
				$object->{$class_key} = $val;
			}
		}

		$method = null;
		try {
			$method = new \ReflectionMethod($className, '__construct');
		} catch (\Exception $e) {
			//echo "constructor __construct does not exist\n";
		}
		try {
			$method = new \ReflectionMethod($className, "$className");
		} catch (\Exception $e) {
			//echo "constructor $className does not exist\n";
		}
		if ($method != null) {
			$method->invoke($object);
		}

		return $object;
	}

	function getCount() {
		try {
			$res = $this->execute();
			$keys = array_keys($res[0]);
			return $res[0][$keys[0]];
		} catch (\Exception $e) {
			echo "selectCount error\n".$e->getMessage()."\n";
			echo "SQL: $this->sql<br />";
			return 0;
		};
	}

	function __destruct() {
		$this->stmt = null;
	}
}

class DataBase2 {

		private static $propertyFileName = "db.ini";
		private static $instance;

		var $mysql_usr;
		var $mysql_pwd;
		var $mysql_host;
		var $db;
		var $conn;

		public static function getInstance() {
			if (!self::$instance) {
				Properties::read(self::$propertyFileName, "db");
				$dbhost = Properties::get("db.host");
				$dbuser = Properties::get("db.user");
				$dbpassword = Properties::get("db.password");
				$dbscheme = Properties::get("db.scheme");
				self::$instance = new DataBase2($dbhost, $dbuser, $dbpassword, $dbscheme);
			}
			return self::$instance;
		}

		function createQuery($sql) {
			return  new Query($this->db, $sql);
		}

		function dump() {
				print "ora_usr: ".$this->mysql_usr."\n";
				print "ora_pwd: ".$this->mysql_pwd."\n";
				print "ora_sid: ".$this->mysql_host."\n";
				print "connect: ".$this->conn."\n";
		}

		public function __construct($host, $user, $password, $dbname) {
				//$mysql_usr_ = strtoupper ($mysql_usr_);
				//$mysql_pwd_ = strtoupper ($mysql_pwd_);
				//$mysql_host_ = strtoupper ($mysql_host_);
			try {
				//$this->mysql_usr=$user;
				//$this->mysql_pwd=$password;
				//$this->mysql_host=$host;
				//echo "mysql:dbname=$dbname;host=$host;charset=UTF-8"."<br />";
				$dbh = new \PDO("mysql:dbname=$dbname;host=$host;charset=utf8", $user, $password);
				//$dbh = new PDO("mysql:unix_socket=/tmp/mysql.sock;dbname=$dbname;charset=UTF-8", $user, $password, array(PDO::ATTR_PERSISTENT => true)); // using persistent connections
				$dbh->setAttribute(\PDO::ATTR_PERSISTENT, true);
				$dbh->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);
				$dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

				$dbh->exec("set names utf8");
				$this->db=$dbh;
			} catch(\PDOException $e) {
				echo $e->getMessage();
			}
		}

		//function SelectRecords( $sql, &$rows, $start=-1, $end=-1) {
		function query( $sql, &$rows, $start=-1, $end=-1) {
			return $this->_query($sql, null, null, $rows, $start, $end);
		}

		function parametrizedQuery( $sql, $binds, &$rows, $start=-1, $end=-1) {
			return $this->_query($sql, $binds, null, $rows, $start, $end);
		}

		function queryClass( $sql, $className, &$rows, $start=-1, $end=-1) {
			return $this->_query($sql, null, $className, $rows, $start, $end);
		}

		function parametrizedQueryClass( $sql, $binds, $className, &$rows, $start=-1, $end=-1) {
			return $this->_query($sql, $binds, $className, $rows, $start, $end);
		}

		function _query( $sql, $binds, $className, &$rows, $start, $end) {
			$stopWatch = new StopWatch();
			$stopWatch->start();

			$query = $this->createQuery($sql);
			if ($binds != null && is_array($binds) && count($binds) > 0) {
				$query->bind($binds);
			}
			if ($className != null) {
				$query->resultClass($className);
			}
			$rows = $query->getList($start, $end);

			$stopWatch->stop();
			\Logger::debug("query duration: " . $stopWatch->getTime() . " ms", ["queryId"=> $query->id, "sql"=>$query->getSql()]);

			return $query->rowCount;
		}

		function getCount($sql) {
			return $this->getParametrizedCount($sql, null);
		}

		function getParametrizedCount($sql, $binds) {
			$stopWatch = new StopWatch();
			$stopWatch->start();

			$query = $this->createQuery($sql);
			if ($binds != null && is_array($binds) && count($binds) > 0) {
				$query->bind($binds);
			}
			$count = $query->getCount();
			$stopWatch->stop();
			\Logger::debug("getParametrizedCount duration: " . $stopWatch->getTime() . " ms", ["queryId"=>$query->id, "sql"=>$query->getSql()]);

			return $count;
		}

		function __destruct() {
			$this->db = null;
		}
}


//$g_db =  new DataBase2("localhost", "far", "", "seregap");

/*
$sql = "SELECT ID, NAME, code FROM tables ORDER BY id";
$rc=$db2->query($sql, $res);

$cnt = $db2->getCount($sql);

$sql = "update `test` set name = :name";
$query = $db2->createQuery($sql);
$query->bind(array("name"=>"uuuuuuuu"));
$res = $query->execute();


$sql = "select * from  `test`";
$query = $db2->createQuery($sql);
$query->resultClass("RutorCategories");
$res = $query->getList();

for ($i=0; $i<$rc; $i++)
{
    echo $res[$i]['ID']." ".$res[$i]['NAME']."<br>";
}
*/

?>
