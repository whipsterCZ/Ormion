<?php

namespace Ormion;

use LazyArrayList;
use IDataSource;
use DibiFluent;
use DibiDriverException;

/**
 * Record set
 *
 * @author Jan Marek
 * @license MIT
 */
class Collection extends LazyArrayList implements IDataSource {

	/** @var DibiFluent */
	private $fluent;

	
	/**
	 * Construct
	 * @param DibiFluent $fluent
	 * @param string $rowClass
	 */
	public function __construct(DibiFluent $fluent, $rowClass) {
		parent::__construct(null, $rowClass);
		$this->fluent = $fluent;
	}


	/**
	 * Load items
	 */
	protected function load() {
		$this->import($this->fetchAll());
	}


	/**
	 * Change DibiFluent
	 * @param string $name
	 * @param array $args
	 * @return Collection
	 */
	public function __call($name, $args) {
		call_user_func_array(array($this->fluent, $name), $args);
		$this->setLoaded(false);
		return $this;
	}


	/**
	 * Get DibiDataSource object
	 * @return DibiDataSource
	 */
	public function toDataSource() {
		return $this->fluent->toDataSource();
	}


	/**
	 * Get DibiFluent object
	 * @return DibiDataSource
	 */
	public function toFluent() {
		return $this->fluent;
	}


	/**
	 * Count items in collection
	 * @return int
	 */
	public function count() {
		return $this->isLoaded() ? parent::count() : $this->fluent->count();
	}


	/**
	 * Freeze collection
	 */
	public function freeze() {
		foreach ($this as &$item) {
			$item->freeze();
		}
		
		parent::freeze();
	}


	/**
	 * Execute fluent
	 * @param DibiFluent $fluent
	 * @param array $detectTypes
	 * @return DibiResult
	 * @throws ModelException
	 */
	private function runQuery(DibiFluent $fluent, array $detectTypes = null) {
		try {
			$res = $fluent->execute();

			if ($detectTypes) {
				$class = $this->getItemType();
				$config = $class::getConfig();
				
				foreach ($detectTypes as $column) {
					$res->setType($column, $config->getType($column));
				}
			}

			return $res;
		} catch (DibiDriverException $e) {
			throw new ModelException("Query failed. " . $e->getMessage(), $e->getCode(), $e);
		}
	}


	/**
	 * Fetches all records from table.
	 * @param int $limit offset
	 * @param int $offset limit
	 * @return array
	 */
	public function fetchAll($limit = null, $offset = null) {
		$fluent = clone $this->fluent;

		if ($limit) {
			$fluent->limit($limit);
			if ($offset) $fluent->offset($offset);
		}

		$res = $this->runQuery($fluent)
			->setRowClass($this->getItemType())
			->fetchAll();

		foreach ($res as &$row) {
			$row->setState(IRecord::STATE_EXISTING)->clearModified();
		}

		return $res;
	}


	/**
	 * Fetches all records from table and returns associative tree.
	 * @param string $assoc associative descriptor
	 * @return array
	 */
	public function fetchAssoc($assoc) {
		$arr = $this->runQuery($this->fluent)
			->setRowClass($this->getItemType())
			->fetchAssoc($assoc);

		array_walk_recursive($arr, function ($item) {
			$item->setState(IRecord::STATE_EXISTING)->clearModified();
		});
		
		return $arr;
	}

	/**
	 * Fetches all records like $key => $value pairs.
	 * @param string $key associative key
	 * @param string $value value
	 * @return array
	 */
	public function fetchPairs($key, $value) {
		$fluent = clone $this->fluent;
		$fluent->removeClause("select")->select("%n, %n", $key, $value);
		return $this->runQuery($fluent, array($key, $value))->fetchPairs($key, $value);
	}


	/**
	 * Fetches one column in all records
	 * @param string $column column name
	 * @return array
	 */
	public function fetchColumn($column) {
		$fluent = clone $this->fluent;
		$fluent->removeClause("select")->select("%n", $column);
		return $this->runQuery($fluent, array($column))->fetchPairs();
	}


	/**
	 * Fetches single value
	 * @return mixed
	 */
	public function fetchSingle($column) {
		$fluent = clone $this->fluent;
		$fluent->removeClause("select")->select("%n", $column);
		return $this->runQuery($fluent, array($column))->fetchSingle();
	}


	/**
	 * Fetches single object
	 * @return Record|false
	 */
	public function fetch() {
		$arr = $this->fetchAll(1);
		return isset($arr[0]) ? $arr[0] : false;
	}


	/**
	 * Get aggregate function value
	 * @param string $functionName function name
	 * @param string $column column name
	 * @return int
	 */
	private function getAggr($functionName, $column) {
		$fluent = clone $this->fluent;

		$res = $this->runQuery(
			$fluent->removeClause("select")->select("$functionName([$column])")
		);

		$res->detectTypes();

		return $res->fetchSingle();
	}


	/**
	 * Get max column value
	 * @param string $column column name
	 * @return int
	 */
	public function getMax($column) {
		return $this->getAggr("max", $column);
	}


	/**
	 * Get min column value
	 * @param string $column column name
	 * @return int
	 */
	public function getMin($column) {
		return $this->getAggr("min", $column);
	}


	/**
	 * Get average column value
	 * @param string $column column name
	 * @return int
	 */
	public function getAvg($column) {
		return $this->getAggr("avg", $column);
	}


	/**
	 * Get sum column value
	 * @param string $column column name
	 * @return int
	 */
	public function getSum($column) {
		return $this->getAggr("sum", $column);
	}

}