spritify
========

A simple PHP script that can be used to process a CSS stylesheet, perform some basic optimisation, and generate a spritesheet of all sprites used in that stylesheet.

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
* Supports high resolution sprite scaling through `background-size`
* Disable spritify [for individual rules](http://code.google.com/p/spritify/source/detail?r=7) by using `x-background-sprite: false;`

## Installing

Include [spritify](https://packagist.org/packages/soundasleep/spritify) as a requirement in your project `composer.json`,
and run `composer update` to install it into your project:

```json
{
  "require": {
    "soundasleep/spritify": "dev-master"
  }
}
```

You can then use _spritify_ as part of your build script:

```
php -f vendor/soundasleep/spritify/spritify.php --input default.css --png img/all_sprites.png --output default-compiled.css
```

You can also use the new [grunt-contrib-spritify task](https://github.com/soundasleep/grunt-contrib-spritify) in your Gruntfile.

For example, [CryptFolio](https://cryptfolio.com) uses Spritify to transform [this stylesheet](https://github.com/soundasleep/openclerk/blob/master/site/css/default.scss) into a [compressed stylesheet](http://cryptfolio.com/styles/default.css) with a [PNG spritesheet](http://cryptfolio.com/img/default-sprites.png).

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
  * `background-size: 16px 16px;`
* The following are not supported:
	* `background: #123 url('foo') bottom right;` (all other words are assumed to be 'top left')
	* `background: #123 url('foo') center center;` (something aligned 'center center' is ignored)
	* `background: url('foo') 0% 0%;`
	* `background: url('foo') 50% 100%;`
  * `background-size: 100%;`
  * `background-size: contain;`

## Tests

Very basic tests are provided with phpunit. The source code for these tests are available in [tests/](tests/).

```
composer install
vendor/bin/phpunit
```

## See Also

* [CSSTidy](http://csstidy.sourceforge.net/)
* [OptiPNG](http://optipng.sourceforge.net/)
