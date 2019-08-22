<?php

namespace Musly\Tests\Integration;

use PHPUnit\Framework\TestCase;

use Musly\{
    Collection,
    Exception\FileNotFoundException,
    Exception\FileNotFoundInCollectionException,
    Musly
};

class MuslyTest extends TestCase
{
    /**
     * @var string
     */
    private static $binary;

    public static function setUpBeforeClass(): void
    {
        self::$binary = $_ENV['musly_binary'] ?: trim((string) shell_exec('which musly'));
    }

    public function setUp(): void
    {
        if (!self::$binary) {
            static::markTestSkipped('`musly` command not found, skipping.');
        }

        static::clean();
    }

    public static function tearDownAfterClass(): void
    {
        static::clean();
    }

    /**
     * @dataProvider dataInitializeCollectionSuccess
     */
    public function testInitializeCollectionSuccess($params, $expected)
    {
        $musly = new Musly([ 'binary' => self::$binary ]);
        $collection = new Collection($params['collection']);

        $result = $musly->initializeCollection($collection);
        static::assertSame($expected['result'], $result);

        $pathname = $params['collection']['pathname'] ?? Collection::DEFAULT_PATHNAME;
        static::assertFileExists($pathname);
    }

    public function dataInitializeCollectionSuccess()
    {
        $pathname = uniqid('collection', true);

        return [
            'initialize default collection' => [
                [
                    'collection' => [],
                ],
                [
                    'result' => true,
                ]
            ],
            'initialize collection with custom pathname and similarity method' => [
                [
                    'collection' => [ 'pathname' => $pathname, 'similarityMethod' => 'mandelellis' ],
                ],
                [
                    'result' => true,
                ]
            ]
        ];
    }

    /**
     * @dataProvider dataAnalyzeSuccess
     */
    public function testAnalyzeSuccess($params, $expected)
    {
        $musly = new Musly([ 'binary' => self::$binary ]);
        $collection = new Collection();

        $musly->initializeCollection($collection);

        $tracks = $musly->analyze($params['pathname'], $params['ext']);

        $result = [
            'OK' => 0,
            'SKIPPED' => 0,
            'FAILED' => 0,
        ];

        foreach ($tracks as $track) {
            $result[$track['result']]++;
        }

        static::assertSame($expected['result'], $result);
    }

    public function dataAnalyzeSuccess()
    {
        $directory = 'tests/integration/resources';
        $ext = 'wav';

        return [
            'analyze pathname' => [
                [
                    'pathname' => $directory,
                    'ext' => '',
                ],
                [
                    'result' => [
                        'OK' => 5,
                        'SKIPPED' => 0,
                        'FAILED' => 0,
                    ]
                ]
            ],
            'analyze directory with extension filter' => [
                [
                    'pathname' => $directory,
                    'ext' => $ext,
                ],
                [
                    'result' => [
                        'OK' => 0,
                        'SKIPPED' => 0,
                        'FAILED' => 0,
                    ]
                ]
            ],
        ];
    }

    public function testAnalyzeError()
    {
        $this->expectException(FileNotFoundException::class);

        $musly = new Musly([ 'binary' => self::$binary ]);
        $collection = new Collection();

        $musly->initializeCollection($collection);
        $musly->setCollection($collection);

        $pathname = uniqid('/path/to/file', true);

        $musly->analyze($pathname);
    }

    /**
     * @dataProvider dataGetSimilarTracksSuccess
     */
    public function testGetSimilarTracksSuccess($params, $expected)
    {
        $musly = new Musly([ 'binary' => self::$binary ]);
        $collection = new Collection($params['collection']);

        $musly->initializeCollection($collection);
        $musly->setCollection($collection);

        $musly->analyze($params['pathname']);

        $tracks = $musly->getSimilarTracks($params['track'], $params['num']);

        if (isset($params['collection']['jukeboxPathname'])) {
            static::assertFileExists($params['collection']['jukeboxPathname']);
        }

        static::assertCount($expected['count'], $tracks);

        foreach ($tracks as $track) {
            static::assertSame($expected['keys'], array_keys($track));
        }
    }

    public function dataGetSimilarTracksSuccess()
    {
        $num = random_int(1, 4);
        $pathname = 'tests/integration/resources';
        $track = sprintf('%s/1.mp3', $pathname);

        return [
            'get similar tracks' => [
                [
                    'collection' => [ ],
                    'pathname' => $pathname,
                    'track' => $track,
                    'num' => null,
                ],
                [
                    'count' => 4,
                    'keys' => [ 'track-id', 'track-similarity', 'track-origin' ],
                ]
            ],
            'get similar tracks with jukebox and limit' => [
                [
                    'collection' => [ 'jukeboxPathname' => 'collection.jbox' ],
                    'pathname' => $pathname,
                    'track' => $track,
                    'num' => $num,
                ],
                [
                    'count' => $num,
                    'keys' => [ 'track-id', 'track-similarity', 'track-origin' ],
                ]
            ],
        ];
    }

    public function testGetSimilarTracksError()
    {
        $this->expectException(FileNotFoundInCollectionException::class);

        $musly = new Musly([ 'binary' => self::$binary ]);
        $collection = new Collection();

        $musly->initializeCollection($collection);
        $musly->setCollection($collection);

        $musly->analyze('tests/integration/resources/1.mp3');

        $pathname = 'tests/integration/resources/2.mp3';

        $musly->getSimilarTracks($pathname);
    }

    /**
     * @dataProvider dataGetAllTracks
     */
    public function testGetAllTracks($params, $expected)
    {
        $musly = new Musly([ 'binary' => self::$binary ]);
        $collection = new Collection();

        $musly->initializeCollection($collection);
        $musly->setCollection($collection);

        $musly->analyze($params['pathname']);

        $tracks = $musly->getAllTracks();

        static::assertCount($expected['count'], $tracks);

        foreach ($tracks as $track) {
            static::assertSame($expected['keys'], array_keys($track));
        }
    }

    public function dataGetAllTracks()
    {
        $pathname = 'tests/integration/resources';

        return [
            'get all tracks from collection' => [
                [
                    'pathname' => $pathname,
                ],
                [
                    'count' => 5,
                    'keys' => [ 'track-id', 'track-size', 'track-origin' ],
                ]
            ],
        ];
    }

    private static function clean()
    {
        array_map('unlink', glob('./collection*'));
    }
}
