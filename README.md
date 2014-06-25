spritify
========

A simple PHP script that can be used to process a CSS stylesheet, perform some basic optimisation, and generate a spritesheet of all sprites used in that stylesheet.

This Git repository has been cloned from the original SVN repository: http://code.google.com/p/spritify/

*NOTE* This project is very experimental - feel free to [report any issues or bugs](http://code.google.com/p/spritify/issues/list).

## Requirements
PHP, GD library (`apt-get install php5-gd`)

## Features

* Designed to be dropped into an existing project without requiring significant CSS changes
* Removes CSS comments
* Removes most unnecessary whitespace
* Generates spritesheets from PNG images less than a given size (default 32x32)
* Supports @media queries
* Supports full alpha PNG images
* Disable spritify [http://code.google.com/p/spritify/source/detail?r=7 for individual rules] by using `x-background-sprite: false;`

## Usage

You can use this in part of your build script:

```
svn co http://spritify.googlecode.com/svn/trunk/ spritify
php -f spritify/spritify.php default.css img/all_sprites.png > default-compiled.css
```

For example, [CryptFolio](https://cryptfolio.com) uses Spritify to transform [this stylesheet](http://cryptfolio.com/default.css) into a [compressed stylesheet](http://cryptfolio.com/default-sprites.css) with a [PNG spritesheet](http://cryptfolio.com/img/all_sprites.png).

## Limitations

* The input CSS needs to be valid.
* Uses very basic regular expressions, so will fail with strings that contain ;s, and does not support Unicode.
* Only supports spriting of PNG images, does not support GIF.
* The output CSS file needs to be in the same directory as the input CSS file.
* All images need to be relative and accessible relative to the CSS file (no Apache Aliases, etc).
* Sprited images within @media queries are not tested yet.
* Assumes background images are of one of the following formats:
	* `background: #123 url('foo');` (colours are added as another property 'background-color')
	* `background: url('foo');`
	* `background-image: url('foo');`
	* `background: url('foo') 0 0;`
	* `background: url('foo') 10px 20px;`
	* `background: url('foo') top 10px;`
* The following are not supported:
	* `background: #123 url('foo') bottom right;` (all other words are assumed to be 'top left')
	* `background: #123 url('foo') center center;` (something aligned 'center center' is ignored)
	* `background: url('foo') 0% 0%;`
	* `background: url('foo') 50% 100%;`

## Tests

Very basic tests can be run by executing `tests.php`. The source code for these tests are available in [tests/](tests/).

## See Also

* [http://csstidy.sourceforge.net/ CSSTidy]
* [http://optipng.sourceforge.net/ OptiPNG]
