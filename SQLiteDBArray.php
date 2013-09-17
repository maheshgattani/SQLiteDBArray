<?php

/***
 * A simple, in memory, indexed array implementation in PHP using SQLite DB.
 */
class SQLiteDBArray extends SQLite3 implements ArrayAccess, Iterator, Countable {
		public static $INTEGER = 'INTEGER';
		public static $REAL = 'REAL';
		public static $TEXT = 'TEXT';
		var $idFieldName = '';
		var $position = 0;
		var $tableName = 'data_table';
		var $tableKeys = array();
		var $ids = array();

		function __construct($tableSyntax) {

			/*** $tableSyntax
			 * array(
			 *       'name' => SQLiteDBArray::$TEXT, 'age' => SQLiteDBArray::$INTEGER,
			 *       'address' => SQLiteDBArray::$TEXT, 'salary' => SQLiteDBArray::$REAL
			 * )
			 *
			 * will create a table the same as
			 *
			 * CREATE TABLE COMPANY
			(NAME           TEXT,
			AGE            INT,
			ADDRESS        TEXT,
			SALARY         REAL)
			 *
			 * An additional primary key will be added automatically which will act as index and will auto increment.
			 *
			 * Allowed data types
			INTEGER. The value is a signed integer, stored in 1, 2, 3, 4, 6, or 8 bytes depending on the magnitude of the value.
			REAL. The value is a floating point value, stored as an 8-byte IEEE floating point number.
			TEXT. The value is a text string, stored using the database encoding (UTF-8, UTF-16BE or UTF-16LE).
			 *
			 */

			$this->open(':memory:');
			$this->idFieldName = 'id_'.time();
			$this->tableKeys = array_keys($tableSyntax);
			$ret = $this->exec($this->arraySyntaxToSqlSyntax($this->idFieldName, $tableSyntax));

			if(!$ret) {
				throw new Exception($this->lastErrorMsg());
			}
		}

		function __destruct() {
			$this->close();
		}

		private function arraySyntaxToSqlSyntax($id_field, $array_syntax) {
			$createSql = 'CREATE TABLE ' . $this->tableName . ' (';
			$createSql .= $id_field . ' INTEGER PRIMARY KEY AUTOINCREMENT, ';
			foreach($array_syntax as $key => $part) {
				$createSql .= $key . ' ' . $part . ',';
			}
			return substr($createSql, 0, -1) . ");";
		}

		function offsetSet($offset, $value) {
			if(isset($offset)) {
				$offset++; // sqlite autoincrement starts at 1
			}
			$sql = '';
			$keyString = '';
			$valueString = '';
			foreach ($this->tableKeys as $key) {
				if(isset($value[$key])) {
					$keyString .= $key . ',';
					$valueString .= '\'' . $value[$key] . '\',';
				}
			}
			if (is_null($offset)) {
				$sql = 'INSERT INTO ' . $this->tableName . ' (' . substr($keyString, 0, -1) . ') VALUES (' . substr($valueString, 0, -1) . ');';
			} else {
				$sql = 'INSERT OR REPLACE INTO ' . $this->tableName . ' (' . $this->idFieldName . ',' . substr($keyString, 0, -1) . ') VALUES (' . $offset . ',' . substr($valueString, 0, -1) . ');';
			}

			$ret = $this->exec($sql);
			if(!$ret){
				throw new Exception($this->lastErrorMsg());
			}

			$this->getIds();
		}

		private function getIds() {
			$sql = 'SELECT ' . $this->idFieldName . ' from ' . $this->tableName . ';';
			$ret = $this->query($sql);
			if(!$ret){
				throw new Exception($this->lastErrorMsg());
			}
			$ids = array();
			while($row = $ret->fetchArray(SQLITE3_ASSOC) ){
				$ids[] = $row[$this->idFieldName];
			}
			$this->ids = $ids;
		}

		function offsetExists($offset) {
			$offset++;
			$sql = 'SELECT * from ' . $this->tableName . ' WHERE ' . $this->idFieldName . ' = ' . $offset . ';';
			$ret = $this->query($sql);
			$row = $ret->fetchArray(SQLITE3_ASSOC);
			if(!$ret){
				throw new Exception($this->lastErrorMsg());
			}
			return isset($row);
		}

		function offsetUnset($offset) {
			$offset++;
			$sql = 'DELETE from ' . $this->tableName . ' WHERE ' . $this->idFieldName . ' = ' . $offset . ';';
			$ret = $this->query($sql);
			if(!$ret){
				throw new Exception($this->lastErrorMsg());
			}
		}

		function offsetGet($offset) {
			$offset++;
			return $this->getElementAtId($offset);
		}

		private function getElementAtId($id) {
			$sql = 'SELECT * from ' . $this->tableName . ' WHERE ' . $this->idFieldName . ' = ' . $id . ';';
			$ret = $this->query($sql);
			if(!$ret){
				throw new Exception($this->lastErrorMsg());
			}
			$row = $ret->fetchArray(SQLITE3_ASSOC);
			unset($row[$this->idFieldName]);
			return $row;
		}

		function rewind() {
			$this->position = 0;
		}

		function current() {
			return $this->getElementAtId($this->ids[$this->position]);
		}

		function key() {
			return $this->ids[$this->position] - 1;
		}

		function next() {
			++$this->position;
		}

		function valid() {
			return isset($this->ids[$this->position]);
		}

		function count() {
			return count($this->ids);
		}

		/***
		 * @param $query SQL query. Must match sql standards.
		 * @return array containing the output of the query
		 * @throws Exception if the query fails
		 */
		function executeDbQuery($query) {
			$ret = $this->query($query);
			if(!$ret){
				throw new Exception($this->lastErrorMsg());
			}
			$output = array();
			while($row = $ret->fetchArray(SQLITE3_ASSOC) ){
				$output[] = $row;
			}
			return $output;
		}
	}

	/***
	 * Example use case
	 */
	$db = new SQLiteDBArray(array('name' => SQLiteDBArray::$TEXT, 'age' => SQLiteDBArray::$INTEGER,
		'address' => SQLiteDBArray::$TEXT, 'salary' => SQLiteDBArray::$REAL));

	// Example writes
	$test = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 12, 15, 20);
	foreach($test as $t) {
		$db[$t] = array('name' => 'testName' . $t);
	}

	// Examples of count
	var_dump(count($db));

	// Example reads using for
	for($i = 0; $i <= 20; $i++) {
		var_dump($db[$i]);
	}

	// Example reads using foreach
	foreach($db as $key => $value) {
		var_dump($key);
		var_dump($value);
	}

	// Example unset
	var_dump($db[1]);
	unset($db[1]);
	var_dump($db[1]);
?>
