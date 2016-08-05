<?php

namespace Lmc\Steward\Process\Fixtures\InvalidTests;

use Lmc\Steward\Test\AbstractTestCase;

/**
 * File containing two classes (thus violating PSR-0 and PSR-4).
 * @codingStandardsIgnoreFile
 */
class MultipleClassesInFileTest extends AbstractTestCase
{
}

class AnotherClassTest extends AbstractTestCase
{
}
