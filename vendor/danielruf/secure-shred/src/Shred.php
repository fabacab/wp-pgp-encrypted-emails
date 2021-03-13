<?php

namespace Shred;

/**
 * @author Dani C. - dani.co@mail.com -
 * @author Daniel Ruf - kontakt@daniel-ruf.de -
 *
 * Free to use and abuse under the MIT license.
 * http://www.opensource.org/licenses/mit-license.php
 */
final class Shred
{

  /**
   * Number of iterations. Default = 3
   *
   * @var integer
   */
  private $iterations;

  /**
   * Size of a block. Default = 3
   *
   * @var integer
   */
  private $block_size;

  /**
   * Show stats. Default = false
   *
   * @var bool
   */
  private $stats;

  /**
   * Flush after each write. Default = false
   *
   * @var bool
   */
  private $flush_after_write;

  /**
   * Set the iterations, block size, and statistics reporting.
   *
   * If `$stats` is `true`, shredding operations will print/echo their
   * progress and successes or failures.
   *
   * @param integer $iterations
   * @param integer $block_size
   * @param bool $stats
   * @param bool $flush_after_write
   */
  public function __construct(
    $iterations = 3,
    $block_size = 3,
    $stats = false,
    $flush_after_write = false
  ) {
    if ($iterations === null) {
      $iterations = 3;
    }
    if ($block_size === null) {
      $block_size = 3;
    }
    if ($stats === null) {
      $stats = false;
    }
    if ($flush_after_write === null) {
      $flush_after_write = false;
    }

    $this->iterations = +$iterations;
    $this->block_size = +$block_size;
    $this->stats = $stats;
    $this->flush_after_write = $flush_after_write;
  }

  /**
   * Overwrite and/or safely remove (i.e., `unlink()`) the file.
   *
   * @param string $filepath
   * @param bool $remove
   * @return bool
   */
  public function shred($filepath, $remove = true, $mangle_filename = false)
  {
    $unlink = true;

    try {
      if ($this->fileWritable($filepath)) {
        $read  = new \SplFileObject($filepath, 'r');
        $write = new \SplFileObject($filepath, 'r+');

        if ($this->stats) {
          $start = microtime(true);
        }

        $this->overwriteFile($read, $write);

        if ($this->stats) {
          $end = microtime(true);
          $time = ($end-$start) * 1000;

          echo "\n";
          echo "iterations: {$this->iterations}\n";
          echo "block size: {$this->block_size}\n";
          echo "took: {$time}ns\n";
        }

        if ($remove) {
          $write->ftruncate(0);

          // close the file handles
          $read = null;
          $write = null;

          if ($mangle_filename) {
            $this->mangleFilename($filepath);
          }

          $unlink = unlink($filepath);

          if ($this->stats && $unlink) {
            echo "successfully deleted {$filepath}\n";
          }
        } else {
          // close the file handles
          $read = null;
          $write = null;

          if ($mangle_filename) {
            $this->mangleFilename($filepath);
          }
        }

        return $unlink;
      }

      return false;
    } catch (\Exception $e) {
      throw new \RuntimeException($e->getCode() . ' :: ' . $e->getMessage() . ' ::');
    }
  }

  private function mangleFilename($filepath)
  {
    $filepath_old = $filepath;
    $dirname = dirname($filepath_old);
    $basename = basename($filepath_old);
    $filepath_random = bin2hex(random_bytes(mb_strlen($basename)));
    $filepath = $dirname . DIRECTORY_SEPARATOR . $filepath_random;
    rename($filepath_old, $filepath);
  }

  /**
   * Determines if the file exists & is read/write.
   *
   * @param string $filepath
   * @return bool
   */
  private function fileWritable($filepath)
  {
    // clear stat cache to avoid falsely reported file status
    // use $filename parameter to possibly improve performance
    clearstatcache(true, $filepath);

    if (is_file($filepath)
      && is_readable($filepath)
      && is_writable($filepath)) {
      return true;
    }

    return false;
  }

  /**
   * Overwrites a file N iterations times.
   *
   * @param \SplFileObject $read File opened in a readable mode.
   * @param \SplFileObject $write Same file opened in a writable mode.
   */
  private function overwriteFile($read, $write)
  {
    while (!$read->eof()) {
      $line_tell   = $read->ftell();
      $line        = $read->fgets();
      $line_length = strlen($line);

      if (0 === $line_length) {
        continue;
      }

      for ($n = 0; $n < $this->iterations; $n++) {
        $write->fseek($line_tell);
        $write->fwrite($this->stringRand($line_length));
        if ($this->flush_after_write) {
          $write->fflush();
        }
      }
    }
  }

  /**
   * Get a random string of a given length.
   *
   * @param integer $line_length
   * @uses Shred::$block_size
   * @return string
   */
  private function stringRand($line_length)
  {
    $blocks = +($line_length / $this->block_size);

    if (1 < $blocks) {
      $s    = '';
      $rest = +($line_length - ($blocks * $this->block_size));

      for ($n = 0; $n < $this->block_size; $n++) {
        $s .= random_bytes($blocks);
      }

      if (0 < $rest) {
        $s .= random_bytes($rest);
      }
    } else {
      $s = random_bytes($line_length);
    }

    return $s;
  }
}
