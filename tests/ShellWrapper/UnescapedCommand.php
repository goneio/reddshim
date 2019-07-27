<?php
namespace Gone\ReddShim\Tests\ShellWrapper;

use AdamBrett\ShellWrapper\Command\Param;

class UnescapedCommand extends Param {
    public function __toString()
    {
        return $this->param;
    }
}