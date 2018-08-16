<?php

namespace Musly;

use Musly\Exception\{
    CollectionNotInitializedException,
    FileNotFoundException,
    FileNotFoundInCollectionException,
    MuslyProcessFailedException
};

use Symfony\Component\Process\{
    Exception\ProcessFailedException,
    Process
};

class Musly
{
    /**
     * @var string
     */
    const ANALYSIS_RESULT_OK = 'OK';

    /**
     * @var string
     */
    const ANALYSIS_RESULT_FAILED = 'FAILED';

    /**
     * @var string
     */
    const ANALYSIS_RESULT_SKIPPED = 'SKIPPED';

    /**
     * @var string
     */
    const DEFAULT_BINARY = 'musly';

    /**
     * @var int
     */
    const DEFAULT_SIMILAR_TRACKS_NUM = 5;

    /**
     * @var string
     */
    private $binary = self::DEFAULT_BINARY;

    /**
     * @var Collection
     */
    private $collection;

    /**
     * @param array $params
     *
     * @throws CollectionNotInitializedException
     */
    public function __construct($params = [])
    {
        if (isset($params['binary'])) {
            $this->setBinary($params['binary']);
        }

        $this->collection = new Collection();

        if (isset($params['collection'])) {
            $this->setCollection($params['collection']);
        }
    }

    /**
     * @param string $binary
     * @return $this
     */
    public function setBinary($binary)
    {
        $this->binary = $binary;

        return $this;
    }

    /**
     * @return string
     */
    public function getBinary()
    {
        return $this->binary;
    }

    /**
     * @param Collection $collection
     * @return $this
     *
     * @throws CollectionNotInitializedException
     */
    public function setCollection(Collection $collection)
    {
        $this->ensureCollectionIsInitialized($collection);

        $this->collection = $collection;

        return $this;
    }

    /**
     * @return Collection
     */
    public function getCollection()
    {
        return $this->collection;
    }

    /**
     * @param Collection|null $collection
     * @return bool
     *
     * @throws MuslyProcessFailedException
     */
    public function initializeCollection(Collection $collection = null)
    {
        if (!$collection) {
            $collection = $this->collection;
        }

        if ($collection->isInitialized()) {
            return false;
        }

        $commandline = sprintf(
            '%s -c "%s" %s',
            $this->binary,
            $collection->getPathname(),
            $collection->getSimilarityMethod() ? sprintf('-n "%s"', $collection->getSimilarityMethod()) : '-N'
        );

        try {
            $this->runProcess($commandline);
        } catch (ProcessFailedException $e) {
            throw new MuslyProcessFailedException($e->getProcess());
        }

        return true;
    }

    /**
     * @param string $pathname
     * @param string $ext
     * @return array
     *
     * @throws CollectionNotInitializedException
     * @throws FileNotFoundException
     * @throws MuslyProcessFailedException
     */
    public function analyze($pathname, $ext = '')
    {
        $this->ensureCollectionIsInitialized($this->collection);

        $this->ensurePathname($pathname);

        // when adding a directory as pathname, musly appends directory separator to it even if it already exists
        if (substr($pathname, -1) === DIRECTORY_SEPARATOR) {
            $pathname = rtrim($pathname, DIRECTORY_SEPARATOR);
        }

        $commandline = sprintf(
            '%s -c "%s" -a "%s"',
            $this->binary,
            $this->collection->getPathname(),
            $pathname
        );

        if ($ext) {
            $commandline .= sprintf(' -x %s', ltrim($ext, '.'));
        }

        try {
            $process = $this->runProcess($commandline);
            $output = $process->getOutput();

            return $this->extractAnalysisResult($output);
        } catch (ProcessFailedException $e) {
            throw new MuslyProcessFailedException($e->getProcess());
        }
    }

    /**
     * @param string $pathname
     * @param int $num
     * @return array
     *
     * @throws CollectionNotInitializedException
     * @throws FileNotFoundException
     * @throws MuslyProcessFailedException
     */
    public function getSimilarTracks($pathname, $num = null)
    {
        $this->ensureCollectionIsInitialized($this->collection);

        $this->ensurePathname($pathname);

        $commandline = sprintf(
            '%s -c "%s" -p "%s" -k %d',
            $this->binary,
            $this->collection->getPathname(),
            $pathname,
            $num ?? self::DEFAULT_SIMILAR_TRACKS_NUM
        );

        if ($this->collection->getJukeboxPathname()) {
            $commandline .= sprintf(' -j "%s"', $this->collection->getJukeboxPathname());
        }

        try {
            $process = $this->runProcess($commandline);
            $output = $process->getOutput();

            return $this->extractListingResult($output);
        } catch (ProcessFailedException $e) {
            if (stripos($e->getProcess()->getErrorOutput(), 'file not found') !== false) {
                throw new FileNotFoundInCollectionException(
                    sprintf('"%s" not found in collection "%s"', $pathname, $this->collection->getPathname())
                );
            }

            throw new MuslyProcessFailedException($e->getProcess());
        }
    }

    /**
     * @return array
     *
     * @throws CollectionNotInitializedException
     * @throws MuslyProcessFailedException
     */
    public function getAllTracks()
    {
        $this->ensureCollectionIsInitialized($this->collection);

        $commandline = sprintf(
            '%s -c "%s" -l',
            $this->binary,
            $this->collection->getPathname()
        );

        try {
            $process = $this->runProcess($commandline);
            $output = $process->getOutput();

            return $this->extractListingResult($output);
        } catch (ProcessFailedException $e) {
            throw new MuslyProcessFailedException($e->getProcess());
        }
    }

    /**
     * @param string $commandline
     * @return Process
     *
     * @throws ProcessFailedException
     */
    protected function runProcess($commandline)
    {
        $process = new Process($commandline);
        $process->mustRun();

        return $process;
    }

    /**
     * @param Collection $collection
     *
     * @throws CollectionNotInitializedException
     */
    protected function ensureCollectionIsInitialized($collection)
    {
        if (!$collection->isInitialized()) {
            throw new CollectionNotInitializedException(
                sprintf('Collection "%s" is not initialized.', $collection->getPathname())
            );
        }
    }

    /**
     * @param string $pathname
     *
     * @throws FileNotFoundException
     */
    protected function ensurePathname($pathname)
    {
        if (!file_exists($pathname)) {
            throw new FileNotFoundException(sprintf('"%s" does not exists', $pathname));
        }
    }

    /**
     * @param string $output
     * @return array
     */
    private function extractListingResult($output)
    {
        $matches = [];

        preg_match(
            '/(?!.*Computing the k=\d+ most similar tracks to)(?!.*Reading collection file):.+\n(.+?)/sU',
            $output,
            $matches
        );

        $tracks = [];

        $tracklist = trim($matches[1]);
        $hasAttrs = strpos($tracklist, 'track-id') !== false;

        foreach (explode(PHP_EOL, $tracklist) as $line) {
            if ($hasAttrs && preg_match_all('/([^:]+):\s([^,]*)(?:,\s)?/', $line, $matches)) {
                $tracks[] = array_combine($matches[1], $matches[2]);
                
                continue;
            }

            $tracks[] = [
                'track-id' => null,
                'track-similarity' => null,
                'track-origin' => $line,
            ];
        }

        return $tracks;
    }

    /**
     * @param string $output
     * @return array
     */
    private function extractAnalysisResult($output)
    {
        $matches = [];

        preg_match_all(
            '/^(?:Analyzing|Skipping[\w ]+)\s\[\d+]:\s(?:\.\.)?(.+)(?:\s-\s\[(OK|FAILED)\]\.?)?$/mU',
            $output,
            $matches,
            PREG_SET_ORDER
        );

        $tracks = [];

        foreach ($matches as $match) {
            $tracks[] = [
                'track-origin' => ltrim($match[1], '.'),
                'result' => $match[2] ?? self::ANALYSIS_RESULT_SKIPPED,
            ];
        }

        return $tracks;
    }
}
