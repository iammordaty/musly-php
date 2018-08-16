<?php

namespace Musly\Tests\Unit;

use PHPUnit\Framework\TestCase;

use Musly\{
    Collection,
    Exception\InvalidArgumentException
};


class CollectionTest extends TestCase
{
    /**
     * @dataProvider dataCreateSuccess
     */
    public function testCreateSuccess($params, $expected)
    {
        $collection = new Collection($params);

        $this->assertSame($expected['pathname'], $collection->getPathname());
        $this->assertSame($expected['similarityMethod'], $collection->getSimilarityMethod());
        $this->assertSame($expected['jukeboxPathname'], $collection->getJukeboxPathname());
    }

    public function dataCreateSuccess()
    {
        $pathname = uniqid('/path/to/collection');
        $defaultJukeboxPathname = $pathname . '.jbox';
        $jukeboxPathname = uniqid('/path/to/jukebox');

        return [
            'create with default params' => [
                null,
                [
                    'pathname' => 'collection.musly',
                    'similarityMethod' => null,
                    'jukeboxPathname' => null,
                ]
            ],
            'create with pathname as string' => [
                $pathname,
                [
                    'pathname' => $pathname,
                    'similarityMethod' => null,
                    'jukeboxPathname' => null,
                ]
            ],
            'create with custom absolute pathname and default jukebox pathname' => [
                [
                    'pathname' => $pathname,
                    'similarityMethod' => 'mandelellis',
                    'jukeboxPathname' => Collection::USE_DEFAULT_JUKEBOX_PATHNAME,
                ],
                [
                    'pathname' => $pathname,
                    'similarityMethod' => 'mandelellis',
                    'jukeboxPathname' => $defaultJukeboxPathname,
                ]
            ],
            'create with custom relative pathname and default jukebox pathname' => [
                [
                    'pathname' => 'custom-collection.musly',
                    'similarityMethod' => 'mandelellis',
                    'jukeboxPathname' => Collection::USE_DEFAULT_JUKEBOX_PATHNAME,
                ],
                [
                    'pathname' => 'custom-collection.musly',
                    'similarityMethod' => 'mandelellis',
                    'jukeboxPathname' => 'custom-collection.jbox',
                ]
            ],
            'create with custom params' => [
                [
                    'pathname' => $pathname,
                    'similarityMethod' => 'mandelellis',
                    'jukeboxPathname' => $jukeboxPathname,
                ],
                [
                    'pathname' => $pathname,
                    'similarityMethod' => 'mandelellis',
                    'jukeboxPathname' => $jukeboxPathname,
                ]
            ],
            'create with custom params and default pathname' => [
                [
                    'similarityMethod' => 'mandelellis',
                    'jukeboxPathname' => $jukeboxPathname,
                ],
                [
                    'pathname' => 'collection.musly',
                    'similarityMethod' => 'mandelellis',
                    'jukeboxPathname' => $jukeboxPathname,
                ]
            ],
        ];
    }

    public function testCreateError()
    {
        $this->expectException(InvalidArgumentException::class);

        $invalidSimilarityMethod = uniqid('this-is-invalid-similarity-method');

        new Collection([ 'similarityMethod' => $invalidSimilarityMethod ]);
    }

    /**
     * @dataProvider dataConfigureSuccess
     */
    public function testConfigureSuccess($params, $expected)
    {
        $collection = new Collection();

        $collection->setSimilarityMethod($params['similarityMethod']);

        if ($params['jukeboxPathname'] === null) {
            $collection->setJukeboxPathname();
        } else {
            $collection->setJukeboxPathname($params['jukeboxPathname']);
        }

        $this->assertSame($expected['similarityMethod'], $collection->getSimilarityMethod());
        $this->assertSame($expected['jukeboxPathname'], $collection->getJukeboxPathname());
    }

    public function dataConfigureSuccess()
    {
        $jukeboxPathname = uniqid('/path/to/jukebox');

        return [
            'configure with default jukebox pathname' => [
                [
                    'similarityMethod' => 'mandelellis',
                    'jukeboxPathname' => null,
                ],
                [
                    'similarityMethod' => 'mandelellis',
                    'jukeboxPathname' => 'collection.jbox',
                ]
            ],
            'configure by custom params' => [
                [
                    'similarityMethod' => 'mandelellis',
                    'jukeboxPathname' => $jukeboxPathname,
                ],
                [
                    'similarityMethod' => 'mandelellis',
                    'jukeboxPathname' => $jukeboxPathname,
                ]
            ],
        ];
    }

    public function testConfigureError()
    {
        $this->expectException(InvalidArgumentException::class);

        $invalidSimilarityMethod = uniqid('this-is-invalid-similarity-method');

        $collection = new Collection();
        $collection->setSimilarityMethod($invalidSimilarityMethod);
    }
}
