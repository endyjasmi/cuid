<?php namespace EndyJasmi;

use Mockery;
use PHPUnit_Framework_TestCase as TestCase;

class CuidTest extends TestCase
{
    protected $path;

    public function setUp()
    {
        $this->path = __DIR__;
    }

    public function testInvokeMagicMethod()
    {
        $cuid = new Cuid($this->path);

        $hash = $cuid();

        var_dump($hash);

        $this->assertInternalType('string', $hash);
        $this->assertRegExp('/c[0-9a-z]{24,}/', $hash);
    }

    public function testCuidMethod()
    {
        $cuid = new Cuid($this->path);

        $hash = $cuid->cuid();

        var_dump($hash);

        $this->assertInternalType('string', $hash);
        $this->assertRegExp('/c[0-9a-z]{24,}/', $hash);
    }

    public function testSlugMethod()
    {
        $cuid = new Cuid($this->path);

        $hash = $cuid->slug();

        var_dump($hash);

        $this->assertInternalType('string', $hash);
        $this->assertRegExp('/[0-9a-z]{8}/', $hash);
    }
}
