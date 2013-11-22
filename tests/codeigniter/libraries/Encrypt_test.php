<?php

class Encrypt_test extends CI_TestCase {

	public function set_up()
	{
		$this->encrypt = new Mock_Libraries_Encrypt();
		$this->ci_instance_var('encrypt', $this->encrypt);

		$this->ci_set_config('encryption_key', "Encryptin'glike@boss!");
		$this->msg = 'My secret message';
		$this->mcrypt = extension_loaded('mcrypt');
	}

	// --------------------------------------------------------------------

	public function test_encode()
	{
		$this->assertNotEquals($this->msg, $this->encrypt->encode($this->msg));
	}

	// --------------------------------------------------------------------

	public function test_decode()
	{
		$encoded_msg = $this->encrypt->encode($this->msg);
		$this->assertEquals($this->msg, $this->encrypt->decode($encoded_msg));
	}

	// --------------------------------------------------------------------

	public function test_optional_key()
	{
		$key = 'Ohai!ù0129°03182%HD1892P0';
		$encoded_msg = $this->encrypt->encode($this->msg, $key);
		$this->assertEquals($this->msg, $this->encrypt->decode($encoded_msg, $key));
	}

	// --------------------------------------------------------------------

	public function test_default_cipher()
	{
		if ( ! $this->mcrypt)
		{
			$this->markTestSkipped('MCrypt not available');
			return;
		}

		$this->assertEquals('rijndael-128', $this->encrypt->get_cipher());
	}

	// --------------------------------------------------------------------

	public function test_set_cipher()
	{
		if ( ! $this->mcrypt)
		{
			$this->markTestSkipped('MCrypt not available');
			return;
		}

		$this->encrypt->set_cipher(MCRYPT_BLOWFISH);
		$this->assertEquals('blowfish', $this->encrypt->get_cipher());
	}

	// --------------------------------------------------------------------

	public function test_default_mode()
	{
		if ( ! $this->mcrypt)
		{
			$this->markTestSkipped('MCrypt not available');
			return;
		}

		$this->assertEquals('cbc', $this->encrypt->get_mode());
	}

	// --------------------------------------------------------------------

	public function test_set_mode()
	{
		if ( ! $this->mcrypt)
		{
			$this->markTestSkipped('MCrypt not available');
			return;
		}

		$this->encrypt->set_mode(MCRYPT_MODE_CFB);
		$this->assertEquals('cfb', $this->encrypt->get_mode());
	}

}
