<?php
namespace FluidTYPO3\Flux\Tests\Unit\ViewHelpers\Pipe;

/*
 * This file is part of the FluidTYPO3/Flux project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use FluidTYPO3\Flux\Outlet\Pipe\ControllerPipe;
use FluidTYPO3\Flux\Tests\Unit\ViewHelpers\AbstractViewHelperTestCase;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;

class ControllerViewHelperTest extends AbstractViewHelperTestCase
{
    /**
     * @dataProvider getTestArguments
     * @param array $arguments
     */
    public function testWithArguments(array $arguments)
    {
        $result = $this->executeViewHelper($arguments, array(), null, null, 'FakePlugin');
        $this->assertSame('', $result);
    }

    /**
     * @return array
     */
    public function getTestArguments()
    {
        return array(
            array(array()),
            array(array('controller' => 'SomeClass')),
            array(array('controller' => 'SomeClass', 'action' => 'foobar')),
        );
    }

    protected function createObjectManagerInstance(): ObjectManagerInterface
    {
        $objectManager = parent::createObjectManagerInstance();
        $objectManager->method('get')->willReturnMap(
            [
                [ControllerPipe::class, new ControllerPipe()],
            ]
        );
        return $objectManager;
    }
}
