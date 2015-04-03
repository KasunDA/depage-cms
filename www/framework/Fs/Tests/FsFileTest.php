<?php

class FsFileTest extends TestBase
{
    // {{{ createTestClass
    public function createTestClass($override = array())
    {
        $params = array('scheme' => 'file');
        $newParams = array_merge($params, $override);

        return new FsFileTestClass($newParams);
    }
    // }}}
    // {{{ createRemoteTestDir
    public function createRemoteTestDir()
    {
        return $this->localDir;
    }
    // }}}
    // {{{ deleteRemoteTestDir
    public function deleteRemoteTestDir()
    {
    }
    // }}}
    // {{{ createRemoteTestFile
    public function createRemoteTestFile($path)
    {
        $this->createTestFile($path);
    }
    // }}}

    // {{{ testCdIntoWrapperUrl
    public function testCdIntoWrapperUrl()
    {
        $pwd = $this->fs->pwd();
        mkdir($this->remoteDir . '/testDir');
        $this->fs->cd('file://' . $this->remoteDir . '/testDir');
        $newPwd = $this->fs->pwd();

        $this->assertEquals($pwd . 'testDir/', $newPwd);
    }
    // }}}
}

/* vim:set ft=php sw=4 sts=4 fdm=marker et : */