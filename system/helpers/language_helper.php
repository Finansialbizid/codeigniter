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
 * CodeIgniter Language Helpers
 *
 * @package		CodeIgniter
 * @subpackage	Helpers
 * @category	Helpers
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/helpers/language_helper.html
 */

// ------------------------------------------------------------------------

if ( ! function_exists('lang'))
{
	/**
	 * Lang
	 *
	 * Fetches a language variable and optionally outputs a form label
	 *
	 * @param	string	the language line
	 * @param	string	the id of the form element
	 * @return	string
	 */
	function lang($line, $id = '')
	{
		$CI =& get_instance();
		$line = $CI->lang->line($line);

		if ($id !== '')
		{
			$line = '<label for="'.$id.'">'.$line.'</label>';
		}

		return $line;
	}
}

if ( ! function_exists('lang_format'))
{
	/**
	 * Lang Format
	 *
	 * Fetches a language variable and optionally replaces placeholders with
	 * actual values
	 *
	 * @param	string	the language line
	 * @param	string	the actual value(s) for the placeholder(s)
	 * @param	string	the placeholder string
	 * @return	string
	 */
	function lang_format($line, $values = NULL, $placeholder = '?')
	{
		$line = lang($line);

		if($values !== NULL && ($position = strpos($line, $placeholder)) !== FALSE)
		{
			$values = is_array($values) ? $values : array($values);
			$placeholder_length = strlen($placeholder);
			$index = 0;

			do
			{
				$line = substr_replace($line, $values[$index++], $position, $placeholder_length);
				$position = strpos($line, $placeholder, $position + $placeholder_length);
			}
			while($position !== FALSE);
		}

		return $line;
	}
}

/* End of file language_helper.php */
/* Location: ./system/helpers/language_helper.php */