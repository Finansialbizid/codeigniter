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
 * bundled with this package in the files license.txt / license.rst. It is
 * also available through the world wide web at this URL:
 * http://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to obtain it
 * through the world wide web, please send an email to
 * licensing@ellislab.com so we can send you a copy immediately.
 *
 * @package		CodeIgniter
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2006 - 2012, EllisLab, Inc. (http://ellislab.com/)
 * @license		http://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */

/**
 * CodeIgniter Driver Library Class
 *
 * This class enables you to create "Driver" libraries that add runtime ability
 * to extend the capabilities of a class via additional driver objects
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		EllisLab Dev Team
 * @link
 */
class CI_Driver_Library {

	/**
	 * Array of drivers that are available to use with the driver class
	 *
	 * @var array
	 */
	protected $valid_drivers = array();

	/**
	 * Name of the current class - usually the driver class
	 *
	 * @var string
	 */
	protected $lib_name;

	/**
	 * Subclass prefix from config
	 *
	 * @var	string
	 */
	protected $subclass_prefix = '';

	/**
	 * Get magic method
	 *
	 * The first time a child is used it won't exist, so we instantiate it
	 * subsequents calls will go straight to the proper child.
	 *
	 * @param	string	Child class name
	 * @return	object	Child class
	 */
	public function __get($child)
	{
		// Try to load the driver
		return $this->load_driver($child);
	}

	/**
	 * Load driver
	 *
	 * Separate load_driver call to support explicit driver load by library or user
	 *
	 * @param	string	Driver name (w/o parent prefix)
	 * @return	object	Child class
	 */
	public function load_driver($child)
	{
		// Get CodeIgniter instance
		$CI = get_instance();

		if ( ! isset($this->lib_name))
		{
			// Get library name without any prefix
			$this->subclass_prefix = (string) $CI->config->item('subclass_prefix');
			$this->lib_name = str_replace(array('CI_', $this->subclass_prefix), '', get_class($this));
		}

		// The child will be prefixed with the parent lib
		$child_name = $this->lib_name.'_'.$child;

		// See if requested child is a valid driver
		if ( ! in_array($child, array_map('strtolower', $this->valid_drivers)))
		{
			// The requested driver isn't valid!
			$msg = 'Invalid driver requested: '.$child_name;
			log_message('error', $msg);
			show_error($msg);
		}

		// All driver files should be in a library subdirectory - capitalized
		$subdir = ucfirst(strtolower($this->lib_name));

		// Get package paths and filename case variations to search
		$paths = $CI->load->get_package_paths(TRUE);
		$cases = array(ucfirst($child_name), strtolower($child_name));

		// Is there an extension?
		$class_name = $this->subclass_prefix.$child_name;
		$found = class_exists($class_name);
		if ( ! $found)
		{
			// Check for subclass file
			foreach ($paths as $path)
			{
				// Extension will be in drivers subdirectory
				$path .= 'libraries/'.$subdir.'/drivers/';

				// Try filename with caps and all lowercase
				foreach ($cases as $name)
				{
					// Does the file exist?
					$file = $path.$this->subclass_prefix.$name.'.php';
					if (file_exists($file))
					{
						// Yes - require base class from last path (BASEPATH)
						$basepath = end($paths).'libraries/'.$subdir.'/drivers/'.ucfirst($child_name).'.php';
						if ( ! file_exists($basepath))
						{
							$msg = 'Unable to load the requested class: CI_'.$child_name;
							log_message('error', $msg);
							show_error($msg);
						}

						// Include both sources and mark found
						include($basepath);
						include($file);
						$found = TRUE;
						break 2;
					}
				}
			}
		}

		// Do we need to search for the class?
		if ( ! $found)
		{
			// Use standard class name
			$class_name = 'CI_'.$child_name;
			$found = class_exists($class_name);
			if ( ! $found)
			{
				// Check package paths
				foreach ($paths as $path)
				{
					// Class will be in drivers subdirectory
					$path .= 'libraries/'.$subdir.'/drivers/';

					// Try filename with caps and all lowercase
					foreach ($cases as $name)
					{
						// Does the file exist?
						$file = $path.$name.'.php';
						if (file_exists($file))
						{
							// Include source
							include $file;
							break 2;
						}
					}
				}
			}
		}

		// Did we finally find the class?
		if ( ! class_exists($class_name))
		{
			$msg = 'Unable to load the requested driver: '.$class_name;
			log_message('error', $msg);
			show_error($msg);
		}

		// Instantiate, decorate, and add child
		$obj = new $class_name;
		$obj->decorate($this);
		$this->$child = $obj;
		return $this->$child;
	}

}

// --------------------------------------------------------------------------

/**
 * CodeIgniter Driver Class
 *
 * This class enables you to create drivers for a Library based on the Driver Library.
 * It handles the drivers' access to the parent library
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		EllisLab Dev Team
 * @link
 */
class CI_Driver {

	/**
	 * Instance of the parent class
	 *
	 * @var object
	 */
	protected $_parent;

	/**
	 * List of methods in the parent class
	 *
	 * @var array
	 */
	protected $_methods = array();

	/**
	 * List of properties in the parent class
	 *
	 * @var array
	 */
	protected $_properties = array();

	/**
	 * Array of methods and properties for the parent class(es)
	 *
	 * @var array
	 */
	protected static $_reflections = array();

	/**
	 * Decorate
	 *
	 * Decorates the child with the parent driver lib's methods and properties
	 *
	 * @param	object
	 * @return	void
	 */
	public function decorate($parent)
	{
		$this->_parent = $parent;

		// Lock down attributes to what is defined in the class
		// and speed up references in magic methods

		$class_name = get_class($parent);

		if ( ! isset(self::$_reflections[$class_name]))
		{
			$r = new ReflectionObject($parent);

			foreach ($r->getMethods() as $method)
			{
				if ($method->isPublic())
				{
					$this->_methods[] = $method->getName();
				}
			}

			foreach ($r->getProperties() as $prop)
			{
				if ($prop->isPublic())
				{
					$this->_properties[] = $prop->getName();
				}
			}

			self::$_reflections[$class_name] = array($this->_methods, $this->_properties);
		}
		else
		{
			list($this->_methods, $this->_properties) = self::$_reflections[$class_name];
		}
	}

	// --------------------------------------------------------------------

	/**
	 * __call magic method
	 *
	 * Handles access to the parent driver library's methods
	 *
	 * @param	string
	 * @param	array
	 * @return	mixed
	 */
	public function __call($method, $args = array())
	{
		if (in_array($method, $this->_methods))
		{
			return call_user_func_array(array($this->_parent, $method), $args);
		}

		$trace = debug_backtrace();
		_exception_handler(E_ERROR, "No such method '{$method}'", $trace[1]['file'], $trace[1]['line']);
		exit;
	}

	// --------------------------------------------------------------------

	/**
	 * __get magic method
	 *
	 * Handles reading of the parent driver library's properties
	 *
	 * @param	string
	 * @return	mixed
	 */
	public function __get($var)
	{
		if (in_array($var, $this->_properties))
		{
			return $this->_parent->$var;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * __set magic method
	 *
	 * Handles writing to the parent driver library's properties
	 *
	 * @param	string
	 * @param	array
	 * @return	mixed
	 */
	public function __set($var, $val)
	{
		if (in_array($var, $this->_properties))
		{
			$this->_parent->$var = $val;
		}
	}

}

/* End of file Driver.php */
/* Location: ./system/libraries/Driver.php */
