<?php
class Log_test extends CI_TestCase {

	public function test_configuration()
	{
		if ( ! is_php('5.3'))
		{
			return $this->markTestSkipped("PHP 5.2 doesn't have ReflectionProperty::setAccessible() and can't run this test");
		}

		$path       = new ReflectionProperty('CI_Log', '_log_path');
		$path->setAccessible(TRUE);
		$threshold  = new ReflectionProperty('CI_Log', '_threshold');
		$threshold->setAccessible(TRUE);
		$date_fmt   = new ReflectionProperty('CI_Log', '_date_fmt');
		$date_fmt->setAccessible(TRUE);
		$file_ext   = new ReflectionProperty('CI_Log', '_file_ext');
		$file_ext->setAccessible(TRUE);
		$file_perms = new ReflectionProperty('CI_Log', '_file_permissions');
		$file_perms->setAccessible(TRUE);
		$enabled    = new ReflectionProperty('CI_Log', '_enabled');
		$enabled->setAccessible(TRUE);

		$log_path = '/root/';
		$this->ci_set_config('log_path', $log_path);
		$this->ci_set_config('log_threshold', 'z');
		$this->ci_set_config('log_date_format', 'd.m.Y');
		$this->ci_set_config('log_file_extension', '');
		$this->ci_set_config('log_file_permissions', '');
		$instance = new CI_Log();
				
		$real_enabled = FALSE;
		file_exists($log_path) OR mkdir($log_path, 0755, TRUE);
		if (is_dir($log_path) && is_really_writable($log_path))
		{
			$real_enabled = TRUE;
		}

		$this->assertEquals($path->getValue($instance), $log_path);
		$this->assertEquals($threshold->getValue($instance), 1);
		$this->assertEquals($date_fmt->getValue($instance), 'd.m.Y');
		$this->assertEquals($file_ext->getValue($instance), 'php');
		$this->assertEquals($file_perms->getValue($instance), 0644);
		$this->assertEquals($enabled->getValue($instance), $real_enabled);

		$log_path = APPPATH.'logs/';
		$this->ci_set_config('log_path', '');
		$this->ci_set_config('log_threshold', '0');
		$this->ci_set_config('log_date_format', '');
		$this->ci_set_config('log_file_extension', '.log');
		$this->ci_set_config('log_file_permissions', 0600);
		$instance = new CI_Log();
		
		$real_enabled = FALSE;
		file_exists($log_path) OR mkdir($log_path, 0755, TRUE);
		if (is_dir($log_path) && is_really_writable($log_path))
		{
			$real_enabled = TRUE;
		}

		$this->assertEquals($path->getValue($instance), $log_path);
		$this->assertEquals($threshold->getValue($instance), 0);
		$this->assertEquals($date_fmt->getValue($instance), 'Y-m-d H:i:s');
		$this->assertEquals($file_ext->getValue($instance), 'log');
		$this->assertEquals($file_perms->getValue($instance), 0600);
		$this->assertEquals($enabled->getValue($instance), $real_enabled);
	}

	// --------------------------------------------------------------------

	public function test_format_line()
	{
		if ( ! is_php('5.3'))
		{
			return $this->markTestSkipped("PHP 5.2 doesn't have ReflectionProperty::setAccessible() and can't run this test");
		}

		$this->ci_set_config('log_path', '');
		$this->ci_set_config('log_threshold', 0);
		$instance = new CI_Log();

		$format_line = new ReflectionMethod($instance, '_format_line');
		$format_line->setAccessible(TRUE);
		$this->assertEquals(
			$format_line->invoke($instance, 'LEVEL', 'Timestamp', 'Message'),
			"LEVEL - Timestamp --> Message\n"
		);
	}
}