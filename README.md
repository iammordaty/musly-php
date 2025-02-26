# iammordaty/musly-php

musly-php is simple PHP wrapper around the `musly` command line tool.

> Musly is a fast and high-quality audio music similarity library written in C/C++.
>
> -- [Musly website](http://musly.org)

## Installation

It's recommended that you use Composer to install musly-php.

```bash
$ composer require iammordaty/musly-php
```

## Requirements

* PHP 7.1
* [Musly](https://github.com/dominikschnitzer/musly) with [commandline tool](http://www.musly.org/about.html)

## Basic Usage

```php
use \Musly\Musly;

$musly = new Musly();
$musly->initializeCollection();

$musly->analyze('/path/to/dir/or/track.mp3');

$similarTracks = $musly->getSimilarTracks('/path/to/track.mp3');
```

## Advanced Usage

```php
use \Musly\{
    Collection,
    Exception\FileNotFoundException,
    Exception\FileNotFoundInCollectionException,
    Exception\MuslyProcessFailedException,
    Musly
};

$collection = new Collection([
    'pathname' => '/path/to/collection.musly',
    'similarityMethod' => Collection::SIMILARITY_METHOD_TIMBRE,
    'jukeboxPathname' => '/path/to/collection.jbox',
]);

// ... or
// $collection = new Collection('/path/to/collection.musly');
// $collection->setSimilarityMethod(Collection::SIMILARITY_METHOD_TIMBRE);
// $collection->setJukeboxPathname('/path/to/collection.jbox');

$musly = new Musly([ 'binary' => '/path/to/musly/binary' ]);

try {
    if (!$collection->isInitialized()) {
        $musly->initializeCollection($collection);
    }

    $musly->setCollection($collection);
    $musly->analyze('/path/to/dir/', 'mp3');
    $musly->analyze('/path/to/track.mp3');

    $similarTracks = $musly->getSimilarTracks('/path/to/track.mp3', 20);
    $collectionTracks = $musly->getAllTracks();
}
catch (FileNotFoundException | FileNotFoundInCollectionException $e) {
    // handle expception
}
catch (MuslyProcessFailedException $e) {
    // handle expception
}
```

See the `musly` commandline tool help for more information.

## Tests

To execute the test suite, you'll need phpunit.

```bash
$ phpunit
```

## Further informations

- [Musly website](http://www.musly.org/)
- [Musly repository](https://github.com/dominikschnitzer/musly)
- [`musly` commandline tool usage](http://www.musly.org/about.html)

## License

iammordaty/musly-php is licensed under the MIT License.
