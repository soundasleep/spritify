<?php

function print_help($stream) {
	fprintf($stream, "Spritify: A tool to process CSS stylesheets, optimise them and generate spritesheets.\n");
	fprintf($stream, "  Usage: php -f spritify.php [input_css] [output_png] ([--max-width 32] [--max-height 32] [--padding 200] [--output filename.css])");
	fprintf($stream, "  Output: Compressed CSS stylesheet");
}

class SpritifyException extends Exception { }
