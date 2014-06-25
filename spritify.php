<?php

/**
 * A simple class that takes a given CSS stylesheet, searches it for small images that can be placed into
 * a spritesheet, and then generate both the spritesheet and a new CSS file that can be used
 * in-place. Sprites are assumed to be PNG files with alpha.
 *
 * As part of this process, the stylesheet is also partially optimised - removing comments, some unneccessary
 * whitespace etc.
 *
 * Jevon Wright <jevon@jevon.org> https://github.com/soundasleep/spritify/
 * Licensed under GPL-3.0
 *
 * INPUT
 *   --input path/to/css.css                 Input CSS file to read
 *   --png ../img/sprites.png                Sprite PNG to write, relative to input CSS
 *   [--output path/to/output.css]           Output CSS file to write to, or STDOUT if not defined
 *   [--max-width 32]                        Maximum width of PNG to consider as sprite
 *   [--max-height 32]                       Maximum height of PNG to consider as sprite
 *   [--padding 200]                         Vertical padding to place between sprites
 *
 * REQUIREMENTS
 *   sudo apt-get install php5-gd
 *
 * LIMITATIONS
 * - The input CSS needs to be valid
 * - Does not support unicode rules (this is a PCRE limitation)
 * - Uses very basic regular expressions, so will fail with strings that contain ;s
 * - Only supports spriting of PNG images, does not support GIF.
 * - The output CSS file needs to be in the same directory as the input CSS file.
 * - All images need to be relative and accessible relative to the CSS file (no Apache Aliases, etc).
 * - Supports @media queries, but sprited images within @media queries are not tested yet.
 * - Assumes background images are of one of the following formats:
 *   - background: #123 url('foo'); (colours are added as another property 'background-color')
 *   - background: url('foo');
 *   - background-image: url('foo');
 *   - background: url('foo') 0 0;
 *   - background: url('foo') 10px 20px;
 *   - background: url('foo') top 10px;
 * - The following are not supported:
 *   - background: #123 url('foo') bottom right; (all other words are assumed to be 'top left')
 *   - background: #123 url('foo') center center; (something aligned 'center center' is ignored)
 *   - background: url('foo') 0% 0%;
 *   - background: url('foo') 50% 100%;
 */

require_once(__DIR__ . "/functions.php");

// defaults
$input = false;
$output_sprites = false;			// relative to $relative
$output_file = false;				// if false, then use stdout

$max_sprite_width = 32;
$max_sprite_height = 32;

// amount of space to place between sprites; this can be a big number, doesn't seem to impact PNG filesize
$sprite_padding = 200;

if (count($argv) > 2) {
	$input = $argv[1];
	$output_sprites = $argv[2];
}

// get arguments from command line
for ($i = 1; $i < $argc; $i++) {
	if ($argv[$i] == "--help" || $argv[$i] == "/?") {
		// print help and exit
		print_help(STDOUT);
		exit(0);
	}

	if (substr($argv[$i], 0, 2) == "--") {
		$switch = substr($argv[$i], 2);
		if (($i + 1) < $argc) {
			$value = $argv[$i + 1];
			switch ($switch) {
				case "input":
					$input = $value;
					break;

				case "png":
					$output_sprites = $value;
					break;

				case "output":
					$output_file = $value;
					break;

				case "max-width":
					$max_sprite_width = $value;
					break;

				case "max-height":
					$max_sprite_height = $value;
					break;

				case "padding":
					$sprite_padding = $value;
					break;
			}
			$i++;	// skip next argument
		} else {
			error_log("No switch argument for '$switch' defined");
			exit(1);
		}
	} else {
		// must be a SVN url
		$svn_urls[] = $argv[$i];
	}
}

if (!$input || !$output_sprites) {
	print_help(STDERR);
	return 1;
}

// generate a random parameter to add to the output_png to ensure that the browser requests the most recent version
$rand_param = "?" . sprintf("%04x", rand(0,0xffff));

// calculate $relative - the relative path that this script needs to add to access CSS, images etc
$relative = "";
if (strpos($input, "/") !== false) {
	$relative = substr($input, 0, strrpos($input, "/") + 1);
}

// first, read the stylesheet, generating rules
if (!file_exists($input)) {
	throw new SpritifyException("Input CSS file '$input' does not exist");
}
$input_file = file_get_contents($input);

// drop all comments
$input_file = preg_replace("#/\\*.+?\\*/#", "", $input_file);

// split out media queries
$queries = array();

$buffer = "";
$current_query = "";
// find next @media
while (($next = strpos($input_file, "@media")) !== false) {
	// we were in a none @media
	$queries[] = array(
		'query' => false,
		'content' => substr($input_file, 0, $next),
	);

	// save the current statement
	$input_file = substr($input_file, $next);
	if (strpos($input_file, "{") === false) {
		throw new SpritifyException("Could not find an opening brace for @media query: " . substr($input_file, 0, 64));
	}
	$query = substr($input_file, 0, strpos($input_file, "{"));
	$input_file = substr($input_file, strpos($input_file, "{") + 1 /* skip the first brace */);
	$brace_count = 1;

	// iterate over content until we find the closing {
	for ($i = 0; $i < strlen($input_file); $i++) {
		if (substr($input_file, $i, 1) == "{") {
			$brace_count++;
		}
		if (substr($input_file, $i, 1) == "}") {
			$brace_count--;
			if ($brace_count == 0) {
				break;
			}
		}
	}

	// save this query
	$queries[] = array(
		'query' => $query,
		'content' => substr($input_file, 0, $i),
	);

	// and continue
	$input_file = substr($input_file, $i + 1 /* skip the last brace */);
}

// save the last one
$queries[] = array(
	'query' => false,
	'content' => $input_file,
);

// stores the CSS file as parsed
// as an array of (head => head, properties => (key => key, value => value))
// or as a (media => media query) or as (media_end => true)
// (there may be duplicate rules that will overwrite each other,
// at some point we could optimise overwriting properties if we can understand CSS)
$css = array();

foreach ($queries as $query) {
	$input_file = $query['content'];

	// skip empty content
	if (!trim($input_file)) {
		continue;
	}

	if ($query['query']) {
		$css[] = array(
			'media' => $query['query'],
		);
	}

	// check that it is valid according to some basic preg rules
	if (preg_match("#([a-z0-9\-_]+)\\s*:\\s*([^;]+)\\s*}#im", $input_file, $match)) {
		throw new SpritifyException("Input file '$input' is not valid CSS: missing ; at end of property list: '" . trim($match[0]) . "'");
	}

	$matches = false;
	if (!preg_match_all("#([^{]+?)\\s*{([^}]+)}#im", $input_file, $matches, PREG_SET_ORDER)) {
		throw new SpritifyException("Could not find any valid rules in '$input': '" . substr($input_file, 0, 64) . "'");
	}

	foreach ($matches as $rule) {
		$head = $rule[1];
		$body = $rule[2];

		// reduce whitespace in head
		$head = preg_replace("#[\r\n\t]+#im", " ", $head);
		$head = str_replace(", ", ",", $head);
		$head = preg_replace("#[\\s]+#im", " ", $head);
		$head = trim($head);

		// get all of the properties of this rule
		// NOTE will fail for things like 'content: ';';'
		$property_matches = false;
		if (!preg_match_all("#([a-z0-9\-_]+)\\s*:\\s*([^;]+);#im", $body, $property_matches, PREG_SET_ORDER)) {
			throw new SpritifyException("Rule '$head' had no valid properties");
		}

		$result = array(
			"head" => $head,
			"properties" => array(),
		);

		foreach ($property_matches as $property) {
			$result['properties'][] = array("key" => $property[1], "value" => $property[2]);
		}
		$css[] = $result;
	}

	if ($query['query']) {
		$css[] = array(
			'media_end' => $query['query'],
		);
	}

}

// process the CSS to find out which sprites we need to create
$sprites = array();
$sprited_elements = array();
foreach ($css as $rule_index => $rule) {
	if (isset($rule['media']) || isset($rule['media_end'])) {
		continue;
	}

	$head = $rule['head'];
	foreach ($rule['properties'] as $property_index => $property) {
		if ($property['key'] == 'background') {
			// get the URL of the image
			if (preg_match("#url\\(['\"]([^'\"]+)['\"]\\)#im", $property['value'], $match)) {
				$image_url = $match[1];
				// we only support PNG (this also means we don't need to support animated GIFs etc)
				if (substr(strtolower($image_url), -4) == ".png") {

					// is this a PNG image that is small enough to be placed onto a spritesheet?
					if (!file_exists($relative . $image_url)) {
						throw new SpritifyException("Rule '$head': Background image '$relative$image_url' does not exist");
					}
					$sizes = getimagesize($relative . $image_url);
					if ($sizes[0] <= $max_sprite_width && $sizes[1] <= $max_sprite_height) {
						// unless this is a 'center center' image
						if (preg_match("#\\)\\s*center\\s*center#im", strtolower($property['value']), $match)) {
							continue;
						}

						// or the property 'x-background-sprite' is set and false
						$disable_sprite = false;
						foreach ($rule['properties'] as $property2) {
							if ($property2['key'] == 'x-background-sprite' && $property2['value'] == 'false') {
								$disable_sprite = true;
							}
						}
						if ($disable_sprite) {
							continue;
						}

						if (array_search($image_url, $sprites, TRUE) === false) {
							$sprites[] = $image_url;
						}

						// this image needs to be sprited
						$sprited_elements[] = $rule['head'];

						// work out the height of the sprite image
						if (array_search($image_url, $sprites, TRUE) === false) {
							throw new SpritifyException("Unexpectedly couldn't find '$image_url' in sprites array");
						}
						$sprite_top = -array_search($image_url, $sprites, TRUE) * ($max_sprite_height + $sprite_padding);
						$sprite_left = 0;

						// now work out what this 'background' property will be replaced with
						if (preg_match("#\\)\\s*([0-9]+|top|bottom|center|left|right)(|px)\\s*([0-9]+|top|bottom|left|right|center)(|px)#im", strtolower($property['value']), $match)) {
							if (in_array($match[1], array('bottom', 'center', 'right'))) {
								fwrite(STDERR, "Warning: Rule '$head' used unsupported background position constant '" . $match[1] . "': assuming 'top'.\n");
							}
							if (in_array($match[3], array('bottom', 'center', 'right'))) {
								fwrite(STDERR, "Warning: Rule '$head' used unsupported background position constant '" . $match[3] . "': assuming 'left'.\n");
							}
							$sprite_left += $match[1];
							$sprite_top += $match[3];
						} else {
							// it has no positioning
						}

						// replace the rule
						$css[$rule_index]['properties'][$property_index]['key'] = 'background-position';
						$css[$rule_index]['properties'][$property_index]['value'] = sprintf("%d", $sprite_left) . ($sprite_left ? "px" : "") . " " . sprintf("%d", $sprite_top) . ($sprite_top ? "px" : "");

						// was there a background color in this rule as well? if so, add it
						if (preg_match("#^(\\#?[a-z0-9]+)\s+url#im", $property['value'], $match)) {
							$css[$rule_index]['properties'][] = array(
								'key' => 'background-color',
								'value' => $match[1],
							);
						}
					}
				}
			}
			if (preg_match("##im", $property['value'])) {
				// insert a new 'background-color' property after this one
			}
		}
	}
}

// add a new CSS rule for all sprited elements
// TODO does this need to be placed at the top of the spreadsheet?
if ($sprited_elements) {
	array_unshift($css, array(
		'head' => implode(',', $sprited_elements),
		'properties' => array(
			array(
				'key' => 'background',
				'value' => "url('" . $output_sprites . $rand_param . "') top left no-repeat",
			),
		),
	));
}


// generate the sprites image
// based on code from http://www.php.net/manual/en/function.imagesavealpha.php
if (count($sprites)) {
	$img = imagecreatetruecolor($max_sprite_width, count($sprites) * ($max_sprite_height + $sprite_padding));

	// enable alpha blending on the destination image.
	imagealphablending($img, true);

	// Allocate a transparent color and fill the new image with it.
	// Without this the image will have a black background instead of being transparent.
	$transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
	imagefill($img, 0, 0, $transparent);

	// go through each sprite image, copying it into the destination image
	foreach ($sprites as $index => $image_url) {
		$sprite = imagecreatefrompng($relative . $image_url);
		imagecopyresampled($img, $sprite, 0, $index * ($max_sprite_height + $sprite_padding), 0, 0, imagesx($sprite), imagesy($sprite), imagesx($sprite), imagesy($sprite));
	}

	// Save the alpha.
	imagealphablending($img, false);
	imagesavealpha($img, true);

	// Write the image.
	imagepng($img, $relative . $output_sprites);
	imagedestroy($img);
}

// finally, output the compressed CSS

if ($output_file) {
	$output_stream = fopen($output_file, "w");
} else {
	$output_stream = STDOUT;
}

fprintf($output_stream, "%s", "/* Generated by Spritify */\n");
foreach ($css as $rule) {
	if (isset($rule['media'])) {
		fprintf($output_stream, "%s", $rule['media'] . "{\n");
		continue;
	}
	if (isset($rule['media_end'])) {
		fprintf($output_stream, "}\n");
		continue;
	}
	fprintf($output_stream, "%s", $rule['head'] . "{");
	foreach ($rule['properties'] as $property) {
		// bail on any x-background-sprite custom properties
		if ($property['key'] == 'x-background-sprite') {
			continue;
		}

		fprintf($output_stream, "%s", $property['key'] . ":" . $property['value'] . ";");
	}
	fprintf($output_stream, "%s", "}\n");
}

// echo $input_file;

