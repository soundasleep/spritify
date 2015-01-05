<?php

/**
 * Tests all of the tests from test/resources
 */
class ResourcesTest extends PHPUnit_Framework_TestCase {

	function testSingleRule() {
		$this->doTest("single-rule");
	}

	function testMediaQuery() {
		$this->doTest("media-query");
	}

	function testSizes() {
		$this->doTest("sizes");
	}

	/**
	 * Tests issue #1
	 */
	function testBlockComment() {
		$this->doTest("block-comment");
	}

	/**
	 * Tests issue #2
	 */
	function testMulti() {
		$this->doTest("multi");
	}

	/**
	 * Tests issue #2
	 */
	function testMulti2() {
		$this->doTest("multi2");
	}

	function doTest($test) {

		$input_file = __DIR__ . "/resources/$test.css";
		$expected_file = __DIR__ . "/resources/$test.expected.css";
		$output_file = __DIR__ . "/resources/$test.output.css";

		if (!file_exists($input_file)) {
			$this->fail("$input_file does not exist");
		}
		if (!file_exists($expected_file)) {
			$this->fail("$expected_file does not exist");
		}
		if (file_exists($output_file)) {
			unlink($output_file);
		}

		// set $argv as necessary
		$argv = array(
			"",
			"--input",
			$input_file,
			"--output",
			$output_file,
			"--png",
			"sprites-$test.png",
			"--no-rand-param",
		);
		$argc = count($argv);

		require(__DIR__ . "/../spritify.php");

		if (!file_exists($output_file)) {
			$this->fail("$output_file was not created");
		}

		$output = trim(file_get_contents($output_file));
		$expected = trim(file_get_contents($expected_file));

		if ($output != $expected) {
			passthru("diff $expected_file $output_file");
			$this->fail("Test results did not match");
		}

	}

}
