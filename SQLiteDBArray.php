<?php

/***
 * A simple, in memory, indexed array implementation in PHP using SQLite DB.
 */
class SQLiteDBArray extends SQLite3 implements ArrayAccess, Iterator, Countable {
		public static $INTEGER = 'INTEGER';
		public static $REAL = 'REAL';
		public static $TEXT = 'TEXT';
		var $id_field_name = '';
		var $position = 0;
		var $table_name = 'data_table';
		var $table_keys = array();
		var $ids = array();

		function __construct($table_syntax) {

			/*** $table_syntax
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
			 * Allowed data types
			INTEGER. The value is a signed integer, stored in 1, 2, 3, 4, 6, or 8 bytes depending on the magnitude of the value.
			REAL. The value is a floating point value, stored as an 8-byte IEEE floating point number.
			TEXT. The value is a text string, stored using the database encoding (UTF-8, UTF-16BE or UTF-16LE).
			 *
			 */

			$this->open(':memory:');
			$this->id_field_name = 'id_'.time();
			$this->table_keys = array_keys($table_syntax);
			$ret = $this->exec($this->array_syntax_to_sql_syntax($this->id_field_name, $table_syntax));

			if(!$ret) {
				throw new Exception($this->lastErrorMsg());
			}
		}

		function __destruct() {
			$this->close();
		}

		private function array_syntax_to_sql_syntax($id_field, $array_syntax) {
			$create_sql = 'CREATE TABLE data_table (';
			$create_sql .= $id_field . ' INTEGER PRIMARY KEY AUTOINCREMENT, ';
			foreach($array_syntax as $key => $part) {
				$create_sql .= $key . ' ' . $part . ',';
			}
			return substr($create_sql, 0, -1) . ");";
		}

		function offsetSet($offset, $value) {
			if(isset($offset)) {
				$offset++; // sqlite autoincrement starts at 1
			}
			$sql = '';
			$key_string = '';
			$value_string = '';
			foreach ($this->table_keys as $key) {
				if(isset($value[$key])) {
					$key_string .= $key . ',';
					$value_string .= '\'' . $value[$key] . '\',';
				}
			}
			if (is_null($offset)) {
				$sql = 'INSERT INTO ' . $this->table_name . ' (' . substr($key_string, 0, -1) . ') VALUES (' . substr($value_string, 0, -1) . ');';
			} else {
				$sql = 'INSERT OR REPLACE INTO ' . $this->table_name . ' (' . $this->id_field_name . ',' . substr($key_string, 0, -1) . ') VALUES (' . $offset . ',' . substr($value_string, 0, -1) . ');';
			}

			$ret = $this->exec($sql);
			if(!$ret){
				throw new Exception($this->lastErrorMsg());
			}

			$this->getIds();
		}

		private function getIds() {
			$sql = 'SELECT ' . $this->id_field_name . ' from ' . $this->table_name . ';';
			$ret = $this->query($sql);
			$ids = array();
			while($row = $ret->fetchArray(SQLITE3_ASSOC) ){
				$ids[] = $row[$this->id_field_name];
			}
			$this->ids = $ids;
		}

		function offsetExists($offset) {
			$offset++;
			$sql = 'SELECT * from ' . $this->table_name . ' WHERE ' . $this->id_field_name . ' = ' . $offset . ';';
			$ret = $this->query($sql);
			$row = $ret->fetchArray(SQLITE3_ASSOC);
			return isset($row);
		}

		function offsetUnset($offset) {
			$offset++;
			$sql = 'DELETE from ' . $this->table_name . ' WHERE ' . $this->id_field_name . ' = ' . $offset . ';';
			$ret = $this->query($sql);
			$row = $ret->fetchArray(SQLITE3_ASSOC);
			return isset($row);
		}

		function offsetGet($offset) {
			$offset++;
			return $this->getElementAtId($offset);
		}

		private function getElementAtId($id) {
			$sql = 'SELECT * from ' . $this->table_name . ' WHERE ' . $this->id_field_name . ' = ' . $id . ';';
			$ret = $this->query($sql);
			$row = $ret->fetchArray(SQLITE3_ASSOC);
			unset($row[$this->id_field_name]);
			return $row;
		}

		function rewind() {
			$this->position = 0;
		}

		function current() {
			return $this->getElementAtId($this->ids[$this->position]);
		}

		function key() {
			return $this->ids[$this->position];
		}

		function next() {
			$this->ids[++$this->position];
		}

		function valid() {
			return $this->getElementAtId($this->ids[$this->position]);
		}

		function count() {
			return count($this->ids);
		}
	}

	/***
	 * Example use case
	 */
	$db = new SQLiteDBArray(array('name' => SQLiteDBArray::$TEXT, 'age' => SQLiteDBArray::$INTEGER,
		'address' => SQLiteDBArray::$TEXT, 'salary' => SQLiteDBArray::$REAL));

	$test = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 12, 15, 20);
	foreach($test as $t) {
		$db[$t] = array('name' => 'testName' . $t);
		var_dump(count($db));
	}

	foreach($test as $t) {
		var_dump($db[$t]);
	}
?>
