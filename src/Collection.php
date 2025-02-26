<?php

namespace Musly;

use Musly\Exception\InvalidArgumentException;

class Collection extends \SplFileInfo
{
    /**
     * @var string
     */
    public const SIMILARITY_METHOD_MANDEL_ELLIS = 'mandelellis';

    /**
     * @var string
     */
    public const SIMILARITY_METHOD_TIMBRE = 'timbre';

    /**
     * @var string[]
     */
    public const AVAILABLE_SIMILARITY_METHODS = [
        self::SIMILARITY_METHOD_MANDEL_ELLIS,
        self::SIMILARITY_METHOD_TIMBRE,
    ];

    /**
     * @var string
     */
    public const DEFAULT_PATHNAME = 'collection.musly';

    /**
     * @var string
     */
    public const DEFAULT_JUKEBOX_FILE_EXT = 'jbox';

    /**
     * @var string
     */
    public const USE_DEFAULT_JUKEBOX_PATHNAME = '%COLL%.' . self::DEFAULT_JUKEBOX_FILE_EXT;

    /**
     * @var string|null
     */
    private $similarityMethod;

    /**
     * @var string|null
     */
    private $jukeboxPathname;

    /**
     * @param array|string $params
     */
    public function __construct($params = [])
    {
        $this->normalizeParams($params);

        parent::__construct($params['pathname']);

        if (isset($params['similarityMethod'])) {
            $this->setSimilarityMethod($params['similarityMethod']);
        }

        if (isset($params['jukeboxPathname'])) {
            $this->setJukeboxPathname($params['jukeboxPathname']);
        }
    }

    /**
     * @return bool
     */
    public function isInitialized(): bool
    {
        return $this->isFile();
    }

    /**
     * @param string $similarityMethod
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function setSimilarityMethod(string $similarityMethod): self
    {
        if (in_array($similarityMethod, self::AVAILABLE_SIMILARITY_METHODS) === false) {
            throw new InvalidArgumentException(sprintf('Invalid similarity method specified (%s)', $similarityMethod));
        }

        $this->similarityMethod = $similarityMethod;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getSimilarityMethod(): ?string
    {
        return $this->similarityMethod;
    }

    /**
     * @param string $jukeboxPathname
     * @return $this
     */
    public function setJukeboxPathname(string $jukeboxPathname = self::USE_DEFAULT_JUKEBOX_PATHNAME): self
    {
        $this->jukeboxPathname = $jukeboxPathname;

        if ($this->jukeboxPathname === self::USE_DEFAULT_JUKEBOX_PATHNAME) {
            $this->jukeboxPathname  = $this->getDefaultJukeboxPathname();
        }

        return $this;
    }

    /**
     * @return string|null
     */
    public function getJukeboxPathname(): ?string
    {
        return $this->jukeboxPathname;
    }

    /**
     * @param string|array $params
     */
    private function normalizeParams(&$params): void
    {
        if (is_string($params)) {
            $params = [ 'pathname' => $params ];
        }

        if (empty($params['pathname'])) {
            $params['pathname'] = self::DEFAULT_PATHNAME;
        }
    }

    /**
     * @return string
     */
    private function getDefaultJukeboxPathname(): string
    {
        $path = '';

        if ($this->getPath()) {
            $path = $this->getPath() . DIRECTORY_SEPARATOR;
        }

        return sprintf(
            '%s%s.%s',
            $path,
            $this->getFilename(),
            self::DEFAULT_JUKEBOX_FILE_EXT
        );
    }
}
