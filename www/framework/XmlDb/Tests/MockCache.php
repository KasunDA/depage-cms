<?php

namespace Depage\XmlDb\Tests;

class MockCache
{
    public $cached = array();
    public $deleted = false;

    public function set($identifier, $xml)
    {
        $this->cached[$identifier] = $xml;
    }

    public function get($identifier)
    {
        $result = false;

        if (isset($this->cached[$identifier])) {
            $result = $this->cached[$identifier];
        }

        return $result;
    }

    public function delete($identifier)
    {
        $this->deleted = true;
    }
}

/* vim:set ft=php fenc=UTF-8 sw=4 sts=4 fdm=marker et : */
