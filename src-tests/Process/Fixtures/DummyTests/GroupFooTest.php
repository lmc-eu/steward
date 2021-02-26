<?php declare(strict_types=1);

namespace Lmc\Steward\Process\Fixtures\DummyTests;

use Lmc\Steward\Test\AbstractTestCase;

/**
 * Dummy test without group "foo" and group "both"
 *
 * @group both
 * @group foo
 */
class GroupFooTest extends AbstractTestCase
{
}
