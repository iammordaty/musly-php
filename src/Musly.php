<?php

namespace Musly;

use Musly\Exception\CollectionNotInitializedException;
use Musly\Exception\FileNotFoundException;
use Musly\Exception\FileNotFoundInCollectionException;
use Musly\Exception\MuslyProcessFailedException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class Musly
{
    /**
     * @var string
     */
    public const ANALYSIS_RESULT_OK = 'OK';

    /**
     * @var string
     */
    public const ANALYSIS_RESULT_FAILED = 'FAILED';

    /**
     * @var string
     */
    public const ANALYSIS_RESULT_SKIPPED = 'SKIPPED';

    /**
     * @var string
     */
    public const DEFAULT_BINARY = 'musly';

    /**
     * @var int
     */
    public const DEFAULT_SIMILAR_TRACKS_NUM = 5;

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
    public function __construct(array $params = [])
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
    public function setBinary(string $binary): self
    {
        $this->binary = $binary;

        return $this;
    }

    /**
     * @return string
     */
    public function getBinary(): string
    {
        return $this->binary;
    }

    /**
     * @param Collection $collection
     * @return $this
     *
     * @throws CollectionNotInitializedException
     */
    public function setCollection(Collection $collection): self
    {
        $this->ensureCollectionIsInitialized($collection);

        $this->collection = $collection;

        return $this;
    }

    /**
     * @return Collection
     */
    public function getCollection(): Collection
    {
        return $this->collection;
    }

    /**
     * @param Collection|null $collection
     * @return bool
     *
     * @throws MuslyProcessFailedException
     */
    public function initializeCollection(Collection $collection = null): bool
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
    public function analyze(string $pathname, string $ext = ''): array
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
     * @param int|null $num
     * @param string|null $extraParams
     * @return array
     *
     * @throws CollectionNotInitializedException
     * @throws FileNotFoundException
     * @throws MuslyProcessFailedException
     */
    public function getSimilarTracks(string $pathname, ?int $num = null, ?string $extraParams = null): array
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

        if ($extraParams) {
            $commandline .= ' ' . $extraParams;
        }

        if ($this->collection->getJukeboxPathname()) {
            $commandline .= sprintf(' -j "%s"', $this->collection->getJukeboxPathname());
        }

        try {
            $process = $this->runProcess($commandline);
            $output = $process->getOutput();

            return $this->extractListingResult($output, 24);
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
    public function getAllTracks(): array
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

            return $this->extractListingResult($output, 21);
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
    protected function runProcess(string $commandline): Process
    {
        $process = Process::fromShellCommandline($commandline);
        $process->mustRun();

        return $process;
    }

    /**
     * @param Collection $collection
     * @return void
     *
     * @throws CollectionNotInitializedException
     */
    protected function ensureCollectionIsInitialized(Collection $collection): void
    {
        if (!$collection->isInitialized()) {
            $message = sprintf('Collection "%s" is not initialized.', $collection->getPathname());

            throw new CollectionNotInitializedException($message);
        }
    }

    /**
     * @param string $pathname
     * @return void
     *
     * @throws FileNotFoundException
     */
    protected function ensurePathname(string $pathname): void
    {
        if (!file_exists($pathname)) {
            throw new FileNotFoundException(sprintf('"%s" does not exists', $pathname));
        }
    }

    /**
     * @param string $output
     * @param int $linesToSkip
     * @return array
     */
    private function extractListingResult(string $output, int $linesToSkip): array
    {
        $lines = explode(PHP_EOL, trim($output));
        $linesToSkipZeroBased = $linesToSkip - 1;
        $withAttrs = strpos($output, 'track-id') !== false;

        $tracks = [];

        foreach ($lines as $index => $line) {
            if ($index <= $linesToSkipZeroBased) {
                continue;
            }

            if ($withAttrs && preg_match_all('/([^:]+):\s([^,]*)(?:,\s)?/', $line, $matches)) {
                $tracks[] = array_combine($matches[1], $matches[2]);

                continue;
            }

            $tracks[] = [
                'track-id' => null,
                'track-distance' => null,
                'track-origin' => $line,
            ];
        }

        return $tracks;
    }

    /**
     * @param string $output
     * @return array
     */
    private function extractAnalysisResult(string $output): array
    {
        $matches = [];

        preg_match_all(
            '/^(?:Analyzing|Skipping[\w ]+)\s\[\d+]:\s(?:\.\.)?(.+)(?:\s-\s\[(OK|FAILED)]\.?)?$/mU',
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
