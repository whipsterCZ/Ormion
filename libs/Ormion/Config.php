<?php

namespace Ormion;

use Nette\Object;
use Nette\Config\ConfigAdapterIni;
use InvalidArgumentException;
use DibiTableInfo;

/**
 * Ormion config
 *
 * @author Jan Marek
 * @license MIT
 */
class Config extends Object {

	/** @var bool */
	public static $generateForms = true;

	/** @var array */
	private $data;

	
	/**
	 * Constructor
	 * @param array $data
	 */
	public function __construct($data) {
		$this->data = $data;
	}


	/**
	 * Save config to ini file
	 * @param string $file
	 * @return Config
	 */
	public function save($file) {
		ConfigAdapterIni::save($this->data, $file);
		return $this;
	}


	/**
	 * Create config from ini file
	 * @param string $file
	 * @return Config
	 */
	public static function fromFile($file) {
		return new self(ConfigAdapterIni::load($file));
	}


	/**
	 * Create Config from database table info
	 * @return Config
	 */
	public static function fromTableInfo(DibiTableInfo $tableInfo) {
		// columns
		foreach ($tableInfo->getColumns() as $column) {
			$name = $column->getName();
			$arr["column"][$name]["type"] = $column->getType();

			if ($column->isNullable()) {
				$arr["column"][$name]["nullable"] = true;
			}
		}

		// keys
		foreach ($tableInfo->getPrimaryKey()->getColumns() as $column) {
			$name = $column->getName();
			$arr["key"][$name]["primary"] = true;
			$arr["key"][$name]["autoIncrement"] = $column->isAutoIncrement();
		}

		// form
		if (self::$generateForms) {
			foreach ($arr["column"] as $name => $column) {
				// key
				if (isset($arr["key"][$name])) {
					$arr["form_default"][$name]["type"] = "hidden";

				// regular column
				} else {
					$arr["form_default"][$name]["type"] = "text";
					$arr["form_default"][$name]["label"] = $name;

					if (empty($column["nullable"])) {
						$arr["form_default"][$name]["validation"]["required"] = true;
					}
				}
			}

			// submit button
			$arr["form_default"]["s"] = array(
				"type" => "submit",
				"label" => "OK",
			);
		}

		return new self($arr);
	}


	/**
	 * Get column
	 * @param string $name
	 * @return array
	 */
	private function getColumn($name) {
		return isset($this->data["column"][$name]) ? $this->data["column"][$name] : null;
	}


	/**
	 * Get column names
	 * @return array
	 */
	public function getColumns() { // getColumns ?
		$arr = array();

		foreach ($this->data["column"] as $name => $column) {
			if ($this->isColumn($name)) {
				$arr[] = $name;
			}
		}

		return $arr;
	}


	/**
	 * Is real column
	 * @param string $name column name
	 * @return bool
	 */
	public function isColumn($name) {
		return !(isset($this->data["column"][$name]["column"]) && $this->data["column"][$name]["column"] == false);
	}


	/**
	 * Get dibi type
	 * @param string $name column name
	 * @return string
	 */
	public function getType($name) {
		$column = $this->getColumn($name);
		return $column ? $column["type"] : null;
	}


	/**
	 * Is column nullable
	 * @param string $name
	 * @return bool
	 */
	public function isNullable($name) {
		$column = $this->getColumn($name);
		return isset($column["nullable"]) ? (bool) $column["nullable"] : false;
	}


	/**
	 * Is primary key auto increment
	 * @return bool
	 */
	public function isPrimaryAutoIncrement() {
		foreach ($this->data["key"] as $key) {
			if ($key["primary"]) {
				return (bool) $key["autoIncrement"];
			}
		}

		return false;
	}


	/**
	 * Get primary column names
	 * @return array
	 */
	public function getPrimaryColumns() {
		$arr = array();

		foreach ($this->data["key"] as $name => $key) {
			if ($key["primary"]) {
				$arr[] = $name;
			}
		}

		return $arr;
	}


	/**
	 * Get first primary column name
	 * @return string
	 */
	public function getPrimaryColumn() {
		foreach ($this->data["key"] as $name => $key) {
			if ($key["primary"]) {
				return $name;
			}
		}

		return null;
	}


	/**
	 * Get form configuration
	 * @param string $name
	 * @return array
	 */
	public function getForm($name) {
		if (empty($this->data["form_$name"])) {
			throw new InvalidArgumentException("Form with name '$name' does not exist.");
		}

		return $this->data["form_$name"];
	}

}