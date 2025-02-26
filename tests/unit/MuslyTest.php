<?php

namespace Musly\Tests\Unit;

use Musly\Collection;
use Musly\Exception\CollectionNotInitializedException;
use Musly\Exception\FileNotFoundException;
use Musly\Exception\FileNotFoundInCollectionException;
use Musly\Exception\MuslyProcessFailedException;
use Musly\Musly;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class MuslyTest extends TestCase
{
    /**
     * @dataProvider dataCreateSuccess
     */
    public function testCreateSuccess($params, $expected)
    {
        $musly = new Musly($params);

        self::assertSame($expected['binary'], $musly->getBinary());
        self::assertSame($expected['collection']->getPathname(), $musly->getCollection()->getPathname());
    }

    public function dataCreateSuccess(): array
    {
        $binary = uniqid('/path/to/musly', true);
        $collection = $this->getCollectionMock([ 'initialized' => true ]);
        $pathname = uniqid('/path/to/collection', true);
        $customCollection = $this->getCollectionMock([ 'initialized' => true, 'pathname' => $pathname ]);

        return [
            'create with default params' => [
                [],
                [
                    'binary' => Musly::DEFAULT_BINARY,
                    'collection' => $collection,
                ]
            ],
            'create with custom params' => [
                [
                    'binary' => $binary,
                    'collection' => $customCollection,
                ],
                [
                    'binary' => $binary,
                    'collection' => $customCollection,
                ]
            ],
        ];
    }

    public function testCreateError()
    {
        $this->expectException(CollectionNotInitializedException::class);

        $collection = $this->getCollectionMock([ 'initialized' => false ]);

        $params = [
            'collection' => $collection,
        ];

        new Musly($params);
    }

    /**
     * @dataProvider dataConfigureSuccess
     */
    public function testConfigureSuccess($params, $expected)
    {
        $musly = new Musly();

        $musly->setBinary($params['binary']);
        $musly->setCollection($params['collection']);

        self::assertSame($expected['binary'], $musly->getBinary());
        self::assertSame($expected['collection'], $musly->getCollection());
    }

    public function dataConfigureSuccess(): array
    {
        $binary = uniqid('/path/to/musly', true);
        $pathname = uniqid('/path/to/collection', true);
        $collection = $this->getCollectionMock([ 'initialized' => true, 'pathname' => $pathname ]);

        return [
            'configure with custom params' => [
                [
                    'binary' => $binary,
                    'collection' => $collection,
                ],
                [
                    'binary' => $binary,
                    'collection' => $collection,
                ]
            ],
        ];
    }

    public function testConfigureError()
    {
        $this->expectException(CollectionNotInitializedException::class);

        $collection = $this->getCollectionMock([ 'initialized' => false ]);

        $musly = new Musly();
        $musly->setCollection($collection);
    }

    /**
     * @dataProvider dataInitializeCollectionSuccess
     */
    public function testInitializeCollectionSuccess($params, $expected)
    {
        $musly = $this->getMockBuilder(Musly::class)
            ->setConstructorArgs([ [ 'binary' => $params['binary'] ] ])
            ->onlyMethods([ 'runProcess' ])
            ->getMock();

        $expectedTimes = $expected['commandline'] ? self::once() : self::never();

        $musly
            ->expects($expectedTimes)
            ->method('runProcess')
            ->with($expected['commandline']);

        self::assertSame($expected['result'], $musly->initializeCollection($params['collection']));
    }

    public function dataInitializeCollectionSuccess(): array
    {
        $binary = uniqid('/path/to/binary', true);
        $pathname = uniqid('/path/to/collection', true);
        $jukeboxPathname = uniqid('/path/to/jukebox', true);

        return [
            'initialize default collection' => [
                [
                    'binary' => $binary,
                    'collection' => null,
                ],
                [
                    'commandline' => sprintf('%s -c "collection.musly" -N', $binary),
                    'result' => true,
                ]
            ],
            'initialize collection with custom pathname and default similarity method' => [
                [
                    'binary' => $binary,
                    'collection' => $this->getCollectionMock([ 'pathname' => $pathname ])
                ],
                [
                    'commandline' => sprintf('%s -c "%s" -N', $binary, $pathname),
                    'result' => true,
                ]
            ],
            'initialize collection with custom similarity method and default pathname' => [
                [
                    'binary' => $binary,
                    'collection' => $this->getCollectionMock([ 'similarityMethod' => 'mandelellis' ])
                ],
                [
                    'commandline' => sprintf('%s -c "collection.musly" -n "mandelellis"', $binary),
                    'result' => true,
                ]
            ],
            'initialize collection with jukebox' => [
                [
                    'binary' => $binary,
                    'collection' => $this->getCollectionMock([ 'pathname' => $pathname, 'similarityMethod' => 'mandelellis', 'jukeboxPathname' => $jukeboxPathname ])
                ],
                [
                    // no change, the jukebox is only used to speed up the similarity calculation process
                    'commandline' => sprintf('%s -c "%s" -n "mandelellis"', $binary, $pathname),
                    'result' => true,
                ]
            ],
            'dont reinitialize collections' => [
                [
                    'binary' => $binary,
                    'collection' => $this->getCollectionMock([ 'initialized' => true ])
                ],
                [
                    'commandline' => null,
                    'result' => false,
                ]
            ],
        ];
    }

    public function testInitializeCollectionError()
    {
        $this->expectException(MuslyProcessFailedException::class);

        $process = $this->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->getMock();

        $musly = $this->getMockBuilder(Musly::class)
            ->onlyMethods([ 'runProcess' ])
            ->getMock();

        $musly
            ->method('runProcess')
            ->will(self::throwException(new ProcessFailedException($process)));

        $collection = $this->getCollectionMock([ 'initialized' => false ]);

        $musly->initializeCollection($collection);
    }

    /**
     * @dataProvider dataAnalyzeSuccess
     */
    public function testAnalyzeSuccess($params, $expected)
    {
        $process = $this->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->getMock();

        $process
            ->method('getOutput')
            ->willReturn($params['stdout']);

        $musly = $this->getMockBuilder(Musly::class)
            ->setConstructorArgs([ [ 'binary' => $params['binary'], 'collection' => $params['collection'] ] ])
            ->onlyMethods([ 'ensurePathname', 'runProcess' ])
            ->getMock();

        $musly
            ->expects(self::once())
            ->method('runProcess')
            ->with($expected['commandline'])
            ->willReturn($process);

        $tracks = $musly->analyze($params['pathname'], $params['ext']);

        $result = [
            'OK' => 0,
            'SKIPPED' => 0,
            'FAILED' => 0,
        ];

        foreach ($tracks as $track) {
            $result[$track['result']]++;
        }

        self::assertSame($expected['result'], $result);
    }

    public function dataAnalyzeSuccess(): array
    {
        $binary = uniqid('/path/to/binary', true);
        $collection = $this->getCollectionMock([ 'initialized' => true, 'pathname' => uniqid('/path/to/collection', true) ]);
        $directory = uniqid('/path/to/directory/%s', true);
        $directoryWithSlash = sprintf('%s/', $directory);
        $ext = uniqid('', true);
        $extWithDot = sprintf('.%s', $ext);

        return [
            'analyze pathname' => [
                [
                    'binary' => $binary,
                    'collection' => $collection,
                    'pathname' => $directory,
                    'ext' => '',
                    'stdout' => file_get_contents('./tests/unit/resources/analyze-pathname.stdout'),
                ],
                [
                    'commandline' => sprintf('%s -c "%s" -a "%s"', $binary, $collection->getPathname(), $directory),
                    'result' => [
                        'OK' => 5,
                        'SKIPPED' => 0,
                        'FAILED' => 0,
                    ]
                ]
            ],
            'analyze directory and trim trailing slash' => [
                [
                    'binary' => $binary,
                    'collection' => $collection,
                    'pathname' => $directoryWithSlash,
                    'ext' => '',
                    'stdout' => file_get_contents('./tests/unit/resources/analyze-pathname.stdout'),
                ],
                [
                    'commandline' => sprintf('%s -c "%s" -a "%s"', $binary, $collection->getPathname(), $directory),
                    'result' => [
                        'OK' => 5,
                        'SKIPPED' => 0,
                        'FAILED' => 0,
                    ]
                ]
            ],
            'analyze directory with extension filter' => [
                [
                    'binary' => $binary,
                    'collection' => $collection,
                    'pathname' => $directory,
                    'ext' => $extWithDot,
                    'stdout' => file_get_contents('./tests/unit/resources/analyze-pathname.stdout'),
                ],
                [
                    'commandline' => sprintf('%s -c "%s" -a "%s" -x %s', $binary, $collection->getPathname(), $directory, $ext),
                    'result' => [
                        'OK' => 5,
                        'SKIPPED' => 0,
                        'FAILED' => 0,
                    ]
                ]
            ],
            'analyze pathname with already analyzed tracks' => [
                [
                    'binary' => $binary,
                    'collection' => $collection,
                    'pathname' => $directory,
                    'ext' => '',
                    'stdout' => file_get_contents('./tests/unit/resources/analyze-pathname-with-already-analyzed-tracks.stdout'),
                ],
                [
                    'commandline' => sprintf('%s -c "%s" -a "%s"', $binary, $collection->getPathname(), $directory),
                    'result' => [
                        'OK' => 3,
                        'SKIPPED' => 2,
                        'FAILED' => 0,
                    ]
                ]
            ],
            'analyze pathname without tracks' => [
                [
                    'binary' => $binary,
                    'collection' => $collection,
                    'pathname' => $directory,
                    'ext' => '',
                    'stdout' => file_get_contents('./tests/unit/resources/analyze-pathname-without-tracks.stdout'),
                ],
                [
                    'commandline' => sprintf('%s -c "%s" -a "%s"', $binary, $collection->getPathname(), $directory),
                    'result' => [
                        'OK' => 0,
                        'SKIPPED' => 0,
                        'FAILED' => 0,
                    ]
                ]
            ],
            'analyze directory with failed tracks' => [
                [
                    'binary' => $binary,
                    'collection' => $collection,
                    'pathname' => $directory,
                    'ext' => '',
                    'stdout' => file_get_contents('./tests/unit/resources/analyze-pathname-with-failed-tracks.stdout'),
                ],
                [
                    'commandline' => sprintf('%s -c "%s" -a "%s"', $binary, $collection->getPathname(), $directory),
                    'result' => [
                        'OK' => 2,
                        'SKIPPED' => 1,
                        'FAILED' => 2,
                    ]
                ]
            ],
        ];
    }

    /**
     * @dataProvider dataCommonErrors
     * @dataProvider dataFileNotFoundError
     */
    public function testAnalyzeError($params, $expected)
    {
        $this->expectException($expected['exception']);

        $musly = $this->getMockBuilder(Musly::class)
            ->onlyMethods($params['methods'])
            ->getMock();

        $musly
            ->expects($expected['times'])
            ->method('runProcess')
            ->will(self::throwException(new ProcessFailedException($params['process'])));

        $musly->analyze($params['pathname']);
    }

    /**
     * @dataProvider dataGetSimilarTracksSuccess
     */
    public function testGetSimilarTracksSuccess($params, $expected)
    {
        $process = $this->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->getMock();

        $process
            ->method('getOutput')
            ->willReturn($params['stdout']);

        $musly = $this->getMockBuilder(Musly::class)
            ->setConstructorArgs([ [ 'binary' => $params['binary'], 'collection' => $params['collection'] ] ])
            ->onlyMethods([ 'ensureCollectionIsInitialized', 'ensurePathname', 'runProcess' ])
            ->getMock();

        $musly
            ->expects(self::once())
            ->method('runProcess')
            ->with($expected['commandline'])
            ->willReturn($process);

        $tracks = $musly->getSimilarTracks($params['pathname'], $params['num'], $params['extraParams']);

        self::assertCount($expected['count'], $tracks);

        foreach ($tracks as $track) {
            self::assertSame($expected['keys'], array_keys($track));
        }
    }

    public function dataGetSimilarTracksSuccess(): array
    {
        $binary = uniqid('/path/to/binary', true);
        $pathname = uniqid('/path/to/file', true);
        $num = random_int(1, 100);
        $jukeboxPathname = uniqid('/path/to/jukebox', true);
        $collection = $this->getCollectionMock([ 'initialized' => true, 'pathname' => uniqid('/path/to/collection', true) ]);
        $collectionWithJukebox = $this->getCollectionMock([ 'initialized' => true, 'pathname' => uniqid('/path/to/collection', true), 'jukeboxPathname' => $jukeboxPathname ]);
        $extraParams = uniqid('-extra-params=', true);

        $baseDatasets = [
            'get similar tracks' => [
                [
                    'binary' => $binary,
                    'collection' => $collection,
                    'pathname' => $pathname,
                    'num' => null,
                    'extraParams' => null,
                ],
                [
                    'commandline' => sprintf('%s -c "%s" -p "%s" -k 5', $binary, $collection->getPathname(), $pathname),
                    'count' => 3,
                    'keys' => [ 'track-id', 'track-distance', 'track-origin' ],
                ]
            ],
            'get similar tracks with limit' => [
                [
                    'binary' => $binary,
                    'collection' => $collection,
                    'pathname' => $pathname,
                    'num' => $num,
                    'extraParams' => null,
                ],
                [
                    'commandline' => sprintf('%s -c "%s" -p "%s" -k %d', $binary, $collection->getPathname(), $pathname, $num),
                    'count' => 3,
                    'keys' => [ 'track-id', 'track-distance', 'track-origin' ],
                ]
            ],
            'get similar tracks with extra params' => [
                [
                    'binary' => $binary,
                    'collection' => $collection,
                    'pathname' => $pathname,
                    'num' => null,
                    'extraParams' => $extraParams,
                ],
                [
                    'commandline' => sprintf('%s -c "%s" -p "%s" -k 5 %s', $binary, $collection->getPathname(), $pathname, $extraParams),
                    'count' => 3,
                    'keys' => [ 'track-id', 'track-distance', 'track-origin' ],
                ]
            ],
            'get similar tracks with jukebox' => [
                [
                    'binary' => $binary,
                    'collection' => $collectionWithJukebox,
                    'pathname' => $pathname,
                    'num' => $num,
                    'extraParams' => null,
                ],
                [
                    'commandline' => sprintf('%s -c "%s" -p "%s" -k %d -j "%s"', $binary, $collectionWithJukebox->getPathname(), $pathname, $num, $collectionWithJukebox->getJukeboxPathname()),
                    'count' => 3,
                    'keys' => [ 'track-id', 'track-distance', 'track-origin' ],
                ]
            ],
        ];

        $outputs = [
            'iammordaty/musly' => file_get_contents('./tests/unit/resources/get-similar-tracks-iammordaty-musly.stdout'),
            'dominikschnitzer/musly' => file_get_contents('./tests/unit/resources/get-similar-tracks-dominikschnitzer-musly.stdout'),
        ];

        $datasets = [];

        foreach ($outputs as $version => $output) {
            foreach ($baseDatasets as $baseName => $baseDataset) {
                [ $params, $expected ] = $baseDataset;

                $datasets[sprintf('%s (%s)', $baseName, $version)] = [
                    array_merge([ 'stdout' => $output ], $params),
                    $expected
                ];
            }
        }

        $datasets['get similar tracks with incomplete jukebox'] = [
            [
                'binary' => $binary,
                'collection' => $collection,
                'pathname' => $pathname,
                'num' => null,
                'stdout' => file_get_contents('./tests/unit/resources/get-similar-tracks-with-incomplete-jukebox.stdout'),
                'extraParams' => null,
            ],
            [
                'commandline' => sprintf('%s -c "%s" -p "%s" -k 5', $binary, $collection, $pathname),
                'count' => 3,
                'keys' => [ 'track-id', 'track-distance', 'track-origin' ],
            ]
        ];

        $num = 1000;

        $datasets['get similar tracks with limit from large collection'] = [
            [
                'binary' => $binary,
                'collection' => $collection,
                'pathname' => $pathname,
                'num' => $num,
                'stdout' => file_get_contents('./tests/unit/resources/get-similar-tracks-large-collection.stdout'),
                'extraParams' => null,
            ],
            [
                'commandline' => sprintf('%s -c "%s" -p "%s" -k %d', $binary, $collection, $pathname, $num),
                'count' => $num,
                'keys' => [ 'track-id', 'track-distance', 'track-origin' ],
            ]
        ];

        return $datasets;
    }

    /**
     * @dataProvider dataCommonErrors
     * @dataProvider dataFileNotFoundError
     * @dataProvider dataFileNotFoundInCollectionExceptionError
     */
    public function testGetSimilarTracksError($params, $expected)
    {
        $this->expectException($expected['exception']);

        $musly = $this->getMockBuilder(Musly::class)
            ->onlyMethods($params['methods'])
            ->getMock();

        $musly
            ->expects($expected['times'])
            ->method('runProcess')
            ->will(self::throwException(new ProcessFailedException($params['process'])));

        $musly->getSimilarTracks($params['pathname']);
    }

    public function dataFileNotFoundInCollectionExceptionError(): array
    {
        $pathname = uniqid('/path/to/file-or-directory', true);

        $process = $this->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->getMock();

        $process
            ->method('getErrorOutput')
            ->willReturn('File not found in collection! Aborting.');

        return [
            'throw an exception if the file does not exist in the collection' => [
                [
                    'methods' => [ 'ensureCollectionIsInitialized', 'ensurePathname', 'runProcess' ],
                    'process' => $process,
                    'pathname' => $pathname,
                ],
                [
                    'times' => self::once(),
                    'exception' => FileNotFoundInCollectionException::class,
                ]
            ],
        ];
    }

    /**
     * @dataProvider dataGetAllTracks
     */
    public function testGetAllTracks($params, $expected)
    {
        $process = $this->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->getMock();

        $process
            ->method('getOutput')
            ->willReturn($params['stdout']);

        $musly = $this->getMockBuilder(Musly::class)
            ->setConstructorArgs([ [ 'binary' => $params['binary'], 'collection' => $params['collection'] ] ])
            ->onlyMethods([ 'ensurePathname', 'runProcess' ])
            ->getMock();

        $musly
            ->expects(self::once())
            ->method('runProcess')
            ->with($expected['commandline'])
            ->willReturn($process);

        $tracks = $musly->getAllTracks();

        self::assertCount($expected['count'], $tracks);

        foreach ($tracks as $track) {
            self::assertSame($expected['keys'], array_keys($track));
        }
    }

    public function dataGetAllTracks(): array
    {
        $binary = uniqid('/path/to/binary', true);
        $collection = $this->getCollectionMock([ 'initialized' => true, 'pathname' => uniqid('/path/to/collection', true) ]);

        return [
            'get all tracks from collection' => [
                [
                    'binary' => $binary,
                    'collection' => $collection,
                    'stdout' => file_get_contents('./tests/unit/resources/list-tracks.stdout'),
                ],
                [
                    'commandline' => sprintf('%s -c "%s" -l', $binary, $collection->getPathname()),
                    'count' => 5,
                    'keys' => [ 'track-id', 'track-size', 'track-origin' ],
                ]
            ],
            'get all tracks from large collection' => [
                [
                    'binary' => $binary,
                    'collection' => $collection,
                    'stdout' => file_get_contents('./tests/unit/resources/list-tracks-large-collection.stdout'),
                ],
                [
                    'commandline' => sprintf('%s -c "%s" -l', $binary, $collection->getPathname()),
                    'count' => 10000,
                    'keys' => [ 'track-id', 'track-size', 'track-origin' ],
                ]
            ],
        ];
    }

    /**
     * @dataProvider dataCommonErrors
     */
    public function testGetAllTracksError($params, $expected)
    {
        $this->expectException($expected['exception']);

        $musly = $this->getMockBuilder(Musly::class)
            ->onlyMethods($params['methods'])
            ->getMock();

        $musly
            ->expects($expected['times'])
            ->method('runProcess')
            ->will(self::throwException(new ProcessFailedException($params['process'])));

        $musly->getAllTracks();
    }

    public function dataFileNotFoundError(): array
    {
        $pathname = uniqid('/path/to/file-or-directory', true);

        $process = $this->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->getMock();

        $process
            ->method('getErrorOutput')
            ->willReturn(uniqid('error_output', true));

        return [
            'throw an exception if the file does not exist' => [
                [
                    'methods' => [ 'ensureCollectionIsInitialized', 'runProcess' ],
                    'process' => $process,
                    'pathname' => $pathname,
                ],
                [
                    'times' => self::never(),
                    'exception' => FileNotFoundException::class,
                ]
            ],
        ];
    }

    public function dataCommonErrors(): array
    {
        $pathname = uniqid('/path/to/file-or-directory', true);

        $process = $this->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->getMock();

        $process
            ->method('getErrorOutput')
            ->willReturn(uniqid('error_output', true));

        return [
            'throw an exception if process fails' => [
                [
                    'methods' => [ 'ensureCollectionIsInitialized', 'ensurePathname', 'runProcess' ],
                    'process' => $process,
                    'pathname' => $pathname,
                ],
                [
                    'times' => self::once(),
                    'exception' => MuslyProcessFailedException::class,
                ]
            ],
            'throw an exception if collection is not initialized' => [
                [
                    'methods' => [ 'runProcess' ],
                    'process' => $process,
                    'pathname' => $pathname,
                ],
                [
                    'times' => self::never(),
                    'exception' => CollectionNotInitializedException::class,
                ]
            ],
        ];
    }

    // --

    private function getCollectionMock($params = [])
    {
        $collection = $this->getMockBuilder(Collection::class)
             ->setConstructorArgs([ $params ])
             ->onlyMethods([ 'isInitialized' ])
             ->getMock();

        if (isset($params['initialized'])) {
            $collection
                ->method('isInitialized')
                ->willReturn($params['initialized']);
        }

        return $collection;
    }
}
