<?php

function print_help($stream) {
	fprintf($stream, "Spritify: A tool to process CSS stylesheets, optimise them and generate spritesheets.\n");
	fprintf($stream, "  Usage: php -f spritify.php [input_css] [output_png] ([--max-width 32] [--max-height 32] [--padding 200] [--output filename.css])");
	fprintf($stream, "  Output: Compressed CSS stylesheet");
}

class SpritifyException extends Exception { }

function scale_size($size, $scale_width, $scale_height) {
	if (preg_match("#(-?[0-9]+)(|px) (-?[0-9]+)(|px)#im", $size, $match)) {
		return print_dimensions($match[1] * $scale_width, $match[3] * $scale_height);
	} else {
		throw new SpritifyException("Invalid size to scale '$size'");
	}
}

function rule_has_property($rule, $key, $value) {
	foreach ($rule['properties'] as $property_index => $property) {
		if ($property['key'] == $key && $property['value'] == $value) {
			return true;
		}
	}
	return false;
}

/**
 * If $ is 0, return "0", otherwise return $px.
 */
function print_dimensions($x, $y) {
	$x = (int) $x;
	$y = (int) $y;
	return (($x == 0) ? "0" : ($x . "px")) . " " .
		(($y == 0) ? "0" : ($y . "px"));
}
