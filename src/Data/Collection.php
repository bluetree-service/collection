<?php

declare(strict_types=1);

/**
 * collection class to store list of Objects or other data in array
 *
 * @package     BlueCollection
 * @subpackage  Data
 * @author      Michał Adamiak    <chajr@bluetree.pl>
 * @copyright   chajr/bluetree
 * @link https://github.com/bluetree-service/collection/wiki/ClassKernel_Datacollection collection usage
 */
namespace BlueCollection\Data;

use Laminas\Serializer\Adapter\PhpSerialize;
use ArrayAccess;
use Iterator;
use Exception;
use BlueCollection\Helper\ArrayHelper;

class Collection implements ArrayAccess, Iterator
{
    /**
     * store all collection elements
     *
     * @var array
     */
    protected array $collection = [];

    /**
     * store collection element before change
     *
     * @var array
     */
    protected array $originalCollection = [];

    /**
     * default page size
     **
     * @var int
     */
    protected int $pageSize = 10;

    /**
     * number of current page
     **
     * @var int
     */
    protected int $currentPage = 1;

    /**
     * if there was some errors in object, that variable will be set on true
     *
     * @var bool
     */
    protected bool $hasErrors = false;

    /**
     * will contain list of all errors that was occurred in object
     *
     * 0 => ['error_key' => 'error information']
     *
     * @var array
     */
    protected array $errorsList = [];

    /**
     * @var bool
     */
    protected bool $dataChanged = false;

    /**
     * separator for data to return as string
     *
     * @var string
     */
    protected string $separator = ', ';

    /**
     * store list of rules to validate data
     * keys are searched using regular expression
     *
     * @var array
     */
    protected array $validationRules = [];

    /**
     * list of callbacks to prepare data before insert into object
     *
     * @var array
     */
    protected array $dataPreparationCallbacks = [];

    /**
     * list of callbacks to prepare data before return from object
     *
     * @var array
     */
    protected array $dataRetrieveCallbacks = [];

    /**
     * allow to turn off/on data validation
     *
     * @var bool
     */
    protected bool $validationOn = true;

    /**
     * allow to turn off/on data preparation
     *
     * @var bool
     */
    protected bool $preparationOn = true;

    /**
     * allow to turn off/on data retrieve preparation
     *
     * @var bool
     */
    protected bool $retrieveOn = true;

    /**
     * allow to process [section] as array key
     *
     * @var bool
     */
    protected bool $processIniSection;

    /**
     * if true loop on collection will iterate on pages, otherwise on elements
     **
     * @var bool
     */
    protected bool $loopByPages = false;

    /**
     * default constructor options
     *
     * @var array
     */
    protected array $options = [
        'data'                  => null,
        'type'                  => null,
        'validation'            => [],
        'preparation'           => [],
        'ini_section'           => false,
    ];

    /**
     * store size of original given collection
     **
     * @var int
     */
    protected int $originalCollectionSize = 0;

    /**
     * inform append* methods that data was set in object creation
     *
     * @var bool
     */
    protected bool $objectCreation = true;

    /**
     * store all new added data keys, to remove them when in eg. restore original data
     **
     * @var array
     */
    protected array $newKeys  = [];

    /**
     * store list of removed original collection keys
     *
     * @var array
     */
    protected array $removedKeys  = [];

    /**
     * create collection object
     **
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->options = \array_merge($this->options, $options);
        $data = $this->options['data'];
        $this->processIniSection = $this->options['ini_section'];

        $data = $this->beforeInitializeObject($data);
        $this->putValidationRule($this->options['validation'])
            ->putPreparationCallback($this->options['preparation']);
        $data = $this->initializeObject($data);
            

        switch (true) {
            case \is_array($data):
                $this->appendArray($data);
                break;

            case $this->options['type'] === 'serialized':
                $this->unserialize($this->options['data']);
                break;

            case $this->options['type'] === 'json':
                $this->appendJson($data);
                break;

//            case $this->options['type'] === 'xml':
//                $this->appendXml($data);
//                break;
//
//            case $this->options['type'] === 'simple_xml':
//                $this->appendSimpleXml($data);
//                break;
//
//            case $this->options['type'] === 'csv':
//                $this->appendCsv($data);
//                break;
//
//            case $this->options['type'] === 'ini':
//                $this->appendIni($data);
//                break;

            default:
                break;
        }

        $this->afterInitializeObject();
        $this->objectCreation = false;
    }

    /**
     * apply given json data as object collection
     *
     * @param string $data
     * @return void
     */
    public function appendJson(string $data): void
    {
        try {
            $jsonData = \json_decode($data, true, 512, JSON_THROW_ON_ERROR);
            $this->appendArray($jsonData);
        } catch (\JsonException $exception) {
            $this->addException($exception);
            return;
        }

        if ($this->objectCreation) {
            $this->afterAppendDataToNewObject();
        }
    }

    /**
     * apply given xml data as object collection
     *
     * @param $data string
     * @return $this
     */
    public function appendSimpleXml(string $data): self
    {
        return $this;
    }

    /**
     * apply given xml data as object collection
     * also handling attributes
     *
     * @param $data string
     * @return $this
     */
    public function appendXml(string $data): self
    {
        return $this;
    }

    /**
     * allow to set ini data into object
     *
     * @param string $data
     * @return $this
     */
    public function appendIni(string $data): self
    {
        return $this;
    }

    /**
     * allow to set csv data into object
     *
     * @param string $data
     * @return $this
     */
    public function appendCsv(string $data): self
    {
        return $this;
    }

    /**
     * return serialized collection
     *
     * @return string
     */
    public function serialize(): string
    {
        $data = null;

        try {
            $serializer = new PhpSerialize();
            $data = $serializer->serialize($this->prepareCollection());
        } catch (\Throwable $exception) {
            $this->addException($exception);
        }

        return $data;
    }

    /**
     * apply serialized collection into object
     *
     * @param string $string
     * @return $this
     */
    public function unserialize(string $string): self
    {
        $data = [];

        try {
            $serializer = new PhpSerialize();
            $data = $serializer->unserialize($string);
        } catch (\Throwable $exception) {
            $this->addException($exception);
        }

        foreach ($data as $element) {
            $this->addElement($element);
        }

        if ($this->objectCreation) {
            return $this->afterAppendDataToNewObject();
        }

        return $this;
    }

    /**
     * create exception message and set it in object
     *
     * @param Exception $exception
     * @return $this
     */
    protected function addException(Exception $exception): self
    {
        $this->hasErrors = true;
        $this->errorsList[$exception->getCode()] = [
            'message' => $exception->getMessage(),
            'line' => $exception->getLine(),
            'file' => $exception->getFile(),
            'trace' => $exception->getTraceAsString(),
        ];

        return $this;
    }

    /**
     * prepare collection before return
     *
     * @param mixed $data
     * @param bool $isSingleElement
     * @return mixed
     */
    protected function prepareCollection(mixed $data = null, bool $isSingleElement = false): mixed
    {
        if (\is_null($data) && !$isSingleElement) {
            $data = $this->collection;
        }

        if (!$this->retrieveOn) {
            return $data;
        }

        if ($isSingleElement) {
            foreach ($this->dataRetrieveCallbacks as $rule) {
                $data = $this->callUserFunction($rule, null, $data, null);
            }
        } else {
            foreach ($this->collection as $index => $element) {
                foreach ($this->dataRetrieveCallbacks as $rule) {
                    $data[$index] = $this->callUserFunction($rule, $index, $element, null);
                }
            }
        }

        return $data;
    }

    /**
     * replace changed data by original data
     * set data changed to false only if restore whole data
     * works only for original deleted data
     *
     * @param int|null $key
     * @return $this
     */
    public function restoreData(?int $key = null): self
    {
        $restored = $this->getOriginalCollection();

        if (\is_null($key)) {
            $this->collection = $restored;
            $this->dataChanged = false;
            $this->newKeys = [];
            $this->removedKeys = [];
            $this->originalCollectionSize = $this->count();
        } elseif (\array_key_exists($key, $restored)) {
            $this->restoreSingleKeyData($key);
        }

        return $this;
    }

    /**
     * restore data for single key
     *
     * @param int $key
     */
    protected function restoreSingleKeyData(int $key): void
    {
        $collection = [];
        $index = 0;
        $size = \count($this->collection) +1;

        if (!\array_key_exists($key, $this->originalCollection)) {
            return;
        }

        for ($i = 0; $i < $size; $i++) {
            if ($i === $key) {
                $collection[$i] = $this->originalCollection[$i];
                unset($this->originalCollection[$i]);
                $index++;
            } else {
                $collection[$i] = $this->collection[$i - $index];
            }
        }

        foreach ($this->newKeys  as $newKeysIndex => $newKey) {
            if ($newKey > $key) {
                ++$this->newKeys[$newKeysIndex];
            }
        }

        $index = \array_search($key, $this->removedKeys, true);
        unset($this->removedKeys [$index]);
        $this->collection = $collection;

        if (empty($this->newKeys)
            && empty($this->removedKeys)
            && empty($this->originalCollection)
        ) {
            $this->dataChanged = false;
        }
    }

    /**
     * remove all new keys from given data
     *
     * @param array $data
     * @return array
     */
    protected function removeNewKeys(array $data): array
    {
        foreach ($this->newKeys  as $key) {
            unset($data[$key]);
        }
        return $data;
    }

    /**
     * all data stored in collection became original collection
     *
     * @return $this
     */
    public function replaceDataArrays(): self
    {
        $this->originalCollection = [];
        $this->dataChanged = false;
        $this->newKeys = [];
        $this->removedKeys = [];
        $this->originalCollectionSize = $this->count();
        return $this;
    }

    /**
     * append array as collection elements
     *
     * @param array $arrayData
     * @return $this
     */
    public function appendArray(array $arrayData): self
    {
        foreach ($arrayData as $data) {
            $this->addElement($data);
        }

        if ($this->objectCreation) {
            return $this->afterAppendDataToNewObject();
        }
        return $this;
    }

    /**
     * return original data for key, before it was changed or whole original collection
     * that method don't handle return data preparation
     *
     * @param null|string $key
     * @return mixed
     */
    public function getOriginalCollection(?string $key = null): mixed
    {
        $this->prepareData($key);

        $data = $this->removeNewKeys($this->collection);
        $collection = [];
        $index = 0;

        for ($i = 0; $i < $this->originalCollectionSize; $i++) {
            if (\in_array($i, $this->removedKeys, true)) {
                $collection[$i] = null;
                $index++;
            } else {
                $collection[$i] = $data[$i - $index];
            }
        }

        $mergedData = ArrayHelper::arrayMerge(
            $collection,
            $this->originalCollection
        );

        if (!$key) {
            return $mergedData;
        }

        return $mergedData[$key] ?? null;
    }

    /**
     * alias for delete method
     *
     * @param int $index
     * @return $this
     */
    public function delete(int $index): self
    {
        return $this->removeElement($index);
    }

    /**
     * check that given index exist and allow to remove it and recalculate new index array
     **
     *
     * @param int $index
     * @return $this
     */
    protected function deleteNewKey(int $index): self
    {
        $key = \array_search($index, $this->newKeys, true);

        if ($key) {
            unset($this->newKeys [$key]);
            $this->recalculateCollectionNewIndexes();
        } else {
            $this->removedKeys [] = $index;
            \array_walk($this->newKeys , static function(&$index) {
                --$index;
            });
        }

        return $this;
    }

    /**
     * add one row element to collection
     **
     * @param mixed $data
     * @return $this
     */
    public function addElement(mixed $data): self
    {
        $bool = $this->validateData($data);
        if (!$bool) {
            return $this;
        }

        $data = $this->prepareData($data);
        $this->collection[] = $data;
        $this->dataChanged = true;

        if (!$this->objectCreation) {
            $keys = \array_keys($this->collection);
            $this->newKeys [] = \end($keys);
        } else {
            $this->originalCollectionSize++;
        }

        return $this;
    }

    /**
     * allow to change data in given index
     **
     * @param int $index
     * @param mixed $newData
     * @param null|string|Callable $callback
     * @return $this
     */
    public function changeElement(int $index, mixed $newData, null|string|callable $callback = null): self
    {
        if (!$this->hasElement($index)) {
            return $this;
        }

        $bool = $this->validateData($newData);
        if (!$bool) {
            return $this;
        }

        $newData = $this->prepareData($newData);
        $this->moveToOriginalCollection($index);

        if ($callback) {
            $this->collection[$index] = $this->callUserFunction(
                $callback,
                $index,
                $newData,
                null
            );
        } else {
            $this->collection[$index] = $newData;
        }

        return $this;
    }

    /**
     * check that data on given index is part of base object
     * and if is move it to original collection
     **
     * @param int $index
     * @return $this
     */
    protected function moveToOriginalCollection(int $index): self
    {
        if (!\array_key_exists($index, $this->originalCollection)
            && !\in_array($index, $this->newKeys, true)
        ) {
            $this->originalCollection[$index] = $this->collection[$index];
        }

        return $this;
    }

    /**
     * launch data preparation
     **
     * @param mixed $data
     * @return mixed
     */
    protected function prepareData(mixed $data): mixed
    {
        if ($this->preparationOn) {
            $data = $this->dataPreparation($data);
        }

        return $data;
    }

    /**
     * recalculate indexes of new elements in collection
     **
     * @return $this
     */
    protected function recalculateCollectionNewIndexes(): self
    {
        $totalElements = $this->count();
        $totalNewKeys = \count($this->newKeys);
        $indexList = [];

        for ($i = $totalElements; $i < $totalNewKeys -2; $i++) {
            $indexList[] = $i;
        }

        $this->newKeys = $indexList;
        return $this;
    }

    /**
     * after some changes in collection structure recalculate numeric indexes
     * of collection elements
     **
     * @return $this
     */
    protected function recalculateCollectionIndexes(): self
    {
        $totalElements = $this->count();
        $indexList = [];

        for ($i = 0; $i < $totalElements; $i++) {
            $indexList[] = $i;
        }

        $this->collection = \array_combine($indexList, $this->collection);

        return $this;
    }

    /**
     * add data preparation rules before add data into collection
     *
     * @param array $rules
     * @return $this
     */
    public function putPreparationCallback(array $rules): self
    {
        $this->dataPreparationCallbacks = \array_merge(
            $this->dataPreparationCallbacks,
            $rules
        );
        return $this;
    }

    /**
     * return all current preparation rules
     *
     * @return array
     */
    public function returnPreparationRules(): array
    {
        return $this->dataPreparationCallbacks;
    }

    /**
     * remove given preparation rule name or all rules
     *
     * @param null|string $rule
     * @return $this
     */
    public function removePreparationRules(?string $rule = null): self
    {
        if (\is_null($rule)) {
            $this->dataPreparationCallbacks = [];
        } else {
            unset($this->dataPreparationCallbacks[$rule]);
        }

        return $this;
    }

    /**
     * add data preparation rules before add data into collection
     *
     * @param array $rules
     * @return $this
     */
    public function putRetrieveCallback(array $rules): self
    {
        $this->dataRetrieveCallbacks = \array_merge(
            $this->dataRetrieveCallbacks,
            $rules
        );
        return $this;
    }

    /**
     * return all current preparation rules
     *
     * @return array
     */
    public function returnRetrieveRules(): array
    {
        return $this->dataRetrieveCallbacks;
    }

    /**
     * remove given preparation rule name or all rules
     *
     * @param null|string $rule
     * @return $this
     */
    public function removeRetrieveRules(?string $rule = null): self
    {
        if (is_null($rule)) {
            $this->dataRetrieveCallbacks = [];
        } else {
            unset($this->dataRetrieveCallbacks[$rule]);
        }

        return $this;
    }

    /**
     * allow to prepare input or output data
     *
     * @param mixed $data
     * @return mixed
     */
    protected function dataPreparation(mixed $data): mixed
    {
        foreach ($this->dataPreparationCallbacks as $rule) {
            $data = $this->callUserFunction($rule, null, $data, null);
        }

        return $data;
    }

    /**
     * return information that collection has some errors
     **
     * @return bool
     */
    public function checkErrors(): bool
    {
        return $this->hasErrors;
    }

    /**
     * return all object errors
     **
     * @return array
     */
    public function returnObjectError(): array
    {
        return $this->errorsList;
    }

    /**
     * clear all errors and set hasErrors to false
     **
     * @return $this
     */
    public function removeObjectError(): self
    {
        $this->hasErrors = false;
        $this->errorsList = [];
        return $this;
    }

    /**
     * validate data on input
     *
     * @param mixed $data
     * @return bool
     */
    protected function validateData(mixed $data): bool
    {
        if (!$this->validationOn) {
            return true;
        }

        $validateFlag = true;
        foreach ($this->validationRules as $rule) {
            $bool = $this->callUserFunction($rule, null, $data, null);

            if (!$bool) {
                $validateFlag = false;
                $this->hasErrors = true;
                $this->errorsList[] = [
                    'message' => 'validation_mismatch',
                    'index' => null,
                    'data' => $data,
                    'rule' => $rule,
                ];
            }
        }

        return $validateFlag;
    }

    /**
     * run given function, method or closure on given data
     *
     * @param array|string|\Closure $function
     * @param int|null $index
     * @param mixed $value
     * @param mixed $attributes
     * @return mixed
     */
    protected function callUserFunction(array|string|\Closure $function, ?int $index, mixed $value, mixed $attributes): mixed
    {
        if (\is_callable($function)) {
            return $function($index, $value, $this, $attributes);
        }

        return $value;
    }

    /**
     * set validation callable function to check each collection row
     * give as array with rule name and function to call
     *
     * @param array $rules
     * @return $this
     */
    public function putValidationRule(array $rules): self
    {
        $this->validationRules = \array_merge($this->validationRules, $rules);
        return $this;
    }

    /**
     * return all current rules
     *
     * @return array
     */
    public function returnValidationRules(): array
    {
        return $this->validationRules;
    }

    /**
     * remove given rule name or all rules
     *
     * @param null|string $rule
     * @return $this
     */
    public function removeValidationRules(?string $rule = null): self
    {
        if (\is_null($rule)) {
            $this->validationRules = [];
        } else {
            unset($this->validationRules[$rule]);
        }

        return $this;
    }

    /**
     * return all elements from collection
     *
     * @return mixed
     */
    public function getCollection(): mixed
    {
        return $this->prepareCollection();
    }

    /**
     * check that given page number can be set up
     *
     * @param int $pageSize
     * @return bool
     */
    protected function isPageAllowed(int $pageSize): bool
    {
        $max = $this->count() >= ($this->getPageSize() * $pageSize);
        $min = $pageSize >= 1;

        return $max && $min;
    }

    /**
     * set next page number
     *
     * @return $this
     */
    public function nextPage(): self
    {
        if ($this->isPageAllowed($this->getCurrentPage() +1)) {
            $this->currentPage++;
        }

        return $this;
    }

    /**
     * set previous page number
     *
     * @return $this
     */
    public function previousPage(): self
    {
        if ($this->isPageAllowed($this->getCurrentPage() -1)) {
            $this->currentPage--;
        }

        return $this;
    }

    /**
     * clear some data after creating new object with data
     *
     * @return $this
     */
    protected function afterAppendDataToNewObject(): self
    {
        $this->dataChanged = false;
        return $this;
    }

    /**
     * return element from collection by given index
     **
     * @param int $index
     * @return mixed
     */
    public function getElement(int $index): mixed
    {
        if ($this->hasElement($index)) {
            $data = $this->collection[$index];
            return $this->prepareCollection($data, true);
        }

        return null;
    }

    /**
     * alias for getElement method
     **
     * @param int $index
     * @return mixed
     */
    public function get(int $index): mixed
    {
        return $this->getElement($index);
    }

    /**
     * alias for changeElement method
     **
     * @param int $index
     * @param mixed $newData
     * @param null|string|Callable $callback
     * @return $this
     */
    public function change(int $index, mixed $newData, null|string|callable $callback = null): self
    {
        return $this->changeElement($index, $newData, $callback);
    }

    /**
     * remove element from collection
     **
     * @param int $index
     * @return Collection
     */
    public function removeElement(int $index): self
    {
        if (!$this->hasElement($index)) {
            return $this;
        }

        $this->moveToOriginalCollection($index)->deleteNewKey($index);
        unset($this->collection[$index]);
        $this->recalculateCollectionIndexes();
        $this->dataChanged = true;

        return $this;
    }

    /**
     * check that element exist in collection
     **
     * @param int $index
     * @return bool
     */
    public function hasElement(int $index): bool
    {
        return \array_key_exists($index, $this->collection);
    }

    /**
     * return list of indexed to update or delete when iterating by pages
     **
     * @param int $page
     * @return array
     */
    protected function getIndexesToUpdate(int $page): array
    {
        $indexes = [];
        $startIndex = $page * $this->pageSize;

        for ($i = $startIndex; $i < $page; $i++) {
            if ($this->isPageAllowed($i)) {
                $indexes[] = $i;
            }
        }

        return $indexes;
    }

    /**
     * check that data for given key exists
     *
     * @param int $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        if ($this->loopByPages) {
            return $this->isPageAllowed($offset +1);
        }

        return $this->hasElement($offset);
    }

    /**
     * return data for given key
     *
     * @param int $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        if ($this->loopByPages) {
            return $this->getPage($offset +1);
        }

        return $this->getElement($offset);
    }

    /**
     * set data for given key
     *
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($this->loopByPages) {
            $indexesToUpdate = $this->getIndexesToUpdate($offset);
            $counter = 0;

            foreach ($value as $element) {
                if (empty($indexesToUpdate)) {
                    break;
                }
                //? add element
                if ($this->hasElement($offset)) {
                    $this->changeElement($indexesToUpdate[$counter], $element);
                } else {
                    $this->addElement($element);
                }
                $counter++;
                unset($indexesToUpdate[$counter]);
            }
        } elseif ($this->hasElement($offset)) {
            $this->changeElement($offset, $value);
        } else {
            $this->addElement($value);
        }
    }

    /**
     * remove data for given key
     *
     * @param int $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        if ($this->loopByPages) {
            $indexesToRemove = $this->getIndexesToUpdate($offset +1);
            foreach ($indexesToRemove as $index) {
                $this->delete($index);
            }
        } else {
            $this->delete($offset);
        }
    }

    /**
     * return the current element in an array
     * handle data preparation
     *
     * @return mixed
     */
    public function current(): mixed
    {
        $key = $this->key();

        if ($this->loopByPages) {
            return $this->getPage($key);
        }

        return $this->getElement($key);
    }

    /**
     * return the current element in an array
     *
     * @return string|int|null
     */
    public function key(): string|int|null
    {
        if ($this->loopByPages) {
            return $this->getCurrentPage();
        }

        return \key($this->collection);
    }

    /**
     * advance the internal array pointer of an array
     * handle data preparation
     *
     * @return void
     */
    public function next(): void
    {
        $key = $this->key();

        if ($this->loopByPages) {
            $this->setCurrentPage($key +1);
            $this->getPage($this->getCurrentPage());
        } else {
            \next($this->collection);
            $this->getElement($key);
        }
    }

    /**
     * rewind the position of a file pointer
     *
     * @return void
     */
    public function rewind(): void
    {
        if ($this->loopByPages) {
            $this->setCurrentPage(1);
            $this->getFirstPage();
        } else {
            \reset($this->collection);
        }
    }

    /**
     * checks if current position is valid
     *
     * @return bool
     */
    public function valid(): bool
    {
        if ($this->loopByPages) {
            return $this->isPageAllowed($this->key());
        }

        return key($this->collection) !== null;
    }

    /**
     * allow to stop data validation
     *
     * @return $this
     */
    public function stopValidation(): self
    {
        $this->validationOn = false;
        return $this;
    }

    /**
     * allow to start data validation
     *
     * @return $this
     */
    public function startValidation()
    {
        $this->validationOn = true;
        return $this;
    }

    /**
     * return information that validation is on
     **
     * @return bool
     */
    public function isValidationOn(): bool
    {
        return $this->validationOn;
    }

    /**
     * allow to stop data preparation before add ito object
     *
     * @return $this
     */
    public function stopInputPreparation(): self
    {
        $this->preparationOn = false;
        return $this;
    }

    /**
     * allow to start data preparation before add tro object
     *
     * @return $this
     */
    public function startInputPreparation(): self
    {
        $this->preparationOn = true;
        return $this;
    }

    /**
     * allow to stop data preparation before return them from object
     *
     * @return $this
     */
    public function stopOutputPreparation(): self
    {
        $this->retrieveOn = false;
        return $this;
    }

    /**
     * allow to start data preparation before return them from object
     *
     * @return $this
     */
    public function startOutputPreparation(): self
    {
        $this->retrieveOn = true;
        return $this;
    }

    /**
     * return first element from collection
     *
     * @return mixed
     */
    public function first(): mixed
    {
        $data = \reset($this->collection);
        return $this->prepareCollection($data, true);
    }

    /**
     * return last element from collection
     *
     * @return mixed
     */
    public function last(): mixed
    {
        $data = \end($this->collection);
        return $this->prepareCollection($data, true);
    }

    /**
     * return number of all elements in collection
     *
     * @return int|null
     */
    public function count(): ?int
    {
        return \count($this->collection);
    }

    /**
     * return object data as serialized string representation
     *
     * @return string
     */
    public function __toString()
    {
        return $this->serialize();
    }

    /**
     * set default size for collection elements on page to return
     *
     * @param int $size
     * @return $this
     */
    public function setPageSize(int $size): self
    {
        $this->pageSize = $size;
        return $this;
    }

    /**
     * return size for collection elements on page to return
     *
     * @return int
     */
    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    /**
     * return current page number
     *
     * @return int
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * allow to set current page
     *
     * @param int $page
     * @return $this
     */
    public function setCurrentPage(int $page): self
    {
        $this->currentPage = $page;
        return $this;
    }

    /**
     * return number of all pages
     *
     * @return int
     */
    public function countPages(): int
    {
        return (int)\ceil($this->count() / $this->getPageSize());
    }

    /**
     * get all elements from first page
     *
     * @return array|mixed
     */
    public function getFirstPage(): mixed
    {
        $data = $this->getPageInternal(1);
        return $this->prepareCollection($data);
    }

    /**
     * get all elements from last page
     **
     * @return mixed
     */
    public function getLastPage(): mixed
    {
        $data = $this->getPageInternal($this->countPages());
        return $this->prepareCollection($data);
    }

    /**
     * return elements for page with given index
     **
     * @param int $index
     * @return array
     */
    protected function getPageInternal(int $index): array
    {
        $pageSize = $this->getPageSize();
        $start = ($index * $pageSize) - $pageSize;
        return \array_slice($this->collection, $start, $pageSize);
    }

    /**
     * return current page or page with given index
     * return null if page don't exists
     **
     *
     * @param int|null $index
     * @return mixed|null
     */
    public function getPage(?int $index = null): mixed
    {
        if (!$index) {
            $index = $this->getCurrentPage();
        }

        if (!$this->isPageAllowed($index)) {
            return null;
        }

        $data = $this->getPageInternal($index);
        return $this->prepareCollection($data);
    }

    /**
     * get next page of collection
     * don't change the current page marker
     **
     * @return array|null
     */
    public function getNextPage(): ?array
    {
        $page = $this->getCurrentPage() +1;

        if (!$this->isPageAllowed($page)) {
            return null;
        }

        return $this->getPage($page);
    }

    /**
     * get previous page of collection
     * don't change the current page marker
     *
     * @return array|null
     */
    public function getPreviousPage(): ?array
    {
        $page = $this->getCurrentPage() -1;

        if (!$this->isPageAllowed($page)) {
            return null;
        }

        return $this->getPage($page);
    }

    /**
     * set loop on collection to iterate on pages, if false on elements
     **
     * @param bool $bool
     * @return $this
     */
    public function loopByPages(bool $bool = true): self
    {
        $this->loopByPages = $bool;
        return $this;
    }

    /**
     * return information witch loop is used for collection
     **
     * @return bool
     */
    public function isLoopByPagesEnabled(): bool
    {
        return $this->loopByPages;
    }

    /**
     * can be overwritten by children objects to start with some special operations
     * as parameter take data given to object by reference
     *
     * @param mixed $data
     * @return mixed
     */
    protected function initializeObject(mixed $data): mixed
    {
        return $data;
    }

    /**
     * can be overwritten by children objects to start with some special operations
     */
    protected function afterInitializeObject(): void
    {

    }

    /**
     * can be overwritten by children objects to start with some special operations
     * as parameter take data given to object by reference
     *
     * @param mixed $data
     * @return mixed
     */
    protected function beforeInitializeObject(mixed $data): mixed
    {
        return $data;
    }
}
