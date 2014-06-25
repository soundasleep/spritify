<?php

/**
 * A basic test suite for spritify.
 */

$tests = array(
	"single-rule",
	"media-query",
);

foreach ($tests as $test) {
	echo "[Test $test]\n";

	$input_file = "tests/$test.css";
	$expected_file = "tests/$test.expected.css";
	$output_file = "tests/$test.output.css";

	if (!file_exists($input_file)) {
		throw new Exception("$input_file does not exist");
	}
	if (!file_exists($expected_file)) {
		throw new Exception("$expected_file does not exist");
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
	);
	$argc = count($argv);

	require(__DIR__ . "/spritify.php");

	if (!file_exists($output_file)) {
		throw new Exception("$output_file was not created");
	}

	$output = trim(file_get_contents($output_file));
	$expected = trim(file_get_contents($expected_file));

	if ($output != $expected) {
		passthru("diff $expected_file $output_file");
		throw new Exception("Test results did not match");
	}
}

echo "Complete!\n";
