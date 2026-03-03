<?php
/**
 * test Collection Object class
 *
 * @package     BlueCollection
 * @subpackage  Test
 * @author      Michał Adamiak    <chajr@bluetree.pl>
 * @copyright   chajr/bluetree
 */
namespace Test;

use BlueCollection\Data\Collection;
use BlueContainer\Container;
use PHPUnit\Framework\TestCase;
use Laminas\Serializer\Adapter\PhpSerialize;
use PHPUnit\Framework\Attributes\DataProvider;

class CollectionTest extends TestCase
{
    /**
     * test basic object creation
     *
     * @param Collection $collection
     * @param array $data
     */
    #[DataProvider('exampleCollectionObject')]
    public function testCreateCollection($collection, array $data)
    {
        $this->assertEquals('lorem ipsum', $collection->first());
        $this->assertEquals($data[1]['data_first'], $collection->getElement(1)['data_first']);

        $collection = new Collection;
        $collection->appendArray($data);

        $this->assertEquals('lorem ipsum', $collection->first());
        $this->assertEquals($data[1]['data_first'], $collection->getElement(1)['data_first']);
    }

    /**
     * test basic object creation with json data
     */
    public function testCreateJsonCollection()
    {
        $data = self::exampleCollection();
        unset($data[7]);
        unset($data[8]);

        $json = json_encode($data);

        $collection = new Collection([
            'data'  => $json,
            'type'  => 'json'
        ]);

        $this->assertEquals('lorem ipsum', $collection->first());
        $this->assertEquals($data[1]['data_first'], $collection->getElement(1)['data_first']);
    }

    /**
     * test basic object creation with json data
     */
    public function testCreateJsonCollectionWithError()
    {
        $json = '["lorem ipsum",{"data_first":1,"data_second":2,"data_third":3},{"data_first":true,"data_second":fals';

        $collection = new Collection([
            'data'  => $json,
            'type'  => 'json'
        ]);

        $this->assertNotEquals('lorem ipsum', $collection->first());
        $this->assertTrue($collection->checkErrors());
        $this->assertEquals('Syntax error', $collection->returnObjectError()[4]['message']);
    }

    /**
     * test basic object creation with serialized data
     */
    public function testCreateSerializedCollection()
    {
        $data = self::exampleCollection();
        unset($data[7]);
        unset($data[8]);

        $serializer = new PhpSerialize();
        $serialized = $serializer->serialize($data);

        $collection = new Collection([
            'data'  => $serialized,
            'type'  => 'serialized'
        ]);

        $this->assertEquals('lorem ipsum', $collection->first());
        $this->assertEquals($data[1]['data_first'], $collection->getElement(1)['data_first']);
    }

    #[DataProvider('exampleCollectionObject')]
    public function testSerializeCollection($collection, array $data)
    {
        $serialized = $collection->serialize();
        $unserializedCollection = new Collection([
            'data'  => $serialized,
            'type'  => 'serialized'
        ]);

        $this->assertEquals('lorem ipsum', $unserializedCollection->first());
        $this->assertEquals($data[1]['data_first'], $unserializedCollection->getElement(1)['data_first']);

        $serialized2 = (string)$collection;
        $this->assertEquals($serialized2, $serialized);
    }

    /**
     * check validation rules when add data to collection on object creation
     */
    public function testCreateCollectionWithValidation()
    {
        $data = self::exampleCollection();
        $validationRules    = [
            'rule_1' => function ($index, $value) {
            if (is_string($value)) {
                return $value === 'lorem ipsum';
            }

                return true;
            },
            'rule_2' => function ($index, $value) {
            if (is_array($value) || is_object($value)) {
                return isset($value['data_second']);
            }

            return true;
            }
        ];

        $collection = new Collection([
            'data'          => $data,
            'validation'    => $validationRules
        ]);
        
        $rules = $collection->returnValidationRules();
        $this->assertNotEmpty($rules);
        $this->assertInstanceOf(\Closure::class, $rules['rule_1']);

        $this->assertFalse($collection->checkErrors());
        $this->assertEmpty($collection->returnObjectError());
        
        $collection->changeElement(0, 'lorem ipsum dolor');
        $this->assertTrue($collection->checkErrors());
        $this->assertNotEmpty($collection->returnObjectError());
        $this->assertEquals(
            'validation_mismatch',
            $collection->returnObjectError()[0]['message']
        );
        
        $collection->removeValidationRules();
        $rules = $collection->returnValidationRules();
        $this->assertEmpty($rules);

        $validationRules    = [
            'rule_1' => function ($index, $value) {
            if (is_string($value)) {
                return $value === 'lorem ipsum dolor';
            }

            return true;
            },
        ];

        $collection = new Collection();
        $collection->startValidation();
        $collection->putValidationRule($validationRules);
        $collection->appendArray($data);

        $this->assertTrue($collection->checkErrors());
        $this->assertNull($collection->returnObjectError()[0]['index']);
        $this->assertEquals(
            'validation_mismatch',
            $collection->returnObjectError()[0]['message']
        );
        
        $collection->removeObjectError();
        $this->assertFalse($collection->checkErrors());
        $this->assertEmpty($collection->returnObjectError());
        
        $this->assertTrue($collection->isValidationOn());
        $collection->stopValidation();
        $this->assertFalse($collection->isValidationOn());
        $collection->appendArray($data);

        $this->assertFalse($collection->checkErrors());
        $this->assertEmpty($collection->returnObjectError());
    }

    /**
     * test data preparation when object is creating
     */
    public function testCreateCollectionWithDataPreparation()
    {
        $data = self::exampleCollection();
        $preparationRules   = [
            'rule_1' => function ($index, $value) {
            if ($value instanceof Container) {
                $value->setTestKey('test key');
            }

            return $value;
            },
        ];

        $collection = new Collection([
            'data'          => $data,
            'preparation'   => $preparationRules
        ]);

        $this->assertNotEmpty($collection->returnPreparationRules());
        $this->assertInstanceOf(\Closure::class, $collection->returnPreparationRules()['rule_1']);

        $this->assertEquals('test key', $collection[7]->getTestKey());
        $this->assertEquals('test key', $collection[8]->getTestKey());

        $collection->removePreparationRules();
        $this->assertEmpty($collection->returnPreparationRules());
    }

    /**
     * test data preparation when collection return some elements
     */
    public function testReturnCollectionWithDataPreparation()
    {
        $data = self::exampleCollection();
        $preparationRules   = [
            'rule_1' => function ($index, $value) {
                if ($value instanceof Container) {
                    $value->setTestKey('test return key');
                }

                return $value;
            },
        ];

        $collection = new Collection([
            'data'          => $data,
        ]);

        $collection->startOutputPreparation();
        $this->assertNull($collection[7]->getTestKey());
        $this->assertNull($collection[8]->getTestKey());

        $collection->putRetrieveCallback($preparationRules);
        
        $this->assertNotEmpty($collection->returnRetrieveRules());

        $this->assertEquals('test return key', $collection[7]->getTestKey());
        $this->assertEquals('test return key', $collection[8]->getTestKey());

        $data = $collection->getCollection();
        $this->assertEquals('lorem ipsum', $data[0]);
        $this->assertEquals($data[1]['data_first'], $collection->getElement(1)['data_first']);
        
        $collection->stopOutputPreparation();
        $this->assertEquals('lorem ipsum', $collection->first());
        $this->assertEquals($data[1]['data_first'], $collection->getElement(1)['data_first']);

        $collection->removeRetrieveRules();
        $this->assertEmpty($collection->returnRetrieveRules());
    }

    /**
     * check usage collection as array (access data and loop processing)
     *
     * @param Collection $collection
     * @param array $data
     */
    #[DataProvider('exampleCollectionObject')]
    public function testArrayAccessForCollection($collection, array $data)
    {
        foreach ($collection as $index => $element) {
            $this->assertEquals($data[$index], $element);
        }
    }

    /**
     * test some basic access to single collection elements
     *
     * @param Collection $collection
     * @param array $data
     */
    #[DataProvider('exampleCollectionObject')]
    public function testBasicAccessToCollectionElements($collection, array $data)
    {
        $this->assertEquals('lorem ipsum', $collection->first());
        $this->assertEquals('lorem ipsum', $collection[0]);
        $this->assertEquals(1, $collection->last()['data_first']);
        $this->assertEquals($data[1]['data_first'], $collection->getElement(1)['data_first']);
        $this->assertEquals(9, $collection->count());
        $this->assertTrue($collection->hasElement(5));

        $this->assertFalse(isset($collection[9]));
        $collection[9] = 'offset 9';
        $this->assertTrue(isset($collection[9]));
        $this->assertEquals('offset 9', $collection[9]);

        $collection[11] = 'offset 11';
        $this->assertFalse(isset($collection[11]));
        $this->assertTrue(isset($collection[10]));
        $this->assertEquals('offset 11', $collection[10]);
    }

    /**
     * test correct set page size and count available pages
     *
     * @param Collection $collection
     */
    #[DataProvider('exampleCollectionObject')]
    public function testPageInformation($collection)
    {
        $collection->setPageSize(2);
        $this->assertEquals(2, $collection->getPageSize());
        $this->assertEquals(5, $collection->countPages());
    }

    /**
     * test access to collection using pages
     *
     * @param Collection $collection
     * @param array $data
     */
    #[DataProvider('exampleCollectionObject')]
    public function testPageAccessForCollection($collection, array $data)
    {
        $collection->setPageSize(2);
        $this->assertEquals(2, count($collection->getFirstPage()));
        $this->assertEquals(1, count($collection->getLastPage()));
        $this->assertEquals([$data[0], $data[1]], $collection->getFirstPage());
        $this->assertEquals([$data[8]], $collection->getLastPage());
        $this->assertEquals([$data[2], $data[3]], $collection->getPage(2));
        $this->assertNull($collection->getPage(10));
        $this->assertEquals(1, $collection->getCurrentPage());

        $collection->nextPage();

        $this->assertEquals(2, $collection->getCurrentPage());
        $this->assertEquals([$data[4], $data[5]], $collection->getNextPage());
        $this->assertEquals([$data[0], $data[1]], $collection->getPreviousPage());

        $collection->previousPage();

        $this->assertEquals(1, $collection->getCurrentPage());
        $this->assertEquals([$data[2], $data[3]], $collection->getNextPage());
    }

    /**
     * test array access to collection using pages
     *
     * @param Collection $collection
     * @param array $data
     */
    #[DataProvider('exampleCollectionObject')]
    public function testArrayAccessToCollectionPages($collection, array $data)
    {
        $collection->setPageSize(2);

        $this->assertEquals($data[0], $collection[0]);

        $collection->loopByPages();
        $this->assertTrue($collection->isLoopByPagesEnabled());

        $this->assertEquals([$data[0], $data[1]], $collection[0]);
        $this->assertEquals([$data[4], $data[5]], $collection[2]);

        foreach ($collection as $index => $element) {
            $this->assertEquals($collection->getPage($index), $element);
        }

//        $this->assertFalse(isset($collection[4]));
//        $collection[4] = [
//            'offset 4',
//            'offset 4.1',
//        ];
//        dump($collection->getCollection());
//        $this->assertTrue(isset($collection[4]));
//        $this->assertEquals('offset 9', $collection[9]);
//
//        $collection[11] = 'offset 11';
//        $this->assertFalse(isset($collection[11]));
//        $this->assertTrue(isset($collection[10]));
//        $this->assertEquals('offset 11', $collection[10]);
    }

    /**
     * test add, modify and delete elements from collection
     *
     * @param Collection $collection
     */
    #[DataProvider('exampleCollectionObject')]
    public function testElementCRUD($collection)
    {
        $collection->addElement('some new element');

        $this->assertEquals(10, $collection->count());
        $this->assertEquals('some new element', $collection->get(9));

        $collection->delete(3);
        $this->assertEquals(9, $collection->count());
        $this->assertEquals('some new element', $collection->get(8));

        $collection->addElement('some new element 2');
        $this->assertEquals('some new element 2', $collection->get(9));

        $collection->changeElement(0, 'changed lorem ipsum');
        $this->assertEquals('changed lorem ipsum', $collection->getElement(0));

        $this->assertNull($collection->getElement(100));

        $collection->change(7, 'new data', function($index, $newData, $collection) {
            /** @var Collection $collection*/
            $object = $collection->getElement($index);
            $object->setNewData($newData);

            return $object;
        });
        $this->assertEquals('new data', $collection->getElement(7)->getNewData());
    }

    /**
     * test add, modify and delete elements from collection with original collection save
     *
     * @param Collection $collection
     */
    #[DataProvider('exampleCollectionObject')]
    public function testElementCRUDWithOriginalData($collection)
    {
        $originalCollection = clone $collection;
        $collection->addElement('some new element');

        $this->assertNotEquals($collection->getCollection(), $collection->getOriginalCollection());
        $this->assertEquals($originalCollection->getCollection(), $collection->getOriginalCollection());

        $collection->addElement('some new element 2');
        $this->assertEquals($originalCollection->getCollection(), $collection->getOriginalCollection());

        $collection->changeElement(0, 'changed lorem ipsum');
        $this->assertEquals($originalCollection->getCollection(), $collection->getOriginalCollection());

        $collection->delete(3);
        $this->assertNotEquals($collection->getCollection(), $collection->getOriginalCollection());
        $this->assertEquals($originalCollection->getCollection(), $collection->getOriginalCollection());

        $collection->delete(2);
        $this->assertNotEquals($collection->getCollection()[2], $collection->getOriginalCollection(2));
        $this->assertEquals($originalCollection->getCollection()[2], $collection->getOriginalCollection(2));

        $this->assertArrayHasKey(8, $collection->getCollection());
        $collection->delete(8);
        $this->assertArrayNotHasKey(8, $collection->getCollection());
    }

    /**
     * test add, modify and delete elements from collection with original collection save
     *
     * @param Collection $collection
     */
    #[DataProvider('exampleCollectionObject')]
    public function testOriginalCollectionRevertAndReplace($collection)
    {
        $originalCollection = clone $collection;
        $collection->addElement('some new element');
        $collection->addElement('some new element 2');
        $collection->changeElement(0, 'changed lorem ipsum');

        $this->assertEquals($originalCollection->getCollection(), $collection->getOriginalCollection());

        $collection->restoreData();

        $this->assertEquals($originalCollection->getCollection(), $collection->getCollection());

        $collection->addElement('some new element');
        $collection->addElement('some new element 2');
        $collection->changeElement(0, 'changed lorem ipsum');

        $collection->restoreData(1);
        $this->assertNotEquals($originalCollection->getCollection(), $collection->getCollection());

        $collection->restoreData(0);
        $this->assertEquals($originalCollection->getCollection()[0], $collection->getCollection()[0]);
    }

    /**
     * @param Collection $collection
     */
    #[DataProvider('exampleCollectionObject')]
    public function testOriginalCollectionReplace($collection)
    {
        $originalCollection = clone $collection;
        $collection->addElement('some new element');
        $collection->addElement('some new element 2');
        $collection->changeElement(0, 'changed lorem ipsum');

        $this->assertEquals($originalCollection->getCollection(), $collection->getOriginalCollection());
        
        $collection->replaceDataArrays();
        $collection->restoreData();

        $this->assertNotEquals($originalCollection->getCollection(), $collection->getCollection());
    }

    /**
     * create collection object for test
     *
     * @return array
     * @throws \JsonException
     */
    public static function exampleCollectionObject(): array
    {
        $data = self::exampleCollection();
        return [[
            new Collection([
                'data'  => $data
            ]),
            $data
        ]];
    }

    /**
     * return some data to test collection functionality
     * 
     * @return array
     */
    public static function exampleCollection()
    {
        $object = new Container(
            [
                'data' => [
                    'data_first'    => 'first',
                    'data_second'   => 2,
                    'data_third'    => false,
                ]
            ]
        );
        $object2 = clone $object;
        $object2->destroy();
        $object2->set([
            'data_first'    => 1,
            'data_second'   => 'second',
            'data_third'    => true,
        ]);

        return [
            'lorem ipsum',
            [
                'data_first'    => 1,
                'data_second'   => 2,
                'data_third'    => 3,
            ],
            [
                'data_first'    => true,
                'data_second'   => false,
                'data_third'    => null,
            ],
            [
                'data_first'    => 'first',
                'data_second'   => 'second',
                'data_third'    => 'third',
            ],
            [
                'data_first'    => '001',
                'data_second'   => '002',
                'data_third'    => '003',
            ],
            [
                'data_first'    => [1, 2, 3],
                'data_second'   => [4, 5, 6],
                'data_third'    => [7, 8, 9],
            ],
            [
                'data_first'    => 4,
                'data_second'   => 5,
                'data_third'    => 6,
            ],
            $object,
            $object2
        ];
    }
}
