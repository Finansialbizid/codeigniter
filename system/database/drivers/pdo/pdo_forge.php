<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.1.6 or newer
 *
 * NOTICE OF LICENSE
 *
 * Licensed under the Open Software License version 3.0
 *
 * This source file is subject to the Open Software License (OSL 3.0) that is
 * bundled with this package in the files license.txt / license.rst.  It is
 * also available through the world wide web at this URL:
 * http://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to obtain it
 * through the world wide web, please send an email to
 * licensing@ellislab.com so we can send you a copy immediately.
 *
 * @package		CodeIgniter
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2008 - 2012, EllisLab, Inc. (http://ellislab.com/)
 * @license		http://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * @link		http://codeigniter.com
 * @since		Version 2.1.0
 * @filesource
 */

/**
 * PDO Forge Class
 *
 * @category	Database
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/database/
 */
class CI_DB_pdo_forge extends CI_DB_forge {

	/**
	 * Create database
	 *
	 * @param	string	the database name
	 * @return	bool
	 */
	public function _create_database()
	{
		// PDO has no "create database" command since it's
		// designed to connect to an existing database
		return ($this->db->db_debug) ? $this->db->display_error('db_unsuported_feature') : FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * Drop database
	 *
	 * @param	string	the database name
	 * @return	bool
	 */
	public function _drop_database($name)
	{
		// PDO has no "drop database" command since it's
		// designed to connect to an existing database
		return ($this->db->db_debug) ? $this->db->display_error('db_unsuported_feature') : FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * Create Table
	 *
	 * @param	string	the table name
	 * @param	array	the fields
	 * @param	mixed	primary key(s)
	 * @param	mixed	key(s)
	 * @param	bool	should 'IF NOT EXISTS' be added to the SQL
	 * @return	bool
	 */
	public function _create_table($table, $fields, $primary_keys, $keys, $if_not_exists)
	{
		$sql = 'CREATE TABLE '.($if_not_exists === TRUE ? 'IF NOT EXISTS ' : '')
			.$this->db->protect_identifiers($table).' (';

		$current_field_count = 0;
		foreach ($fields as $field => $attributes)
		{
			// Numeric field names aren't allowed in databases, so if the key is
			// numeric, we know it was assigned by PHP and the developer manually
			// entered the field information, so we'll simply add it to the list
			if (is_numeric($field))
			{
				$sql .= "\n\t".$attributes;
			}
			else
			{
				$attributes = array_change_key_case($attributes, CASE_UPPER);
				$numeric = array('SERIAL', 'INTEGER');

				$sql .= "\n\t".$this->db->protect_identifiers($field)
					.' '.$attributes['TYPE'];

				if ( ! empty($attributes['CONSTRAINT']))
				{
					// Exception for Postgre numeric which not too happy with constraint within those type
					if ( ! ($this->db->pdodriver === 'pgsql' && in_array($attributes['TYPE'], $numeric)))
					{
						$sql .= '('.$attributes['CONSTRAINT'].')';
					}
				}

				$sql .= (( ! empty($attributes['UNSIGNED']) && $attributes['UNSIGNED'] === TRUE) ? ' UNSIGNED' : '')
					.(isset($attributes['DEFAULT']) ? " DEFAULT ".$attributes['DEFAULT']."'" : '')
					.(( ! empty($attributes['NULL']) && $attributes['NULL'] === TRUE) ? ' NULL' : ' NOT NULL')
					.(( ! empty($attributes['AUTO_INCREMENT']) && $attributes['AUTO_INCREMENT'] === TRUE) ? ' AUTO_INCREMENT' : '');
			}

			// don't add a comma on the end of the last field
			if (++$current_field_count < count($fields))
			{
				$sql .= ',';
			}
		}

		if (count($primary_keys) > 0)
		{
			$sql .= ",\n\tPRIMARY KEY (".implode(', ', $this->db->protect_identifiers($primary_keys)).')';
		}

		if (is_array($keys) && count($keys) > 0)
		{
			foreach ($keys as $key)
			{
				$key = is_array($key)
					? $this->db->protect_identifiers($key)
					: array($this->db->protect_identifiers($key));

				$sql .= ",\n\tFOREIGN KEY (".implode(', ', $key).')';
			}
		}

		return $sql."\n)";
	}

	// --------------------------------------------------------------------

	/**
	 * Drop Table
	 *
	 * @return	bool
	 */
	public function _drop_table($table)
	{
		// Not supported in PDO
		return ($this->db->db_debug) ? $this->db->display_error('db_unsuported_feature') : FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * Alter table query
	 *
	 * Generates a platform-specific query so that a table can be altered
	 * Called by add_column(), drop_column(), and column_alter(),
	 *
	 * @param	string	the ALTER type (ADD, DROP, CHANGE)
	 * @param	string	the column name
	 * @param	string	the table name
	 * @param	string	the column definition
	 * @param	string	the default value
	 * @param	bool	should 'NOT NULL' be added
	 * @param	string	the field after which we should add the new field
	 * @return	string
	 */
	public function _alter_table($alter_type, $table, $column_name, $column_definition = '', $default_value = '', $null = '', $after_field = '')
	{
		$sql = 'ALTER TABLE '.$this->db->protect_identifiers($table).' '.$alter_type.' '.$this->db->protect_identifiers($column_name);

		// DROP has everything it needs now.
		if ($alter_type === 'DROP')
		{
			return $sql;
		}

		return $sql.' '.$column_definition
			.($default_value !== '' ? " DEFAULT '".$default_value."'" : '')
			.($null === NULL ? ' NULL' : ' NOT NULL')
			.($after_field != '' ? ' AFTER '.$this->db->protect_identifiers($after_field) : '');
	}


	// --------------------------------------------------------------------

	/**
	 * Rename a table
	 *
	 * Generates a platform-specific query so that a table can be renamed
	 *
	 * @param	string	the old table name
	 * @param	string	the new table name
	 * @return	string
	 */
	public function _rename_table($table_name, $new_table_name)
	{
		return 'ALTER TABLE '.$this->db->protect_identifiers($table_name).' RENAME TO '.$this->db->protect_identifiers($new_table_name);
	}

}

/* End of file pdo_forge.php */
/* Location: ./system/database/drivers/pdo/pdo_forge.php */
