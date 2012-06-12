<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.2.4 or newer
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
 * @since		Version 1.0
 * @filesource
 */

/**
 * Initialize the database
 *
 * @category	Database
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/database/
 * @param 	string
 * @param 	bool	Determines if query builder should be used or not
 */
function &DB($params = '', $query_builder_override = NULL)
{
	// Load the DB config file if a DSN string wasn't passed
	if (is_string($params) && strpos($params, '://') === FALSE)
	{
		// Is the config file in the environment folder?
		if (( ! defined('ENVIRONMENT') OR ! file_exists($file_path = APPPATH.'config/'.ENVIRONMENT.'/database.php'))
			&& ! file_exists($file_path = APPPATH.'config/database.php'))
		{
			show_error('The configuration file database.php does not exist.');
		}

		include($file_path);

		if ( ! isset($db) OR count($db) === 0)
		{
			show_error('No database connection settings were found in the database config file.');
		}

		if ($params !== '')
		{
			$active_group = $params;
		}

		if ( ! isset($active_group) OR ! isset($db[$active_group]))
		{
			show_error('You have specified an invalid database connection group.');
		}

		$params = $db[$active_group];
	}
	elseif (is_string($params))
	{

		/* parse the URL from the DSN string
		 *  Database settings can be passed as discreet
		 *  parameters or as a data source name in the first
		 *  parameter. DSNs must have this prototype:
		 *  $dsn = 'driver://username:password@hostname/database';
		 */
		if (($dsn = @parse_url($params)) === FALSE)
		{
			show_error('Invalid DB Connection String');
		}

		$params = array(
				'dbdriver'	=> $dsn['scheme'],
				'hostname'	=> isset($dsn['host']) ? rawurldecode($dsn['host']) : '',
				'port'		=> isset($dsn['port']) ? rawurldecode($dsn['port']) : '',
				'username'	=> isset($dsn['user']) ? rawurldecode($dsn['user']) : '',
				'password'	=> isset($dsn['pass']) ? rawurldecode($dsn['pass']) : '',
				'database'	=> isset($dsn['path']) ? rawurldecode(substr($dsn['path'], 1)) : ''
			);

		// were additional config items set?
		if (isset($dsn['query']))
		{
			parse_str($dsn['query'], $extra);

			foreach ($extra as $key => $val)
			{
				if (is_string($val) && in_array(strtoupper($val), array('TRUE', 'FALSE', 'NULL')))
				{
					$val = var_export($val);
				}

				$params[$key] = $val;
			}
		}
	}

	// No DB specified yet? Beat them senseless...
	if (empty($params['dbdriver']))
	{
		show_error('You have not selected a database type to connect to.');
	}

	// Load the DB classes. Note: Since the query builder class is optional
	// we need to dynamically create a class that extends proper parent class
	// based on whether we're using the query builder class or not.
	if ($query_builder_override !== NULL)
	{
		$query_builder = $query_builder_override;
	}
	// Backwards compatibility work-around for keeping the
	// $active_record config variable working. Should be
	// removed in v3.1
	elseif ( ! isset($query_builder) && isset($active_record))
	{
		$query_builder = $active_record;
	}

	require_once(BASEPATH.'database/DB_driver.php');

	if ( ! isset($query_builder) OR $query_builder === TRUE)
	{
		require_once(BASEPATH.'database/DB_query_builder.php');
		if ( ! class_exists('CI_DB'))
		{
			class CI_DB extends CI_DB_query_builder { }
		}
	}
	elseif ( ! class_exists('CI_DB'))
	{
		class CI_DB extends CI_DB_driver { }
	}

	// Load the DB driver
	if ($params['dbdriver'] !== 'pdo')
	{
		$driver_file = BASEPATH.'database/drivers/'.$params['dbdriver'].'/'.$params['dbdriver'].'_driver.php';
	}
	else
	{
		// Require the main pdo driver class
		require_once(BASEPATH.'database/drivers/pdo/pdo_driver.php');
	
		$match = array();
		
		if (empty($params['dsn']))
		{
			$params['dsn'] = '';
		}

		if (preg_match('/([^;]+):/', $params['dsn'], $match) && count($match) == 2)
		{
			// If there is a minimum valid dsn string pattern found, we're done
			// This is for general PDO users, who tend to have a full DSN string.
			$pdodriver = end($match);
		}
		else
		{
			// Try to build a complete DSN string from params
			if (strpos($params['hostname'], ':'))
			{
				// hostname generally would have this prototype
				// $db['hostname'] = 'pdodriver:host(/Server(/DSN))=hostname(/DSN);';
				// We need to get the prefix (pdodriver used by PDO).
				$dsnarray = explode(':', $params['hostname']);
				$pdodriver = $dsnarray[0];

				// Extract the hostname from the partial dsn
				$params['hostname'] = preg_replace('`(host|server)=`', '', $dsnarray[1]);
			}
			else
			{
				// Invalid DSN, display an error
				if ( ! array_key_exists('pdodriver', $params))
				{
					show_error('Invalid DB Connection String for PDO');
				}
				else
				{
					$pdodriver = $params['pdodriver'];
				}
			}
		}
		
		// Load the sub driver for database-specific stuff
		$pdodriver = strtolower($pdodriver);
		$params['pdodriver'] = $pdodriver;

		// So many libraries for connecting to the same database!
		// Since SQL Server is a fork of Sybase, this should cover
		// a lot of bases with one sub-driver
		if (in_array($pdodriver, array('dblib','mssql','sybase','sqlsrv')))
		{
			$pdodriver = 'sqlsrv';
		}

		$driver_file = BASEPATH."database/drivers/pdo/sub_drivers/{$pdodriver}.php";
	}
	
	if ( ! file_exists($driver_file)) show_error('Invalid DB driver');

	require_once($driver_file);

	// Instantiate the DB adapter
	$driver = ($params['dbdriver'] !== 'pdo') 
		? 'CI_DB_'.$params['dbdriver'].'_driver'
		: "CI_{$pdodriver}_PDO_Driver";
		
	$DB = new $driver($params);

	if ($DB->autoinit === TRUE)
	{
		$DB->initialize();
	}

	if ( ! empty($params['stricton']))
	{
		$DB->query('SET SESSION sql_mode="STRICT_ALL_TABLES"');
	}

	return $DB;
}

/* End of file DB.php */
/* Location: ./system/database/DB.php */
