<?php
declare(strict_types = 1);
namespace RCS\Traits\Test;

use RCS\Traits\SingletonTrait;

class TestExtendedNonSingleton
    extends TestNonSingleton
{
    use SingletonTrait;
}
