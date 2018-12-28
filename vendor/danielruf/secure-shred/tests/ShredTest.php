<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;

final class ShredTest extends TestCase
{

    private $root;
    public function setUp()
    {
        vfsStreamWrapper::register();
        $this->root = vfsStream::setup('home');
        vfsStream::newFile('test')->at($this->root)->setContent("1 2 3 4 5 6");
    }
    public function testCanShred(): void
    {
        $file = file_get_contents(vfsStream::url('home/test'));
        
        $this->assertEquals(
            11,
            strlen($this->root->getChild('test')->getContent())
        );
        
        $oldContent = $this->root->getChild('test')->getContent();
        $shred = new Shred\Shred();
        
        $this->assertEquals(
            true,
            $shred->shred(vfsStream::url('home/test'), false)
        );
        
        $newContent = $this->root->getChild('test')->getContent();
        
        $this->assertEquals(
            11,
            strlen($this->root->getChild('test')->getContent())
        );
        
        $this->assertNotEquals(
            $oldContent,
            $newContent
        );
    }

    public function testCanShredAndDelete(): void
    {
        $file = file_get_contents(vfsStream::url('home/test'));
        
        $this->assertEquals(
            11,
            strlen($this->root->getChild('test')->getContent())
        );
        
        $shred = new Shred\Shred();
        
        $this->assertEquals(
            true,
            $shred->shred(vfsStream::url('home/test'), true)
        );
        
        $this->assertFileNotExists(
            vfsStream::url('home/test')
        );
    }

    public function testStats(): void
    {
        $file = file_get_contents(vfsStream::url('home/test'));
        
        $this->assertEquals(
            11,
            strlen($this->root->getChild('test')->getContent())
        );
        
        $oldContent = $this->root->getChild('test')->getContent();
        $shred = new Shred\Shred(3, 3, true);
        $this->setOutputCallback(function() {});
        
        $this->assertEquals(
            true,
            $shred->shred(vfsStream::url('home/test'), false)
        );

        $newContent = $this->root->getChild('test')->getContent();

        $this->assertEquals(
            11,
            strlen($this->root->getChild('test')->getContent())
        );
        
        $this->assertNotEquals(
            $oldContent,
            $newContent
        );

        $this->assertContains(
            "iterations: 3\n",
            $this->getActualOutput()
        );

        $this->assertContains(
            "block size: 3\n",
            $this->getActualOutput()
        );

        $this->assertContains(
            "took: ",
            $this->getActualOutput()
        );
    }

    public function testStatsCustom(): void
    {
        $file = file_get_contents(vfsStream::url('home/test'));
        
        $this->assertEquals(
            11,
            strlen($this->root->getChild('test')->getContent())
        );
        
        $oldContent = $this->root->getChild('test')->getContent();
        $shred = new Shred\Shred(5, 6, true);
        $this->setOutputCallback(function() {});
        
        $this->assertEquals(
            true,
            $shred->shred(vfsStream::url('home/test'), false)
        );

        $newContent = $this->root->getChild('test')->getContent();

        $this->assertEquals(
            11,
            strlen($this->root->getChild('test')->getContent())
        );
        
        $this->assertNotEquals(
            $oldContent,
            $newContent
        );

        $this->assertContains(
            "iterations: 5\n",
            $this->getActualOutput()
        );

        $this->assertContains(
            "block size: 6\n",
            $this->getActualOutput()
        );

        $this->assertContains(
            "took: ",
            $this->getActualOutput()
        );
    }

    public function testStatsDelete(): void
    {
        $file = file_get_contents(vfsStream::url('home/test'));
        
        $this->assertEquals(
            11,
            strlen($this->root->getChild('test')->getContent())
        );
        
        $shred = new Shred\Shred(3, 3, true);
        $this->setOutputCallback(function() {});
        
        $this->assertEquals(
            true,
            $shred->shred(vfsStream::url('home/test'), true)
        );
        
        $this->assertContains(
            "iterations: 3\n",
            $this->getActualOutput()
        );

        $this->assertContains(
            "block size: 3\n",
            $this->getActualOutput()
        );

        $this->assertContains(
            "took: ",
            $this->getActualOutput()
        );

        $this->assertContains(
            "successfully deleted vfs://home/test",
            $this->getActualOutput()
        );
    }
}
