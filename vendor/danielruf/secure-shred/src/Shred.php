<?php

namespace Shred;

/**
 * @author Dani C. - dani.co@mail.com -
 * @author Daniel Ruf - kontakt@daniel-ruf.de -
 *
 * Free to use and abuse under the MIT license.
 * http://www.opensource.org/licenses/mit-license.php
 */
final class Shred {
  
  /* Number of iterations. Default = 3 */
  private $iterations;

  /* Size of a block. Default = 3 */
  private $block_size;

  /* Show stats. Default = false */
  private $stats;
  
  /**
   * Set the iterations and block size.
   *
   * @param integer $iterations
   * @param integer $block_size
   */
  public function __construct($iterations = 3, $block_size = 3, $stats = false) {
    $this->iterations = +$iterations;
    $this->block_size = +$block_size;
    $this->stats = $stats;
  }
  
  /**
   * Overwrite file and/or Safely Remove
   *
   * @param string $filepath
   * @param bool $remove
   * @return bool
   */
  public function shred($filepath, $remove = true) {
    $unlink = true;
    $iterations = $this->iterations;
    $block_size = $this->block_size;
    $stats = $this->stats;
    
    try {
      if ($this->fileWritable($filepath)) {
        $read  = new \SplFileObject($filepath, 'r');
        $write = new \SplFileObject($filepath, 'r+');
        
        if($stats) {
          $start = microtime(true);
        }
        
        $this->overwriteFile($read, $write);

        if($stats) {
          $end = microtime(true);
          $time = ($end-$start) * 1000;
          
          echo "\n";
          echo "iterations: {$iterations}\n";
          echo "block size: {$block_size}\n";
          echo "took: {$time}ns\n";
        }
        
        if ($remove) {
          $write->ftruncate(0);

          // close the file handles
          $read = null;
          $write = null;

          $unlink = unlink($filepath);
          
          if($stats && $unlink){
            echo "successfully deleted {$filepath}\n";
          }
        }
        
        return $unlink;
      }
      
      return false;
      
    }
    catch (\Exception $e) {
      throw new \Exception($e->getCode() . ' :: ' . $e->getMessage() . ' ::');
    }
  }
  
  /**
   * Determines if the file exists & is read/write.
   *
   * @param string $filepath
   * @return bool
   */
  private function fileWritable($filepath) {
    if (is_readable($filepath) && is_writable($filepath)) {
      return true;
    }
    
    return false;
  }
  
  /**
   * Overwrites file n iterations times.
   *
   * @param class $read
   * @param class $write
   */
  private function overwriteFile($read, $write) {
    $iterations = $this->iterations;
    
    while (!$read->eof()) {
      $line_tell   = $read->ftell();
      $line        = $read->fgets();
      $line_length = strlen($line);
      
      if (0 === $line_length) {
        continue;
      }
      
      for ($n = 0; $n < $iterations; $n++) {
        $write->fseek($line_tell);
        $write->fwrite($this->stringRand($line_length));
      }
    }
    
    return;
  }
  
  /**
   * Get Random string 'n' length.
   *
   * @param integer $line_length
   * @param integer $block_size
   * @return string
   */
  private function stringRand($line_length) {
    $block_size = $this->block_size;
    $blocks = +($line_length / $block_size);
    
    if (1 < $blocks) {
      $s    = '';
      $rest = +($line_length - ($blocks * $block_size));
      
      for ($n = 0; $n < $block_size; $n++) {
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