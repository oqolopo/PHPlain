<?php

/**
PHPlain PHP Framework
Copyright 2011-2012 Alexander Angelov, oqolopo (at) gmail (dot) com

Permission is hereby granted, free of charge, to any person
obtaining a copy of this software and associated documentation
files (the "Software"), to deal in the Software without
restriction, including without limitation the rights to use,
copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following
conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
OTHER DEALINGS IN THE SOFTWARE.
*/

class PHPlainQuery implements Iterator {

	private
		$_key		= 0,
		$_valid		= FALSE;

	protected
		$_db		= NULL,
		$_statement	= NULL,
		$_succeeded	= FALSE,
		$_affected	= 0,
		$_result	= FALSE,
		$_fetch		= FALSE,
		$_modified	= FALSE,
		$_buffer	= array(),
		$_params	= array(),
		$_details	= array(),
		$_master	= NULL,
		$_select	= '*',
		$_where		= '',
		$_orderBy	= '',
		$_reverse	= '',
		$_source	= NULL,
		$_relation	= array(
			'queries' => array(),
			'columns' => array(),
			'autoinc' => array(),
			'details' => array()
		),
		$_page		= 1,
		$_pageSize	= 0;

	private function _collectColumns($statement = NULL) {
		if (!($statement || $statement = $this->_statement)) {
			$sql = PHPlainUtil::prepare($this->select, $this->_select, "2 < 1");
			$statement = $this->_prepare($sql);
			$statement->setFetchMode(PDO::FETCH_INTO, $this);
			$statement->execute();
		}
		if ($statement)
			for ($i = 0; $meta = $statement->getColumnMeta($i++);)
				$this->setColumn($meta);
	}

	private function _build($source, $select = "*", $autoinc = NULL) {
		$verb = PHPlainUtil::verb($source);
		$rel = $verb != PHPlainConfig::PLACEHOLDER_SELECT;
		if ($rel) $sql = "SELECT * FROM $source WHERE 2 < 1";
		else $sql = PHPlainUtil::prepare($source, $select, "2 < 1");
		$this->_collectColumns($this->_db->sql($sql));
		$into = array();
		$values = array();
		$set = array();
		$where = array();
		$autoinc = $this->_autoinc($autoinc);
		foreach ($this->_relation['columns'] as $meta) {
			$name = $meta['name'];
			if (!($autoinc && array_key_exists($name, $autoinc))) {
				$into[] = $name;
				$set[] = "$name = :$name";
			}
			if (in_array('primary_key', $meta['flags']))
				$where[] = "$name = :__$name";
		}
		if ($rel) {
			$this->_relation['queries']['select'] = str_replace(PHPlainConfig::PLACEHOLDER_FROM, "FROM $source", PHPlainConfig::SELECT_TEMPLATE);
			$this->_relation['queries']['insert'] = "INSERT INTO $source ("
				. implode(', ', $into) . ') VALUES (:' . implode(', :', $into) . ')';
			$this->_relation['queries']['update'] = "UPDATE $source SET "
				. implode(', ', $set) . ' WHERE ' . implode(' AND ', $where);
			$this->_relation['queries']['delete'] = "DELETE FROM $source WHERE "
				. implode(' AND ', $where);
		} else $this->_relation['queries']['select'] = $source;
		if (isset($this->_db->pagination['count'])) $this->_relation['queries']['count'] = "SELECT " . $this->_db->pagination['count'];
		else $this->_relation['queries']['count'] = PHPlainUtil::prepare($this->_relation['queries']['select'], "COUNT(*)", PHPlainConfig::PLACEHOLDER_WHERE);
	}

	private function _bindParam($statement, $name, $value, $pdo_type = PDO::PARAM_STR) {
		if (is_array($value)) {
			if (isset($value['value'])) $this->_params[$name] = $value['value'];
			if (isset($value['pdo_type'])) $pdo_type = $value['pdo_type'];
		} else $this->_params[$name] = $value;
		if ($pdo_type == PDO::PARAM_INT)
			$statement->bindValue($name, (int) $this->_params[$name], $pdo_type);
		$statement->bindValue($name, $this->_params[$name], $pdo_type);
	}

	private function _bindParams($statement, $sql, $params = NULL) {
		if ($params) {
			$this->_params = $params;
			foreach ($this->_params as $name => $value)
				$statement->bindValue($name, $value);
		} else {
			$this->_params = array();
			if (preg_match_all('/(:\w+)/', $sql, $matches))
				foreach ($matches[1] as $name) {
					$param = substr($name, 1);
					if (isset($this->_relation['columns'][$param]))
						$this->_bindParam($statement, $name,
							$this->_relation['columns'][$param]);
					elseif (substr($param, 0, 2) == '__') {
						$param = substr($param, 2);
						if (isset($this->_buffer[$param]))
							$this->_bindParam($statement, $name, $this->_buffer[$param]);
					}
				}
		}
	}

	private function _statement($statement) {
		if ($this->_statement) $this->_statement->closeCursor();
		$this->_statement = $statement;
	}

	private function _prepare($sql, $params = NULL) {
		$statement = $this->_db->pdo->prepare($sql);
		$this->_bindParams($statement, $sql, $params);
		return $statement;
	}

	private function _execute($sql, $params = NULL) {
		$statement = $this->_prepare($sql, $params);
		$statement->setFetchMode(PDO::FETCH_INTO, $this);
		if ($this->_succeeded = $statement->execute()) {
			$this->_affected = $statement->rowCount();
			$this->_modified = FALSE;
			if ($this->_result = $statement->columnCount()) {
				$this->_buffer = array();
				$this->_statement($statement);
				if (!$this->_relation['columns']) $this->_collectColumns();
			}
		} else {
			$this->_affected = 0;
			$this->_result = 0;
			$this->_statement(NULL);
		}
		return $statement;
	}

	private function _autoinc($autoinc) {
		if ($autoinc) {
			if (is_string($autoinc)) $autoinc = array($autoinc => NULL);
			elseif (array_key_exists(0, $autoinc))
				$autoinc = PHPlainUtil::fill($autoinc, NULL);
			$this->_relation['autoinc'] = $autoinc;
		}
		return $autoinc;
	}

	private function _sync($select = TRUE) {
		foreach ($this->_details as $name => $query) {
			foreach ($this->_relation['details'][$name] as $key => $foreign)
				$query->$key = $this->$foreign;
			if ($select) $query->select();
		}
	}

	private function _detail($source, $autoinc = NULL) {
		$where = array();
		$reverse = array();
		foreach ($this->_relation['details'][$source] as $key => $column) {
			$where[] = "$key = :$key";
			$reverse[] = "$column = :$column";
		}
		if (class_exists($source))
			$this->_details[$source] = new $source($this->_db, $source);
		else $this->_details[$source] = new PHPlainQuery($this->_db, $source, $autoinc);
		$this->$source->_where = "(" . implode(' AND ', $where) .")";
		$this->$source->_master = $this;
		$this->$source->_reverse = "(" . implode(' AND ', $reverse) .")";
		$this->$source->_source = $source;
	}

	private function _master($sql = NULL) {
		if ($sql) {
			$this->_execute($sql);
			$this->fetch(FALSE);
		}
		if ($this->_master) {
			if (!$sql) $this->fetch();
			$sql = PHPlainUtil::prepare($this->_master->_relation['queries']['select'], "*", $this->_reverse);
			foreach ($this->_master->_relation['details'][$this->_source]
					 as $key => $column)
				$this->_master->$column = $this->$key;
			$this->_master->_master($sql);
		}
	}

	private function _relation() {
		foreach (array('queries', 'columns', 'autoinc', 'details') as $item)
			if (!isset($this->_relation[$item]))
				$this->_relation[$item] = array();
	}

	private function _setColumnValue($name, $value, $add = TRUE) {
		if (!$this->_relation['columns']) $this->_collectColumns();
		if (isset($this->_relation['columns'][$name]))
			$this->_relation['columns'][$name]['value'] = $value;
		elseif ($add) $this->setColumn(array('name' => $name, 'pdo_type' => PDO::PARAM_STR,
											 'value' => $value));
		else return FALSE;
		return $this->_modified = TRUE;
	}

	public function __construct($db, $source = NULL, $select = "*", $autoinc = NULL) {
		$this->_db = $db;
		$this->_select = $select;
		$this->setPage($this->_page, $this->_pageSize, FALSE);
		if ($source) {
			if (isset($db->json[$source]))
				$this->_relation = array_merge($this->_relation, $db->json[$source]);
			if ($autoinc) $autoinc = $this->_autoinc($autoinc);
			else $autoinc = $this->_autoinc($this->_relation['autoinc']);
			if (!isset($this->_relation['queries']['select']))
				$this->_build($source, $select, $autoinc);
		}
		$this->_relation();
		foreach ($this->_relation['details'] as $query => $foreign)
			$this->_detail($query);
	}

	public function __set($name, $value) {
		if ($this->_fetch) {
			$this->_relation['columns'][$name]['value'] = $value;
			$this->_buffer[$name] = $value;
		} else $this->_setColumnValue($name, $value);
	}

	public function __get($name) {
		if (isset($this->_relation['columns'][$name]['value']))
			return $this->_relation['columns'][$name]['value'];
		elseif (isset($this->_details[$name])) return $this->_details[$name];
		elseif (isset($this->_relation['queries'][$name])) {
			if ($name == 'select') {
				$where = PHPlainConfig::PLACEHOLDER_AND;
				if ($this->_where) $where = $this->_where . " $where";
				$orderBy = PHPlainConfig::PLACEHOLDER_BY;
				if ($this->_orderBy) $orderBy = $this->_orderBy . " $orderBy";
				return PHPlainUtil::prepare($this->_relation['queries']['select'], $this->_select, $where, $orderBy, $this->_db->pagination, $this->_pageSize, $this->_page);
			} else return $this->_relation['queries'][$name];
		}
		elseif ($name == 'columns') return $this->_relation['columns'];
		else return NULL;
	}

	public function __call($name, $arguments) {
		if (array_key_exists($name, $this->_relation['queries'])) {
			if ($name == 'select') {
				$sql = PHPlainUtil::prepare($this->_relation['queries'][$name], $this->_select, $this->_where, $this->_orderBy, $this->_db->pagination, $this->_pageSize, $this->_page);
			} else {
				$sql = PHPlainUtil::prepare($this->_relation['queries'][$name], $this->_select,
					'', '', $this->_db->pagination, $this->_pageSize, $this->_page);
			}
			$return = $this->_execute($sql, $this->_params = PHPlainUtil::reindex($arguments, 1));
			if ($this->_result && $return && $name != 'select') $this->_master();
			return $return;
		} else {
			$by = preg_split('/(by_|_AND_|_OR_)/', $name, NULL,
							 PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
			if (count($by) > 1 && array_shift($by) == 'by_') {
				$this->_params = PHPlainUtil::reindex($arguments, 1);
				$sql = $this->select;
				$i = 0;
				$where = '';
				while ($token = array_shift($by)) {
					if (is_array($this->_params[++$i])) {
						$op = key($this->_params[$i]);
						$this->_params[$i] = $this->_params[$i][$op];
					} else $op = '=';
					$where .= "$token $op ?";
					if ($token = array_shift($by))
						$where .= str_replace('_', ' ', $token);
				}
				$this->where($where);
				return $this->_execute(PHPlainUtil::prepare($sql, $this->_select, $where, $this->_orderBy, $this->_db->pagination, $this->_pageSize, $this->_page), $this->_params);
			}
		}
		return NULL;
	}

	public function setValues(array $values) {
		foreach ($values as $name => $value) $this->_setColumnValue($name, $value, FALSE);
	}

	public function unsetValues() {
		foreach ($this->_relation['columns'] as $name => $value)
			unset($this->_relation['columns'][$name]['value']);
	}

	public function setQuery($name, $sql) {
		$this->_relation['queries'][$name] = $sql;
	}

	public function unsetQuery($name) {
		if (!in_array($name, array('select', 'insert', 'update', 'delete', 'count')))
			unset($this->_relation['queries'][$name]);
	}

	public function setDetail($name, $detail, $autoinc = NULL) {
		$this->unsetDetail($name);
		$this->_relation['details'][$name] = $detail;
		$this->_detail($name, $autoinc);
	}

	public function unsetDetail($name) {
		if (isset($this->_relation['details'][$name])) {
			unset($this->_details[$name]);
			unset($this->_relation['details'][$name]);
		}
	}

	public function setColumn($meta) {
		$this->_relation['columns'][$meta['name']] = $meta;
	}

	public function unsetColumn($name) {
		if (isset($this->_relation['columns'][$name]))
			unset($this->_relation['columns'][$name]);
	}

	public function fetch($sync = TRUE) {
		if (!$this->_statement) $this->select();
		$this->_fetch = TRUE;
		try {
			$return = $this->_statement->fetch();
		} catch (Exception $e) {
			$return = FALSE;
		}
		$this->_fetch = FALSE;
		if ($return) {
			$this->_valid = TRUE;
			$this->_key++;
			if ($sync) $this->_sync();
		} else $this->_valid = FALSE;
		return $return;
	}

	public function setPageSize($size = 0) {
		if ($size >= 0) $this->_pageSize = $size;
	}

	public function setPage($page, $size = NULL, $select = TRUE) {
		if (is_bool($size)) $select = $size;
		elseif ($size !== NULL) $this->setPageSize($size);
		if ($page > 0) $this->_page = $page;
		if ($select) return $this->select();
		else return NULL;
	}

	public function getPage() {
		return $this->_page;
	}

	public function where($where) {
		$this->_where = $where;
	}

	public function orderBy($orderBy) {
		$this->_orderBy = $orderBy;
	}

	public function count() {
		$count = isset($this->_relation['queries']['count']);
		if ($count) $sql = $this->_relation['queries']['count'];
		else $sql = $this->_relation['queries']['select'];
		$sql = PHPlainUtil::prepare($sql, "COUNT(*)", $this->_where);
		$return = 0;
		if ($statement = $this->_prepare($sql)) {
			$statement->setFetchMode(PDO::FETCH_NUM);
			if ($statement->execute()) {
				$return = $statement->rowCount();
				if ($return && $count) $return = current($statement->fetch());
				$statement->closeCursor();
			}
		}
		return $return;
	}

	public function insert($params = NULL) {
		$return = $this->_execute($this->_relation['queries']['insert'], $params);
		if ($return) {
			$this->_fetch = TRUE;
			if ($this->_result)
				foreach ($return->fetch() as $column => $value)
					$this->$column = $value;
			else
				foreach ($this->_relation['autoinc'] as $column => $sequence) {
					if ($sequence) $value = $this->_db->pdo->lastInsertId($sequence);
					else $value = $this->_db->pdo->lastInsertId();
					$this->$column = $value;
				}
			$this->_fetch = FALSE;
			$this->_sync(FALSE);
		}
		return $return;
	}

	public function post() {
		if ($this->_modified)
			if ($this->_buffer) {
				foreach ($this->_relation['autoinc'] as $column => $sequence)
					if (!isset($this->_relation['columns'][$column]['value']))
						return $this->insert();
				foreach ($this->_relation['columns'] as $column => $meta)
					if ((isset($meta['flags']) && (is_array($meta['flags']))
						 && in_array('primary_key', $meta['flags']))
						&& !(isset($meta['value']) && isset($this->_buffer[$column])))
						return $this->insert();
				return $this->update();
			} else return $this->insert();
		else return FALSE;
	}

	public function cancel() {
		$this->_modified = FALSE;
	}

	public function save($buffer) {
		$this->_buffer = array();
		foreach ($buffer as $name => $value)
			if ($this->_setColumnValue($name, $value, FALSE))
				$this->_buffer[$name] = $value;
		$this->post();
	}

	public function current() {
		return $this->_buffer;
	}

	public function key() {
		return $this->_key;
	}

	public function next() {
		$this->post();
		$this->fetch();
	}

	public function rewind() {
		$this->_key = 0;
		$this->_valid = FALSE;
		if (!$this->_statement) $this->select();
		$this->next();
	}

	public function valid() {
		return $this->_valid;
	}

}

class PHPlainDB {

	private
		$_pdo, $dsn, $username, $password, $driver_options;

	public
		$json = NULL, $pagination;

	public function __construct($dsn, $username = NULL, $password = NULL,
								$driver_options = NULL, $pagination = NULL)
	{
		$this->dsn = $dsn;
		$this->username = $username;
		$this->password = $password;
		$this->driver_options = $driver_options;
		if ($pagination) $this->pagination = $pagination;
		else $this->pagination = PHPlainConfig::$pagination;
	}

	public function __get($name) {
		if ($name == 'pdo') {
			if (!isset($this->_pdo))
				$this->_pdo = new PDO($this->dsn, $this->username, $this->password,
									  $this->driver_options);
			return $this->_pdo;
		} elseif (property_exists($this->pdo, $name)) return $this->pdo->$name;
		else return $this->query($name);
	}

	public function __set($name, $value) {
		if (property_exists($this->pdo, $name)) $this->pdo->$name = $value;
	}

	public function __call($name, $args) {
		if (method_exists($this->pdo, $name))
			return call_user_func_array(array($this->pdo, $name), $args);
		else {
			$where = '';
			$select = '*';
			$order = '';
			$argc = count($args);
			if ($argc && is_array($args[$argc - 1])) {
				$params = array_pop($args);
				$argc--;
			} else $params = FALSE;
			switch ($argc) {
				case 3:
					$order = array_pop($args);
				case 2:
					$select = array_shift($args);
				case 1:
					$where = array_pop($args);
			}
			$sql = PHPlainUtil::prepare(str_replace(PHPlainConfig::PLACEHOLDER_FROM, "FROM $name", PHPlainConfig::SELECT_TEMPLATE), $select, $where, $order) . " " . PHPlainConfig::$pagination['first'];
			if ($params) {
				array_unshift($params, $sql);
				$statement = call_user_func_array(array($this, "sql"), $params);
			} else $statement = $this->sql($sql);
			if ($statement) {
				if ($statement->columnCount() == 1) return $statement->fetchColumn();
				else return $statement->fetch();
			} else return NULL;
		}
	}

	public function sql() {
		$args = func_get_args();
		$statement = $this->pdo->prepare(array_shift($args));
		$statement->execute($args);
		$statement->setFetchMode(PDO::FETCH_ASSOC);
		return $statement;
	}

	public function all() {
		$args = func_get_args();
		$statement = call_user_func_array(array($this, 'sql'), $args);
		return $statement->fetchAll();
	}

	public function query($source, $select = "*", $autoinc = NULL) {
		$file = PHPlainConfig::PATH_SQL . "/$source";
		@include_once("$file.php");
		if (file_exists("$file.json"))
			$this->json = json_decode(file_get_contents("$file.json"), TRUE);
		if (class_exists($source)) return new $source($this, $source, $select, $autoinc);
		else return new PHPlainQuery($this, $source, $select, $autoinc);
	}

}

?>
