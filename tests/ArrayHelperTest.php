<?php
/**
 * test Array Helper
 *
 * @package     BlueCollection
 * @subpackage  Test
 * @author      Michał Adamiak    <chajr@bluetree.pl>
 * @copyright   chajr/bluetree
 */
namespace Test;

use BlueCollection\Helper\ArrayHelper;

class ArrayHelperTest extends \PHPUnit_Framework_TestCase
{
    /**
     * test array merge
     *
     * @param array $data
     *
     * @dataProvider arrayDataProvider
     * @requires arrayDataProvider
     */
    public function testArrayMerge($data)
    {
        static $iteration = 1;

        $merged     = ArrayHelper::arrayMerge($data[0], $data[1], $data[2]);
        $name       = 'expectedArray' . $iteration;
        $expected   = $this->$name();

        $this->assertEquals($expected, $merged);
        $iteration++;
    }

    /**
     * test array merge recursive
     *
     * @param array $data
     *
     * @dataProvider arrayDataProvider
     * @requires arrayDataProvider
     */
    public function testArrayMergeRecursive($data)
    {
        static $iteration = 1;

        $merged     = ArrayHelper::arrayMergeRecursive($data[0], $data[1], $data[2]);
        $name       = 'expectedArrayRecursive' . $iteration;
        $expected   = $this->$name();

        $this->assertEquals($expected, $merged);
        $iteration++;
    }

    /**
     * provide data for test array merging
     *
     * @return array
     */
    public function arrayDataProvider()
    {
        return [
            [
                [
                    [
                        0 => 0,
                        5 => 2,
                        3 => 1,
                        6 => 3,
                        1 => 4,
                    ],
                    [0, 1, 2, 3],
                    [
                        'first'     => 1,
                        'second'    => 2,
                    ],
                ],
            ],

            [
                [
                    [
                        'first' => [
                            1,
                            2,
                        ],
                        'second' => 'foo',
                        [
                            '1',
                            '2' => [
                                'x',
                                'y',
                            ],
                        ],
                    ],
                    [
                        'first' => [
                            'a',
                            'b',
                        ],
                        'second' => 'bar',
                        [
                            'e',
                            'f',
                            '2' => [
                                'q'
                            ],
                        ],
                    ],
                    [
                        'first' => [
                            1 => 'aaa',
                            6 => 'bbb'
                        ],
                        [
                            'e',
                            'f'
                        ],
                    ],
                ],
            ],
        ];
    }

    public function expectedArray1()
    {
        return [
            0           => 0,
            5           => 2,
            3           => 3,
            6           => 3,
            1           => 1,
            2           => 2,
            'first'     => 1,
            'second'    => 2,
        ];
    }

    public function expectedArrayRecursive1()
    {
        return $this->expectedArray1();
    }

    public function expectedArray2()
    {
        return [
            'first' => [
                1 => 'aaa',
                6 => 'bbb',
            ],
            'second' => 'bar',
            [
                'e',
                'f'
            ]
        ];
    }

    public function expectedArrayRecursive2()
    {
        return [
            'first' => [
                0 => 'a',
                1 => 'aaa',
                6 => 'bbb',
            ],
            'second' => 'bar',
            [
                'e',
                2 => [
                    'q',
                    'y'
                ],
                1 => 'f'
            ]
        ];
    }
}
