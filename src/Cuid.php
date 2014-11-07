<?php
/**
 * Cuid class file
 *
 * @package EndyJasmi
 */
namespace EndyJasmi;

use RuntimeException;

/**
 * Cuid is a library to create collision resistant ids optimized for horizontal scaling and performance.
 */
class Cuid
{
    /**
     * Base 36 constant
     */
    const BASE36 = 36;

    /**
     * Decimal constant
     */
    const DECIMAL = 10;

    /**
     * Normal block size
     */
    const NORMAL_BLOCK = 4;

    /**
     * Small block size
     */
    const SMALL_BLOCK = 2;

    /**
     * @var string Temporary folder path to persist data
     */
    protected $path;

    /**
     * Counter used to prevent same machine collision
     *
     * In the original project, counter are stored in memory
     * because nodejs handle request in a single process.
     * PHP process is created for every single request hence
     * counts cannot be stored in memory. For this, I opt to
     * store counts in a locked file
     *
     * Make sure the library have write access
     *
     * @param string $path Storage path
     * @param integer $blockSize Block size
     *
     * @return string Return count generated hash
     */
    protected static function count($path, $blockSize = Cuid::NORMAL_BLOCK)
    {
        // Specify the file
        $filePath = $path . DIRECTORY_SEPARATOR . '.count';

        // Open the file
        $handler = fopen($filePath, 'a+');
        if (! $handler) {
            throw new RuntimeException("Failed to create file: $filePath");
        }

        // Lock file and reset file pointer
        flock($handler, LOCK_EX);
        fseek($handler, 0);

        // Get count from file and increase it
        $count = intval(trim(fgets($handler))) + 1;

        // Update the file and close it
        fseek($handler, 0);
        ftruncate($handler, 0);
        fwrite($handler, $count);
        fclose($handler);

        // Return count in hash
        return Cuid::pad(
            base_convert(
                $count,
                Cuid::DECIMAL,
                Cuid::BASE36
            ),
            $blockSize
        );
    }

    /**
     * Fingerprint are used for server identification
     *
     * PHP process are created for every request hence
     * there is no fix process id. In order to solve this
     * problem, the first process id are stored in a locked
     * file. The process id will be reuse in the subsequent
     * request until the pid file is deleted
     *
     * Make sure the library have write access
     *
     * @param string $path Storage path
     * @param integer $blockSize Block size
     *
     * @return string Return fingerprint generated hash
     */
    protected static function fingerprint($path, $blockSize = Cuid::NORMAL_BLOCK)
    {
        // Specify the file
        $filePath = $path . DIRECTORY_SEPARATOR . '.pid';

        // Open the file
        $handler = fopen($filePath, 'a+');
        if (! $handler) {
            throw new RuntimeException("Failed to create file: $filePath");
        }

        // Lock file and reset file pointer
        flock($handler, LOCK_EX);
        fseek($handler, 0);

        // Get process id from file
        $pid = intval(trim(fgets($handler)));

        // If no process id, new one are generated and stored
        if (! $pid) {
            $pid = getmypid();

            fseek($handler, 0);
            ftruncate($handler, 0);
            fwrite($handler, $pid);
        }

        // Close file
        fclose($handler);

        // Generate process id based hash
        $pid = Cuid::pad(
            base_convert(
                $pid,
                Cuid::DECIMAL,
                Cuid::BASE36
            ),
            Cuid::NORMAL_BLOCK / 2
        );

        // Generate hostname based hash
        $hostname = Cuid::pad(
            base_convert(
                array_reduce(
                    str_split(gethostname()),
                    function ($carry, $char) {
                        return $carry + ord($char);
                    },
                    strlen(gethostname()) + Cuid::BASE36
                ),
                Cuid::DECIMAL,
                Cuid::BASE36
            ),
            2
        );

        // Return small or normal block of hash
        if ($blockSize == Cuid::SMALL_BLOCK) {
            return substr($pid, 0, 1) . substr($hostname, -1);
        }

        return $pid . $hostname;
    }

    /**
     * Pad the input string into specific size
     *
     * @param string $input Input string
     * @param integer $size Input size
     *
     * @return string Return padded string
     */
    protected static function pad($input, $size)
    {
        $input = str_pad(
            $input,
            Cuid::BASE36,
            '0',
            STR_PAD_LEFT
        );

        return substr($input, strlen($input) - $size);
    }

    /**
     * Generate random hash
     *
     * @return string Return random hash string
     */
    protected static function random()
    {
        // Get random integer
        $modifier = pow(Cuid::BASE36, Cuid::NORMAL_BLOCK);
        $random = mt_rand() / mt_getrandmax();

        $random = $random * $modifier;

        // Convert integer to hash and return
        return Cuid::pad(
            base_convert(
                $random,
                Cuid::DECIMAL,
                Cuid::BASE36
            ),
            Cuid::NORMAL_BLOCK
        );
    }

    /**
     * Generate timestamp based hash
     *
     * @return string Return timestamp based hash string
     */
    protected static function timestamp()
    {
        return base_convert(
            floor(microtime(true) * 1000),
            Cuid::DECIMAL,
            Cuid::BASE36
        );
    }

    /**
     * Cuid constructor
     *
     * @param string $path Folder path to persist file
     */
    public function __construct($path)
    {
        $this->path = $path;
    }

    /**
     * Invoke magic method to allows easy access
     *
     * @return string Return generated cuid string
     */
    public function __invoke()
    {
        return $this->cuid();
    }

    /**
     * Generate full version cuid
     *
     * @return string Return generated cuid string
     */
    public function cuid()
    {
        $prefix = 'c';
        $timestamp = Cuid::timestamp();
        $count = Cuid::count($this->path);
        $fingerprint = Cuid::fingerprint($this->path);
        $random = Cuid::random() . Cuid::random();

        return $prefix .
            $timestamp .
            $count .
            $fingerprint .
            $random;
    }

    /**
     * Generate short version cuid
     *
     * It only have 8 characters and it is a great solution
     * for short urls.
     *
     * Note that less room for the data also means higher
     * chance of collision
     *
     * @return string Return generated short cuid string
     */
    public function slug()
    {
        $timestamp = substr(Cuid::timestamp(), -2);
        $count = Cuid::count($this->path, Cuid::SMALL_BLOCK);
        $fingerprint = Cuid::fingerprint($this->path, Cuid::SMALL_BLOCK);
        $random = substr(Cuid::random(), -2);

        return $timestamp .
            $count .
            $fingerprint .
            $random;
    }
}
