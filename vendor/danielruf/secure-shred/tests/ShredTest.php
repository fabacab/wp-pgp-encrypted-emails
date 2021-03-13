<?php

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;

final class ShredTest extends TestCase
{
  private $root;
  private $rootName = 'home';
  private $testFile = 'test';
  private $testFolder = 'testFolder';

  protected function setUp(): void
  {
    vfsStreamWrapper::register();
    $this->root = vfsStream::setup($this->rootName);
    file_put_contents(vfsStream::url("{$this->rootName}/{$this->testFile}"), '1 2 3 4 5 6');
    mkdir(vfsStream::url("{$this->rootName}/{$this->testFolder}"));
  }

  public function testCanShred()
  {
    $this->assertEquals(
      11,
      strlen(file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}")))
    );

    $oldContent = file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}"));
    $shred = new Shred\Shred();

    $this->assertEquals(
      true,
      $shred->shred(vfsStream::url("{$this->rootName}/{$this->testFile}"), false)
    );

    $newContent = file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}"));

    $this->assertEquals(
      11,
      strlen(file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}")))
    );

    $this->assertNotEquals(
      $oldContent,
      $newContent
    );
  }

  public function testCanShredAndMangle()
  {
    $oldPath = vfsStream::url("{$this->rootName}/{$this->testFile}");
    $this->assertEquals(
      11,
      strlen(file_get_contents($oldPath))
    );

    $oldContent = file_get_contents($oldPath);
    $shred = new Shred\Shred();

    $this->assertEquals(
      true,
      $shred->shred($oldPath, false, true)
    );

    $files = scandir(vfsStream::url("{$this->rootName}"));

    $filesLen = array_map("mb_strlen", $files);

    $fileLen = mb_strlen($this->testFile);

    $this->assertContains(
      $fileLen * 2,
      $filesLen
    );

    $fileKey = array_search($fileLen * 2, $filesLen);

    $newPath = vfsStream::url("{$this->rootName}/{$files[$fileKey]}");
    $newContent = file_get_contents($newPath);

    $this->assertEquals(
      11,
      strlen(file_get_contents($newPath))
    );

    $this->assertNotEquals(
      $oldContent,
      $newContent
    );

    $this->assertNotEquals(
      $oldPath,
      $newPath
    );
  }

  public function testCanShredAndDelete()
  {
    $this->assertEquals(
      11,
      strlen(file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}")))
    );

    $shred = new Shred\Shred();

    $this->assertEquals(
      true,
      $shred->shred(vfsStream::url("{$this->rootName}/{$this->testFile}"), true)
    );

    $this->assertFileDoesNotExist(
      vfsStream::url("{$this->rootName}/{$this->testFile}")
    );
  }

  public function testCanNotShredDirectory()
  {
    $shred = new Shred\Shred();

    $this->assertFalse(
      $shred->shred(vfsStream::url("{$this->rootName}/{$this->testFolder}"), true)
    );

    $this->assertFileExists(
      vfsStream::url("{$this->rootName}/{$this->testFolder}")
    );
  }

  public function testStats()
  {
    $this->assertEquals(
      11,
      strlen(file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}")))
    );

    $oldContent = file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}"));
    $shred = new Shred\Shred(null, null, true);
    $this->setOutputCallback(function () {
      // noop
    });

    $this->assertEquals(
      true,
      $shred->shred(vfsStream::url("{$this->rootName}/{$this->testFile}"), false)
    );

    $newContent = file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}"));

    $this->assertEquals(
      11,
      strlen(file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}")))
    );

    $this->assertNotEquals(
      $oldContent,
      $newContent
    );

    $this->assertStringContainsString(
      "iterations: 3\n",
      $this->getActualOutput()
    );

    $this->assertStringContainsString(
      "block size: 3\n",
      $this->getActualOutput()
    );

    $this->assertStringContainsString(
      "took: ",
      $this->getActualOutput()
    );
  }

  public function testStatsCustom()
  {
    $this->assertEquals(
      11,
      strlen(file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}")))
    );

    $oldContent = file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}"));
    $shred = new Shred\Shred(5, 6, true);
    $this->setOutputCallback(function () {
      // noop
    });

    $this->assertEquals(
      true,
      $shred->shred(vfsStream::url("{$this->rootName}/{$this->testFile}"), false)
    );

    $newContent = file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}"));

    $this->assertEquals(
      11,
      strlen(file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}")))
    );

    $this->assertNotEquals(
      $oldContent,
      $newContent
    );

    $this->assertStringContainsString(
      "iterations: 5\n",
      $this->getActualOutput()
    );

    $this->assertStringContainsString(
      "block size: 6\n",
      $this->getActualOutput()
    );

    $this->assertStringContainsString(
      "took: ",
      $this->getActualOutput()
    );
  }

  public function testStatsDelete()
  {
    $this->assertEquals(
      11,
      strlen(file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}")))
    );

    $shred = new Shred\Shred(null, null, true);
    $this->setOutputCallback(function () {
      // noop
    });

    $this->assertEquals(
      true,
      $shred->shred(vfsStream::url("{$this->rootName}/{$this->testFile}"), true)
    );

    $this->assertStringContainsString(
      "iterations: 3\n",
      $this->getActualOutput()
    );

    $this->assertStringContainsString(
      "block size: 3\n",
      $this->getActualOutput()
    );

    $this->assertStringContainsString(
      "took: ",
      $this->getActualOutput()
    );

    $this->assertStringContainsString(
      "successfully deleted vfs://{$this->rootName}/{$this->testFile}",
      $this->getActualOutput()
    );
  }

  public function testFlush()
  {
    $this->assertEquals(
      11,
      strlen(file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}")))
    );

    $oldContent = file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}"));
    $shred = new Shred\Shred(null, null, null, true);
    $this->setOutputCallback(function () {
      // noop
    });

    $this->assertEquals(
      true,
      $shred->shred(vfsStream::url("{$this->rootName}/{$this->testFile}"), false)
    );

    $newContent = file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}"));

    $this->assertEquals(
      11,
      strlen(file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}")))
    );

    $this->assertNotEquals(
      $oldContent,
      $newContent
    );
  }

  public function testFlushStats()
  {
    $this->assertEquals(
      11,
      strlen(file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}")))
    );

    $oldContent = file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}"));
    $shred = new Shred\Shred(null, null, true, true);
    $this->setOutputCallback(function () {
      // noop
    });

    $this->assertEquals(
      true,
      $shred->shred(vfsStream::url("{$this->rootName}/{$this->testFile}"), false)
    );

    $newContent = file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}"));

    $this->assertEquals(
      11,
      strlen(file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}")))
    );

    $this->assertNotEquals(
      $oldContent,
      $newContent
    );

    $this->assertStringContainsString(
      "iterations: 3\n",
      $this->getActualOutput()
    );

    $this->assertStringContainsString(
      "block size: 3\n",
      $this->getActualOutput()
    );

    $this->assertStringContainsString(
      "took: ",
      $this->getActualOutput()
    );
  }

  public function testFlushStatsCustom()
  {
    $this->assertEquals(
      11,
      strlen(file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}")))
    );

    $oldContent = file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}"));
    $shred = new Shred\Shred(5, 6, true, true);
    $this->setOutputCallback(function () {
      // noop
    });

    $this->assertEquals(
      true,
      $shred->shred(vfsStream::url("{$this->rootName}/{$this->testFile}"), false)
    );

    $newContent = file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}"));

    $this->assertEquals(
      11,
      strlen(file_get_contents(vfsStream::url("{$this->rootName}/{$this->testFile}")))
    );

    $this->assertNotEquals(
      $oldContent,
      $newContent
    );

    $this->assertStringContainsString(
      "iterations: 5\n",
      $this->getActualOutput()
    );

    $this->assertStringContainsString(
      "block size: 6\n",
      $this->getActualOutput()
    );

    $this->assertStringContainsString(
      "took: ",
      $this->getActualOutput()
    );
  }
}
