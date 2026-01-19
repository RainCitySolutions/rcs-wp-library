<?php
declare(strict_types = 1);
namespace RCS\Traits\Test;

use RCS\Traits\SingletonTrait;

class TestSingletonTrait
{
    use SingletonTrait;

    public bool $initInstCalled = false;

    protected function initializeInstance(): void {
        $this->initInstCalled = true;
    }
}
