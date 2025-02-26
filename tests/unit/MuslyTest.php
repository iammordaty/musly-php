<?php

namespace Musly\Tests\Unit;

use PHPUnit\Framework\TestCase;

use Musly\{
    Collection,
    Exception\CollectionNotInitializedException,
    Exception\FileNotFoundException,
    Exception\FileNotFoundInCollectionException,
    Exception\MuslyProcessFailedException,
    Musly
};

use Symfony\Component\Process\{
    Exception\ProcessFailedException,
    Process
};

class MuslyTest extends TestCase
{
    /**
     * @dataProvider dataCreateSuccess
     */
    public function testCreateSuccess($params, $expected)
    {
        $musly = new Musly($params);

        $this->assertSame($expected['binary'], $musly->getBinary());
        $this->assertSame($expected['collection']->getPathname(), $musly->getCollection()->getPathname());
    }

    public function dataCreateSuccess()
    {
        $binary = uniqid('/path/to/musly');
        $collection = $this->getCollectionMock([ 'initialized' => true ]);
        $pathname = uniqid('/path/to/collection');
        $customCollection = $this->getCollectionMock([ 'initialized' => true, 'pathname' => $pathname ]);

        return [
            'create with default params' => [
                null,
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

        $this->assertSame($expected['binary'], $musly->getBinary());
        $this->assertSame($expected['collection'], $musly->getCollection());
    }

    public function dataConfigureSuccess()
    {
        $binary = uniqid('/path/to/musly');
        $pathname = uniqid('/path/to/collection');
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
            ->setMethods([ 'runProcess' ])
            ->getMock();

        $expectedTimes = $expected['commandline'] ? $this->once() : $this->never();

        $musly
            ->expects($expectedTimes)
            ->method('runProcess')
            ->with($expected['commandline']);

        $this->assertSame($expected['result'], $musly->initializeCollection($params['collection']));
    }

    public function dataInitializeCollectionSuccess()
    {
        $binary = uniqid('/path/to/binary');
        $pathname = uniqid('/path/to/collection');
        $jukeboxPathname = uniqid('/path/to/jukebox');

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
                    // no change, jukebox is only used to calculate similarity
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
            ->setMethods([ 'runProcess' ])
            ->getMock();

        $musly
            ->method('runProcess')
            ->will($this->throwException(new ProcessFailedException($process)));

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
            ->setMethods([ 'ensurePathname', 'runProcess' ])
            ->getMock();

        $musly
            ->expects($this->once())
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

        $this->assertSame($expected['result'], $result);
    }

    public function dataAnalyzeSuccess()
    {
        $binary = uniqid('/path/to/binary');
        $collection = $this->getCollectionMock([ 'initialized' => true, 'pathname' => uniqid('/path/to/collection') ]);
        $directory = uniqid('/path/to/directory/%s');
        $directoryWithSlash = sprintf('%s/', $directory);
        $ext = uniqid();
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
            ->setMethods($params['methods'])
            ->getMock();

        $musly
            ->expects($expected['times'])
            ->method('runProcess')
            ->will($this->throwException(new ProcessFailedException($params['process'])));

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
            ->setMethods([ 'ensureCollectionIsInitialized', 'ensurePathname', 'runProcess' ])
            ->getMock();

        $musly
            ->expects($this->once())
            ->method('runProcess')
            ->with($expected['commandline'])
            ->willReturn($process);

        $tracks = $musly->getSimilarTracks($params['pathname'], $params['num']);

        $this->assertCount($expected['count'], $tracks);

        foreach ($tracks as $track) {
            $this->assertSame($expected['keys'], array_keys($track));
        }
    }

    public function dataGetSimilarTracksSuccess()
    {
        $binary = uniqid('/path/to/binary');
        $pathname = uniqid('/path/to/file');
        $num = rand(1, 100);
        $jukeboxPathname = uniqid('/path/to/jukebox');
        $collection = $this->getCollectionMock([ 'initialized' => true, 'pathname' => uniqid('/path/to/collection') ]);
        $collectionWithJukebox = $this->getCollectionMock([ 'initialized' => true, 'pathname' => uniqid('/path/to/collection'), 'jukeboxPathname' => $jukeboxPathname ]);

        $baseDatasets = [
            'get similar tracks' => [
                [
                    'binary' => $binary,
                    'collection' => $collection,
                    'pathname' => $pathname,
                    'num' => null,
                ],
                [
                    'commandline' => sprintf('%s -c "%s" -p "%s" -k 5', $binary, $collection->getPathname(), $pathname),
                    'count' => 3,
                    'keys' => [ 'track-id', 'track-similarity', 'track-origin' ],
                ]
            ],
            'get similar tracks with limit' => [
                [
                    'binary' => $binary,
                    'collection' => $collection,
                    'pathname' => $pathname,
                    'num' => $num,
                ],
                [
                    'commandline' => sprintf('%s -c "%s" -p "%s" -k %d', $binary, $collection->getPathname(), $pathname, $num),
                    'count' => 3,
                    'keys' => [ 'track-id', 'track-similarity', 'track-origin' ],
                ]
            ],
            'get similar tracks with jukebox' => [
                [
                    'binary' => $binary,
                    'collection' => $collectionWithJukebox,
                    'pathname' => $pathname,
                    'num' => $num,
                ],
                [
                    'commandline' => sprintf('%s -c "%s" -p "%s" -k %d -j "%s"', $binary, $collectionWithJukebox->getPathname(), $pathname, $num, $collectionWithJukebox->getJukeboxPathname()),
                    'count' => 3,
                    'keys' => [ 'track-id', 'track-similarity', 'track-origin' ],
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
                list($params, $expected) = $baseDataset;

                $datasets[sprintf('%s (%s)', $baseName, $version)] = [
                    array_merge([ 'stdout' => $output ], $params),
                    $expected
                ];
            }
        }

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
            ->setMethods($params['methods'])
            ->getMock();

        $musly
            ->expects($expected['times'])
            ->method('runProcess')
            ->will($this->throwException(new ProcessFailedException($params['process'])));

        $musly->getSimilarTracks($params['pathname']);
    }

    public function dataFileNotFoundInCollectionExceptionError()
    {
        $pathname = uniqid('/path/to/file-or-directory');

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
                    'times' => $this->once(),
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
            ->setMethods([ 'ensurePathname', 'runProcess' ])
            ->getMock();

        $musly
            ->expects($this->once())
            ->method('runProcess')
            ->with($expected['commandline'])
            ->willReturn($process);

        $tracks = $musly->getAllTracks();

        $this->assertCount($expected['count'], $tracks);

        foreach ($tracks as $track) {
            $this->assertSame($expected['keys'], array_keys($track));
        }
    }

    public function dataGetAllTracks()
    {
        $binary = uniqid('/path/to/binary');
        $collection = $this->getCollectionMock([ 'initialized' => true, 'pathname' => uniqid('/path/to/collection') ]);

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
        ];
    }

    /**
     * @dataProvider dataCommonErrors
     */
    public function testGetAllTracksError($params, $expected)
    {
        $this->expectException($expected['exception']);

        $musly = $this->getMockBuilder(Musly::class)
            ->setMethods($params['methods'])
            ->getMock();

        $musly
            ->expects($expected['times'])
            ->method('runProcess')
            ->will($this->throwException(new ProcessFailedException($params['process'])));

        $musly->getAllTracks();
    }

    public function dataFileNotFoundError()
    {
        $pathname = uniqid('/path/to/file-or-directory');

        $process = $this->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->getMock();

        return [
            'throw an exception if the file does not exist' => [
                [
                    'methods' => [ 'ensureCollectionIsInitialized', 'runProcess' ],
                    'process' => $process,
                    'pathname' => $pathname,
                ],
                [
                    'times' => $this->never(),
                    'exception' => FileNotFoundException::class,
                ]
            ],
        ];
    }

    public function dataCommonErrors()
    {
        $pathname = uniqid('/path/to/file-or-directory');

        $process = $this->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->getMock();

        return [
            'throw an exception if process fails' => [
                [
                    'methods' => [ 'ensureCollectionIsInitialized', 'ensurePathname', 'runProcess' ],
                    'process' => $process,
                    'pathname' => $pathname,
                ],
                [
                    'times' => $this->once(),
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
                    'times' => $this->never(),
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
             ->setMethods([ 'isInitialized' ])
             ->getMock();

        if (isset($params['initialized'])) {
            $collection
                ->method('isInitialized')
                ->willReturn($params['initialized']);
        }

        return $collection;
    }
}
