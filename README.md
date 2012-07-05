# Instafilter

Replicate Instragram-style filters in PHP

# Install


# Usage

Fork and pull request any useful changes you make.

```php
\Instafilter\Image::load('kittens.png')
	->resize(200, 200)
	->apply_filter(new Instafilter\Filter\Earlybird())
	->save('new.jpg');
```

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
- Abstract to separate from Image class