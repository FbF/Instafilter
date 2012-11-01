# Instafilter

Replicate Instragram-style filters in PHP. Translates photoshop functions to PHP.

# Installation

The tmp directory must be writable.

# Usage

Fork and pull request any useful changes you make.

```php
\Instafilter\Image::load('kittens.png')
	->resize(200, 200)
	->apply_filter(new Instafilter\Filter\Earlybird())
	->save('new.jpg');
```

n.b. applying the filter is quite slow; You'll get a significant performance gain by resizing _before_ applying the filter.

If you don't use an autoloader, you'll need to load in the classes:
```php
require_once('classes/Image.php');
require_once('classes/Filter.php');
require_once('classes/Filter/Earlybird.php');
require_once('classes/Filter/Inkwell.php');
```

# Todo

- Add more filters
- (Somehow) Improve performance
- Improve interface
- Implement more photoshop functions in imagemagick
	- Implement 'curves' properly by using polynomial regression to get the coefficients needed for imagick's FX function
- Abstract and decouple from Image class
- Make composer/packagist compatible

# Author

Rob McCann<br>
http://robmccann.co.uk

# Thanks

- FuelPHP for use of (parts of) their image class.
- Daniel Box for his Photoshop actions for instagram filters (http://dbox.tumblr.com/post/5426249009/instagram-filters-as-photoshop-actions)