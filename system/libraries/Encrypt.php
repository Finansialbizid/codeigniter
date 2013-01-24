<?php
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
 * @copyright	Copyright (c) 2008 - 2013, EllisLab, Inc. (http://ellislab.com/)
 * @license		http://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CodeIgniter Encryption Class
 *
 * Provides two-way keyed encoding using XOR Hashing and Mcrypt
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/libraries/encryption.html
 */
class CI_Encrypt {

	/**
	 * Reference to the user's encryption key
	 *
	 * @var string
	 */
	public $encryption_key		= '';

	/**
	 * Type of hash operation
	 *
	 * @var string
	 */
	protected $_hash_type		= 'sha1';

	/**
	 * Byte length of selected hash output
	 *
	 * @var int
	 */
	protected $_hmac_len;

	/**
	 * Flag for the existance of mcrypt
	 *
	 * @var bool
	 */
	protected $_mcrypt_exists	= FALSE;

	/**
	 * Current cipher to be used with mcrypt
	 *
	 * @var string
	 */
	protected $_mcrypt_cipher;

	/**
	 * Method for encrypting/decrypting data
	 *
	 * @var int
	 */
	protected $_mcrypt_mode;

	/**
	 * Initialize Encryption class
	 *
	 * @return	void
	 */
	public function __construct()
	{
		$this->_mcrypt_exists = function_exists('mcrypt_encrypt');
		log_message('debug', 'Encrypt Class Initialized');
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch the encryption key
	 *
	 * Returns it as MD5 in order to have an exact-length 128 bit key.
	 * Mcrypt is sensitive to keys that are not the correct length
	 *
	 * @param	string
	 * @return	string
	 */
	public function get_key($key = '')
	{
		if ($key === '')
		{
			if ($this->encryption_key !== '')
			{
				return $this->encryption_key;
			}

			$key = config_item('encryption_key');

			if ($key === FALSE)
			{
				show_error('In order to use the encryption class requires that you set an encryption key in your config file.');
			}
		}

		return md5($key);
	}

	// --------------------------------------------------------------------

	/**
	 * Set the encryption key
	 *
	 * @param	string
	 * @return	CI_Encrypt
	 */
	public function set_key($key = '')
	{
		$this->encryption_key = $key;
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Encode
	 *
	 * Encodes the message string using bitwise XOR encoding.
	 * The key is combined with a random hash, and then it
	 * too gets converted using XOR. The whole thing is then run
	 * through mcrypt (if supported) using the randomized key.
	 * The end result is a double-encrypted message string
	 * that is randomized with each call to this function,
	 * even if the supplied message and key are the same.
	 *
	 * @param	string	the string to encode
	 * @param	string	the key
	 * @return	string
	 */
	public function encode($string, $key = '')
	{
		$method = ($this->_mcrypt_exists === TRUE) ? 'mcrypt_encode' : '_xor_encode';
		return base64_encode($this->$method($string, $this->get_key($key)));
	}

	// --------------------------------------------------------------------

	/**
	 * Decode
	 *
	 * Reverses the above process
	 *
	 * @param	string
	 * @param	string
	 * @return	string
	 */
	public function decode($string, $key = '')
	{
		if (preg_match('/[^a-zA-Z0-9\/\+=]/', $string) OR base64_encode(base64_decode($string)) !== $string)
		{
			return FALSE;
		}

		$method = ($this->_mcrypt_exists === TRUE) ? 'mcrypt_decode' : '_xor_decode';
		return $this->$method(base64_decode($string), $this->get_key($key));
	}

	// --------------------------------------------------------------------

	/**
	 * Encode from Legacy
	 *
	 * Takes an encoded string from the original Encryption class algorithms and
	 * returns a newly encoded string using the improved method added in 2.0.0
	 * This allows for backwards compatibility and a method to transition to the
	 * new encryption algorithms.
	 *
	 * For more details, see http://codeigniter.com/user_guide/installation/upgrade_200.html#encryption
	 *
	 * @param	string
	 * @param	int		(mcrypt mode constant)
	 * @param	string
	 * @return	string
	 */
	public function encode_from_legacy($string, $legacy_mode = MCRYPT_MODE_ECB, $key = '')
	{
		if ($this->_mcrypt_exists === FALSE)
		{
			log_message('error', 'Encoding from legacy is available only when Mcrypt is in use.');
			return FALSE;
		}
		elseif (preg_match('/[^a-zA-Z0-9\/\+=]/', $string))
		{
			return FALSE;
		}

		// decode it first
		// set mode temporarily to what it was when string was encoded with the legacy
		// algorithm - typically MCRYPT_MODE_ECB
		$current_mode = $this->_get_mode();
		$this->set_mode($legacy_mode);

		$key = $this->get_key($key);
		$dec = base64_decode($string);
		if (($dec = $this->mcrypt_decode($dec, $key)) === FALSE)
		{
			$this->set_mode($current_mode);
			return FALSE;
		}

		$dec = $this->_xor_decode($dec, $key);

		// set the mcrypt mode back to what it should be, typically MCRYPT_MODE_CBC
		$this->set_mode($current_mode);

		// and re-encode
		return base64_encode($this->mcrypt_encode($dec, $key));
	}

	// --------------------------------------------------------------------

	/**
	 * XOR Encode
	 *
	 * Takes a plain-text string and key as input and generates an
	 * encoded bit-string using XOR
	 *
	 * @param	string
	 * @param	string
	 * @return	string
	 */
	protected function _xor_encode($string, $key)
	{
		$rand = '';
		do
		{
			$rand .= mt_rand(0, mt_getrandmax());
		}
		while (strlen($rand) < 32);

		$rand = $this->hash($rand);

		$enc = '';
		for ($i = 0, $ls = strlen($string), $lr = strlen($rand); $i < $ls; $i++)
		{
			$enc .= $rand[($i % $lr)].($rand[($i % $lr)] ^ $string[$i]);
		}

		return $this->_xor_merge($enc, $key);
	}

	// --------------------------------------------------------------------

	/**
	 * XOR Decode
	 *
	 * Takes an encoded string and key as input and generates the
	 * plain-text original message
	 *
	 * @param	string
	 * @param	string
	 * @return	string
	 */
	protected function _xor_decode($string, $key)
	{
		$string = $this->_xor_merge($string, $key);

		$dec = '';
		for ($i = 0, $l = strlen($string); $i < $l; $i++)
		{
			$dec .= ($string[$i++] ^ $string[$i]);
		}

		return $dec;
	}

	// --------------------------------------------------------------------

	/**
	 * XOR key + string Combiner
	 *
	 * Takes a string and key as input and computes the difference using XOR
	 *
	 * @param	string
	 * @param	string
	 * @return	string
	 */
	protected function _xor_merge($string, $key)
	{
		$hash = $this->hash($key);
		$str = '';
		for ($i = 0, $ls = strlen($string), $lh = strlen($hash); $i < $ls; $i++)
		{
			$str .= $string[$i] ^ $hash[($i % $lh)];
		}

		return $str;
	}
	// --------------------------------------------------------------------

	/**
	 * Get hash algorithm.
	 *
	 * @access	private
	 * @return	string
	 */

	function _get_hash()
	{
		return $this->_hash_type;
	}
	// --------------------------------------------------------------------

	/**
	 * Get HMAC length for currently selected hash algorithm.
	 *
	 * @access	private
	 * @return	integer
	 */

	function _get_hmac_len()
	{
		if($this->_hmac_len == '')
		{
			// this will actually calculate HMAC for a dummy data in hex format
			$this->_hmac_len = strlen(hash_hmac($this->_get_hash(), 'dummy', 'dummy', FALSE));
		}

		// as we use hex the binary length will be half of it
		return $this->_hmac_len / 2;
	}

	// --------------------------------------------------------------------

	/**
	 * Encrypt using Mcrypt
	 *
	 * @param	string
	 * @param	string
	 * @return	string
	 */
	public function mcrypt_encode($data, $key)
	{
		print('<h2>Encryption</h2>');
		$init_size = mcrypt_get_iv_size($this->_get_cipher(), $this->_get_mode());

		print("data=\"$data\" (orig)<br>");

		// PKCS#7 padding
		$block_size = mcrypt_get_block_size($this->_get_cipher(), $this->_get_mode());
		$pad = $block_size - (strlen($data) % $block_size);
		$data .= str_repeat(chr($pad), $pad);

		print("data=\"$data\" (padded)<br>");

		$init_vect = mcrypt_create_iv($init_size, MCRYPT_RAND);
		$ciphertext = mcrypt_encrypt($this->_get_cipher(), $key, $data, $this->_get_mode(), $init_vect);
		// calculate binary HMAC for ciphertext plus IV
		$mac = hash_hmac($this->_get_hash(), $init_vect.$ciphertext, $key, TRUE);

		print("block size=$block_size data len=" . strlen($data) . "<br>");
		print("pad=$pad pad str=" . str_repeat(chr($pad), $pad) . " <br>");
		print('iv=' . bin2hex($init_vect) . '<br>');
		print('ciphertext=' . bin2hex($ciphertext) . '<br>');
		print('mac=' . bin2hex($mac) . '<br>');
		print('output=' . bin2hex($mac.$init_vect.$ciphertext) . '<br>');

		print('<h2>End Encryption</h2>');

		return $mac.$init_vect.$ciphertext;
	}

	// --------------------------------------------------------------------

	/**
	 * Decrypt using Mcrypt
	 *
	 * @param	string
	 * @param	string
	 * @return	string
	 */
	public function mcrypt_decode($data, $key)
	{
		print('<h2>Decryption</h2>');
		print('input=' . bin2hex($data) . '<br>');

		$init_size = mcrypt_get_iv_size($this->_get_cipher(), $this->_get_mode());

		if ($init_size > strlen($data))
		{
			return FALSE;
		}

		$mac = substr($data, 0, $this->_get_hmac_len());
		$init_vect = substr($data, $this->_get_hmac_len(), $init_size);
		$data = substr($data, $this->_get_hmac_len() + $init_size);

		print('mac=' . bin2hex($mac) . '(received) <br>');
		print('iv=' . bin2hex($init_vect) . '<br>');
		print('ciphertext=' . bin2hex($data) . '<br>');

		$calculated_mac = hash_hmac($this->_get_hash(), $init_vect.$data, $key, TRUE);
		print('mac=' . bin2hex($calculated_mac) . '(calculated) <br>');

		if ($calculated_mac != $mac) 
		{
			print('<h2>Integrity error</h2>');
			return FALSE;
		}

		$plaintext = mcrypt_decrypt($this->_get_cipher(), $key, $data, $this->_get_mode(), $init_vect);

		print("plain=\"$plaintext\" (before pad removal)<br>");
		
		// PKCS#7 padding removal
		$block_size = mcrypt_get_block_size($this->_get_cipher(), $this->_get_mode());
		$pad = ord($plaintext[($len = strlen($plaintext)) - 1]);

		$plaintext = substr($plaintext, 0, strlen($plaintext) - $pad);
		print("plain=\"$plaintext\" (after pad removal)<br>");

		print('<h2>End Decryption</h2>');

		return $plaintext;
	}

	// --------------------------------------------------------------------

	/**
	 * Set the Mcrypt Cipher
	 *
	 * @param	int
	 * @return	CI_Encrypt
	 */
	public function set_cipher($cipher)
	{
		$this->_mcrypt_cipher = $cipher;
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Set the Mcrypt Mode
	 *
	 * @param	int
	 * @return	CI_Encrypt
	 */
	public function set_mode($mode)
	{
		$this->_mcrypt_mode = $mode;
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Get Mcrypt cipher Value
	 *
	 * @return	int
	 */
	protected function _get_cipher()
	{
		if ($this->_mcrypt_cipher === NULL)
		{
			return $this->_mcrypt_cipher = MCRYPT_RIJNDAEL_256;
		}

		return $this->_mcrypt_cipher;
	}

	// --------------------------------------------------------------------

	/**
	 * Get Mcrypt Mode Value
	 *
	 * @return	int
	 */
	protected function _get_mode()
	{
		if ($this->_mcrypt_mode === NULL)
		{
			return $this->_mcrypt_mode = MCRYPT_MODE_CBC;
		}

		return $this->_mcrypt_mode;
	}

	// --------------------------------------------------------------------

	/**
	 * Set the Hash type
	 *
	 * @param	string
	 * @return	void
	 */
	public function set_hash($type = 'sha1')
	{
		$this->_hash_type = in_array($type, hash_algos()) ? $type : 'sha1';
	}

	// --------------------------------------------------------------------

	/**
	 * Hash encode a string
	 *
	 * @param	string
	 * @return	string
	 */
	public function hash($str)
	{
		return hash($this->_hash_type, $str);
	}

}

/* End of file Encrypt.php */
/* Location: ./system/libraries/Encrypt.php */
