<?php declare(strict_types=1);

namespace Lmc\Steward\Process\Fixtures\InvalidTests;

use Lmc\Steward\Test\AbstractTestCase;

/**
 * File containing two classes (thus violating PSR-0 and PSR-4).
 */
class MultipleClassesInFileTest extends AbstractTestCase
{
}

class AnotherClassTest extends AbstractTestCase
{
}
