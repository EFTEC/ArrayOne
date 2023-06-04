# ArrayOne
It is a minimalist library that process arrays in PHP.

This library is focused to work with business data(reading/saving files, database records, API, etc.), so it is not similar to Numpy, Pandas, NumPHP or alike because they target difference objectives. It is more closely similar to Microsoft PowerQuery.
What it does? Filter, order, renaming column, grouping, validating, amongst many other operations.


- [x] it works with PHP arrays. PHP arrays allows hierarchy structures using indexed and/or associative values.
- [x] It is aimed at speed.
- [x] It is minimalist, using the minimum of dependencies and only 1 PHP class.  Do you hate when a simple library adds a whole framework as dependency? Well, not here.
- [x] It works using fluent/nested notations.
- [x] Every method is documented using PhpDoc.

[![Packagist](https://img.shields.io/packagist/v/eftec/ArrayOne.svg)](https://packagist.org/packages/eftec/ArrayOne)
[![Total Downloads](https://poser.pugx.org/eftec/ArrayOne/downloads)](https://packagist.org/packages/eftec/ArrayOne)
[![Maintenance](https://img.shields.io/maintenance/yes/2023.svg)]()
[![composer](https://img.shields.io/badge/composer-%3E1.6-blue.svg)]()
[![php](https://img.shields.io/badge/php-7.1-green.svg)]()
[![php](https://img.shields.io/badge/php-8.x-green.svg)]()
[![CocoaPods](https://img.shields.io/badge/docs-70%25-yellow.svg)]()

<!-- TOC -->
* [ArrayOne](#arrayone)
  * [Basic examples](#basic-examples)
  * [Getting started](#getting-started)
  * [Concepts](#concepts)
  * [initial operator](#initial-operator)
    * [set](#set)
    * [setRequest](#setrequest)
    * [setJson](#setjson)
    * [setCsv](#setcsv)
    * [setCsvHeadless](#setcsvheadless)
  * [middle operator](#middle-operator)
    * [col](#col)
    * [columnToIndex](#columntoindex)
    * [filter](#filter)
    * [first](#first)
    * [flat](#flat)
    * [group](#group)
      * [example](#example)
    * [indexToCol](#indextocol)
    * [join](#join)
    * [last](#last)
    * [map](#map)
    * [mask](#mask)
    * [nav](#nav)
    * [npos](#npos)
    * [reduce](#reduce)
    * [removecol](#removecol)
    * [removerow](#removerow)
    * [removeFirstRow](#removefirstrow)
  * [removeLastRow](#removelastrow)
    * [removeDuplicate](#removeduplicate)
    * [modCol](#modcol)
    * [sort](#sort)
    * [createValidateExample](#createvalidateexample)
    * [validate](#validate)
  * [end operators](#end-operators)
    * [all](#all)
    * [result](#result)
  * [other methods](#other-methods)
    * [makeValidateArrayByExample](#makevalidatearraybyexample)
    * [makeRequestArrayByExample](#makerequestarraybyexample)
  * [versions](#versions)
  * [License](#license)
<!-- TOC -->

## Basic examples

```php
// Reducing an array using aggregate functions:
$invoice=[
    'id'=>1,
    'date'=>new DateTime('now'),
    'customer'=>10,
    'detail'=>[
        ['idproduct'=>1,'unitPrice'=>200,'quantity'=>3],
        ['idproduct'=>2,'unitPrice'=>300,'quantity'=>4],
        ['idproduct'=>3,'unitPrice'=>300,'quantity'=>5],
    ]
];
$arr=ArrayOne::set($invoice)
    ->nav('detail')
    ->reduce(['unitPrice'=>'sum','quantity'=>'sum'])
    ->current(); //['unitPrice'=>800,'quanty'=>12]
```

## Getting started

First, you must install the library. You can download this library or use Composer for its installation:

> composer require eftec/arrayone

Once the library is installed and included, you can use as:

```php
use eftec\ArrayOne;
ArrayOne::set($array); // Initial operator: $array is our initial array.
    ->someoperator1()  // Middle operator: here we do one or many operations to transform the array
    ->someoperator2()
    ->someoperator3()
    ->current(); // End operator: and we get the end result that usually is an array but it could be even a literal.
```

## Concepts

```php
$array=['hello'  // indexed field
       'field2'=>'world', // named field
       'fields'=>[   // a field with sub-fields
           'alpha'=>1,
           'beta'=>2
       ],
       'table'=>[ // a field with a list of values (a table)
           ['id'=>1,'name'=>'red'],
           ['id'=>2,'name'=>'orange'],
           ['id'=>3,'name'=>'blue'],           
       ]
   ];
```

* indexed and named fields works similarly.
* When a field contains an array, then you can "navigate" inside it using the command nav(). In the case of the field called **fields** is nav("field")
* Sometimes, some field contains an array of values that behave like a table (see **table** field)

## initial operator

Initial operator is the first operator of the chain.

### set

It sets the array to be transformed, and it starts the pipeline.
It must be the first operator unless you are using the constructor.

```php
ArrayOne::set($array)->all();
ArrayOne::set($array,$object)->all(); // the object is used by validation()
ArrayOne::set($array,SomeClass:class)->all(); // the object is used by validation()
```
* **parameter** array|null         $array
* **parameter**  object|null|string $service the service instance. You can use the class or an object.

### setRequest
It sets the initial array readint the values from the request (get/post/header/etc).  
**Example:**
```php
ArrayOne::setRequest([
    'id'=>'get', // $_GET['id'] if not found then it uses the default value (null)
    'name'=>'post|default', // $_POST['name'], if not found then it uses "default"
    'content'=>'body' // it reads from the POST body
],null); // null is the default value if not other default value is set.
```
* **parameter** array   $fields An associative array when the values to read 'id'=>'type;defaultvalue'.
  Types:  
  <b>get</b>: get it from the query string   
  <b>post</b>: get it from the post  
  <b>header</b>: get if from the header  
  <b>request</b>: get if from the post, otherwise from get  
  <b>cookie</b>: get if from the cookies  
  <b>body</b>: get if from the post body (values are not serialized)  
  <b>verb</b>: get if from the request method (GET/POST/PUT,etc.)
* **parameter** mixed   $defaultValueAll the default value if the value is not found and not other default value is set.
* **parameter** ?string $separator       Def:'.', The separator character used when the field is nested.  
  example using '.' as separator html:<input name='a.b' value="hello" />  
  result obtained:$result\['a']\['b']='hello';
* **return value** ArrayOne



### setJson
It sets the array using a json.
**Example:**
```php
ArrayOne::setJson('{"a":3,"b":[1,2,3]}')->all();
```
* **parameter** string $json the value to parse.

### setCsv
It sets the array using a csv. This csv must have a header.   
**Example:**
```php
ArrayOne::setCsv("a,b,c\n1,2,3\n4,5,6")->all();
```
* **parameter** string $string the string to parse
* **parameter** string $separator default ",". Set the field delimiter (one character only).
* **parameter** string $enclosure default '"'. Set the field enclosure character (one character only).
* **parameter** string $escape default "\\". Set the escape character (one character only).

### setCsvHeadless
It sets the array using a head-less csv.   
**Example:**
```php
ArrayOne::setCsvHeadLess("1,2,3\n4,5,6")->all(); 
ArrayOne::setCsvHeadLess("1,2,3\n4,5,6",['c1','c2','c3'])->all();
```
* **parameter** string $string the string to parse
* **parameter** array|null $header If the header is null, then it creates an indexed array.   
  if the header is an array, then it is used as header
* **parameter** string $separator default ",". Set the field delimiter (one character only).
* **parameter** string $enclosure default '"'. Set the field enclosure character (one character only).
* **parameter** string $escape default "\\". Set the escape character (one character only).

## middle operator
Middle operators are operators that are called between the initial operator **set()** and the end operator **all()** or **current()**.
They do the transformation and they could be stacked.

**example:**
```php
ArrayOne::set($array)
    ->nav('field') // middle operator #1
    ->group('col1',['col2'=>'sum']) // middle operator #2
    ->all();
```

### col
Returns a single column as an array of values.   
**Example:**
```php
$this->col('c1'); // [['c1'=>1,'c2'=>2],['c1'=>3,'c2'=>4]] => [['c1'=>1],['c1'=>3]];
```
### columnToIndex
it converts a column into an index   
**Example:**
```php
$this->indexToField('colold'); //  [['colold'=>'a','col1'=>'b','col2'=>'c'] => ['a'=>['col1'=>'b','col2'=>'c']]
```
* **parameter** mixed $oldColumn the old column. This column will be converted into an index

### filter
It filters the values. If the condition is false, then the row is deleted. It uses array_filter()   
The indexes are not rebuilt.
**Example:**
```php
$array = [['id' => 1, 'name' => 'chile'], ['id' => 2, 'name' => 'argentina'], ['id' => 3, 'name' => 'peru']];
// ['id' => 2, 'name' => 'argentina']
$r = ArrayOne::set($array)->filter(function($id, $row) {return $row['id'] === 2;}, true)->current();
// [1=>['id' => 2, 'name' => 'argentina']]
$r = ArrayOne::set($array)->filter(function($id, $row) {return $row['id'] === 2;}, false)->current();
```
### first
It returns the first element of an array.
* **return value** $this
### flat
It flats the results. If the result is an array with a single row, then it returns the row without the array   
**Example:**
```php
$this->flat(); // [['a'=>1,'b'=>2]] => ['a'=>1,'b'=>2]
```
* **return value** $this
### group
It groups one column and return its column grouped and values aggregated.  
> The grouped value is used as the new index key.

**Example:**
```php
$this->group('type',['amount'=>'sum','price'=>'sum']); // ['type1'=>['amount'=>20,'price'=>30]]
$this->group('type',['am'=>'sum','pri'=>'sum','grp'=>'group'],false); // [['am'=>20,'pri'=>30,'grp'=>'type1']]
$this->group('type',['newcol'=>'sum(amount)','price'=>'sum(price)']);
```
* **parameter** mixed $column the column to group.
* **parameter** array $functionAggregation An associative array ['col-to-agregate'=>'aggregation']  
  or ['new-col'=>'aggregation(col-to-agregate)']   
  <b>stack</b>: It stack the rows grouped by the column (like a pivot table).  
  <b>count</b>: Count   
  <b>avg</b>: Average   
  <b>min</b>: Minimum   
  <b>max</b>: Maximum   
  <b>sum</b>: Sum   
  <b>first</b>: First   
  <b>last</b>: last  
  <b>group</b>: The grouped value
* **parameter** bool  $useGroupAsIndex     (def true), if true, then the result will use the grouped value as index<br>
                                           if false, then the result will return the values as an indexed array.
#### example

```php
$array=[
    ['cat'=>'cat1','col_min'=>1,'col_max'=>1,'col_sum'=>1,'col_avg'=>1,'col_first'=>'john1','col_last'=>'doe1'],
    ['cat'=>'cat2','col_min'=>2,'col_max'=>2,'col_sum'=>2,'col_avg'=>2,'col_first'=>'john2','col_last'=>'doe2'],
    ['cat'=>'cat3','col_min'=>3,'col_max'=>3,'col_sum'=>3,'col_avg'=>3,'col_first'=>'john3','col_last'=>'doe3'],
    ['cat'=>'cat1','col_min'=>4,'col_max'=>4,'col_sum'=>4,'col_avg'=>4,'col_first'=>'john4','col_last'=>'doe4'],
    ['cat'=>'cat2','col_min'=>5,'col_max'=>5,'col_sum'=>5,'col_avg'=>5,'col_first'=>'john5','col_last'=>'doe5']
    ];
$result=ArrayOne::set($array)
    ->group('cat',[
        'col_min'=>'min',
        'col_max'=>'max',
        'col_sum'=>'sum',
        'col_avg'=>'avg',
        'col_count'=>'count',
        'col_first'=>'first',
        'col_last'=>'last',
    ])
    ->all();
/* [
    'cat1' =>
        ['col_min' => 1, 'col_max' => 4, 'col_sum' => 5, 'col_avg' => 2.5, 'col_first' => 'john1', 'col_last' => 'doe4', 'col_count' => 2,],
    'cat2' =>
        ['col_min' => 2, 'col_max' => 5, 'col_sum' => 7, 'col_avg' => 3.5, 'col_first' => 'john2', 'col_last' => 'doe5', 'col_count' => 2,],
    'cat3' =>
        ['col_min' => 3, 'col_max' => 3, 'col_sum' => 3, 'col_avg' => 3, 'col_first' => 'john3', 'col_last' => 'doe3', 'col_count' => 1,],
];

```

### indexToCol

It converts the index into a field, and renumerates the array   
**Example:**
```php
$this->indexToCol('colnew'); // ['a'=>['col1'=>'b','col2'=>'c']] => [['colnew'=>'a','col1'=>'b','col2'=>'c']
```
* **parameter** mixed $newColumn the name of the new column
* **return value** $this
### join
Joins the current array with another array   
If the columns of both arrays have the same name, then the current name is retained.   
**Example:**
```php
$products=[['id'=>1,'name'=>'cocacola','idtype'=>123]];
$types=[['id'=>123,'desc'=>'it is the type #123']];
ArrayOne::set($products)->join($types,'idtype','id')->all()
// [['id'=>1,'prod'=>'cocacola','idtype'=>123,'desc'=>'it is the type #123']] "id" is from product.
```
* **parameter** array|null $arrayToJoin
* **parameter** mixed $column1 the column of the current array
* **parameter** mixed $column2 the column of the array to join.
* **return value** $this
### last
It returns the last element of an array.
* **return value** $this
### map
It calls a function for every element of an array
* **parameter** callable|null $condition The function to call.
* **return value** $this
### mask
It masks the current array using another array.<br>
Masking deletes all field that are not part of our mask<br>
The mask is smart to recognize a table, so it could mask multiples values by only specifying the first row.  
**Example:**
```php
$array=['a'=>1,'b'=>2,'c'=>3,'items'=>[[a1'=>1,'a2'=>2,'a3'=3],[a1'=>1,'a2'=>2,'a3'=3]];
$mask=['a'=>1,'items'=>[[a1'=>1]]; // [[a1'=>1]] masks an entire table
$this->mask($mask); // $array=['a'=>1,'items'=>[[a1'=>1],[a1'=>1]];
```
* **param** array $arrayMask An associative array with the mask. The mask could contain any value.
* **return value** ArrayOne
### nav
Navigate inside the arrays.   
If you want to select a subcolumn, then you could indicate it separated by dot: "column.subcolumn". You
can separate up to 5 levels.
**Example:**
```php
$this->nav('col');
$this->nav(); // return to root.
$this->nav('col.subcol.subsubcol'); //  [col=>[subcol=>[subsubcol=>[1,2,3]]]] returns  [1,2,3]
```
* **parameter** string|int|null $colName   the name of the field. If null then it returns to the root.   
  You can add more leves by separating by "."
* **return value** $this
### npos
It returns the n-position of an array.
* **parameter** $index
* **return value** $this
### reduce
You can reduce (flat) an array using aggregations or a custom function.
**Example:**
```php
$this->reduce(['col1'=>'sum','col2'=>'avg','col3'=>'min','col4'=>'max']);
$this->reduce(function($row,$index,$prev) { return ['col1'=>$row['col1']+$prev['col1]];  });
```
* **parameter** array|callable $functionAggregation An associative array where the index is the column and the value
  is the function of aggregation   
  A function using the syntax: function ($row,$index,$prev) where $prev
  is the accumulator value
* **return value** $this

### removecol
It removes a column   
**Example:**
```php
$this->removeCol('col1');
$this->removeCol(['col1','col2']);
```
* **parameter** mixed $colName The name of the column or columns (array)
* **return value** $this

### removerow
It removes the row with the id **$rowId**. If the row does not exist, then it does nothing
**Example:**  
```php
$this->removeRow(20);
```
* **parameter** mixed $rowId    The id of the row to delete
* **parameter** bool  $renumber if true then it renumber the list<br>
                       ex: if 1 is deleted then $renumber=true: [0=>0,1=>1,2=>2] =>  [0=>0,1=>2]  
                       ex: if 1 is deleted then $renumber=false: [0=>0,1=>1,2=>2] =>  [0=>0,2=>2]  
* **return value** $this

### removeFirstRow
It removes the first row or rows. Numeric index could be renumbered.
**Example:**  
```php
$this->removeFirstRow(3);
```
* **parameter** int  $numberOfRows The number of rows to delete, the default is 1 (the first row)
* **parameter** bool $renumber     if true then it renumber the list  
ex: if 1 is deleted then $renumber=true: [0=>0,1=>1,'x'=>2] =>  [0=>0,1=>2]  
ex: if 1 is deleted then $renumber=false: [0=>0,1=>1,2=>2] =>  [0=>0,2=>2]  
* **return value** $this

## removeLastRow
It removes the last row or rows
**Example:**
```php
$this->removeLastRow(3);
```
* **parameter** int  $numberOfRows the number of rows to delete
* **parameter** bool $renumber     if true then it renumber the list (since we are deleting the last value then
  usually we don't need it<br>
  ex: if 1 is deleted then $renumber=true: [0=>0,1=>1,2=>2] =>  [0=>0,1=>2]<br>
  ex: if 1 is deleted then $renumber=false: [0=>0,1=>1,2=>2] =>  [0=>0,2=>2]<br>
* **return value** $this

### removeDuplicate
This function removes duplicates of a table.  
**Example:**
```php
$this->removeDuplicate('col');
```
* **parameter** mixed $colName the column to compare if the rows are duplicated.
* **return value** $this

### modCol
It adds or modify a column.
**Example:**
```php
$this->modCol('col1',function($row,$index) { return $row['col2']*$row['col3'];  });
```
* **parameter** string|int|null $colName   the name of the column. If null, then it uses the entire row
* **parameter** callable|null   $operation the operation to realize.
* **return value** $this
### sort
Sort an array   
**Example:**
```php
$this->sort('payment','desc'); // sort an array using the column paypent descending.
```
* **parameter** mixed  $column    if column is null, then it sorts the row (instead of a column of the row)
* **parameter** string $direction =\['asc','desc'][$i]  ascending or descending.
* **return value** $this

### createValidateExample
It creates a validation array using an example


### validate
Validate the current array using a comparison table   
**Example:**
```php
$this->validate([
         'id'=>'int',
    	 'price'=>'int|between;1,20'   // the price must be an integer and it must be between 1 and 20 (including them).
         'table'=>[['col1'=>'int';'col2'=>'string|notnull,,the value is required|']],   // note the double [[ ]] to indicate a table of values
         'list'=>[['int']]
    ]);
```
**Example Using a custom function:**
```php
// 1) defining the service class.
class ServiceClass {
	/**
     * @param mixed $value the value to evaluate 
     * @param mixed $compare the value to compare (optional)
     * @param ?string $msg the message to return if fails
     * @return bool
     */
    public function test($value,$compare=null,&$msg=null): bool
    {
        return true;
    }
}
// 2.1) and setting the service class using the class
ValidateOne
    ->set($array,ServiceClass:class)
    ->validate('field'=>'fn:test') // or ->validate('field'=>[['fn','test']])
    ->all();
// 2.2) or you could use an instance
$obj=new ServiceClass();
ValidateOne
    ->set($array,$obj)
    ->validate('field'=>'fn:test') // or ->validate('field'=>[['fn','test']])
    ->all();
```

* **parameter** array $comparisonTable   The comparison table, is an associative table with the conditions to compare using the next syntax: [index=>"condition|condition2..."].
  * The conditions could be express as:  **\<name of the condition>**;**\<value>**;**\<custom error message>**
    * If the **\<condition>** starts with "not", then it is negated, example: "notalpha"
    * The **\<value>** could be a simple literal (1,hello, etc.) or a list of values (separated by comma)
    * The **\<custom error message>** could contain the next variables: **%field** the id of the field, %value the current value of the field, **%comp** the value to compare, **%first** the first value to compare, **%second** the second value to compare, example "**%field** (%value) is not equals to **%comp**", **%rowid** is the id of the current row (if any)

| condition             | description                                                                                      | example work       | example fail  | expression     |
|-----------------------|--------------------------------------------------------------------------------------------------|--------------------|---------------|----------------|
| not**\<condition>**   | negates any comparison, excepting nullable and custom functions. Example: "notint"               | "hello"            | 20            | notint         |
| nullable              | the value **CAN** be a null. **If the value is null, then it ignores other validations**         | null               |               | nullable       |
| f:**\<namefunction>** | It calls a **custom function** defined in the service class. See example                         | "hello"            |               | f:test         |
| contain like          | if a text is contained in                                                                        | "helloworld"       | "hello"       | contain;world  |
| alpha                 | if the value is alphabetic                                                                       | "hello"            | "hello33"     | alpha          |
| alphanumunder         | if the value is alphanumeric or  under-case                                                      | "hello_33"         | "hello!33"    | alphanumunder  |
| alphanum              | if the value is alphanumeric                                                                     | "hello33"          | "hello!33"    | alphanum       |
| text                  | if the value is a text                                                                           | "hello"            | true          | text           |
| regexp                | if the value match a regular expression. You  can't use comma in the regular expression.         | "abc123"           | "xyz123"      | regexp;/abc*/  |
| email                 | if the value is an email                                                                         | "aaa@bbb.com"      | "aaa.bbb.com" | email          |
| url                   | if the value is an url                                                                           | https://www.nic.cl | "aaaa"        | url            |
| domain                | if the value is a domain                                                                         | www.nic.cl         | "….."         | domain         |
| minlen                | the value must have a minimum length                                                             | "hello"            | "h"           | minlen;3       |
| maxlen                | the value must have a maximum lenght                                                             | "h"                | "hello"       | maxlen;3       |
| betweenlen            | if the value has a size between                                                                  | "hello"            | "h"           | betweenlen;4,5 |
| exist                 | if the value exists                                                                              | "hi"               | null          | exist          |
| missing               | if the value not exist                                                                           | null               | "hi"          | missing        |
| req,required          | if the value is required                                                                         | "hi"               | null          | req,required   |
| eq ==                 | if the value is equals to                                                                        | 1                  | 0             | eq;1           |
| ne != <>              | if the value is not equals to                                                                    | 1                  | 0             | ne;0           |
| null                  | The value **MUST** be null. It is different to  **nullable** because **nullable** is a "**CAN**" | null               | "hello"       | null           |
| empty                 | if the value is empty                                                                            | ""                 | "hello"       | empty          |
| lt                    | if the value is less than                                                                        | 1                  | 10            | lt;5           |
| lte                   | if the value is less or equals than                                                              | 1                  | 10            | lte;5          |
| gt                    | if the value is great than                                                                       | 10                 | 1             | gt;5           |
| gte                   | if the value is great or equals than                                                             | 10                 | 1             | gte;5          |
| between               | if the value is between                                                                          | 5                  | 0             | between;4,5    |
| true                  | if the value is true or 1                                                                        | true               | false         | true           |
| false                 | if the value is false, or 0                                                                      | false              | true          | false          |
| array                 | if the value is an array                                                                         | [1,2,3]            | 1             | array          |
| int                   | if the value is an integer                                                                       | 1                  | "hello"       | int            |
| string                | if the value is a string                                                                         | "hello"            | true          | string         |
| float                 | if the value is a float                                                                          | 333.3              | "hello"       | float          |
| object                | if the value is an object                                                                        | new  stdClass()    | 1             | object         |
| in                    | the value must be in a list                                                                      | "a"                | "x"           | in;a,b,c       |

* **parameter** bool  $extraFieldError if true and the current array has more values than comparison table, then
  it returns an error.

## end operators
### all
Returns the whole array transformed. If you want the current navigation then use current()

**Example:**
```php
$this->set($array)->nav('field')->all();
```
### result

Returns the result indicated by nav(). If you want to return the whole array, then use all()

**Example:** 
```php
$this->set($array)->nav('field')->current();
```
* **return value** mixed

## other methods
Methods that does not fit in the other categories. Those methods are not stackable.

### makeValidateArrayByExample
It generates a validate-array using an example array. It could be used by validation() and filter()<br>
**Example:**
```php
$this->makeValidateArrayByExample(['1','a','f'=>3.3]); // ['int','string','f'=>'float'];
```
* **parameter** array $array
* **return value**  array

### makeRequestArrayByExample
It creates an associative array that could be used to be used by setRequest()  
**Example:**
```php
$this->makeRequestArrayByExample(['a'=1,'b'=>2]); // ['a'='post','b'=>'post'];
```
* **parameter** array  $array An associative array with some values.
* **parameter** string $type=\['get','post','request','header','cookie'][$i] The default type
* **return value** array



## versions
* 1.7 2023-06-04
  * [new] group() allows to return the grouped value. It also allows to return the values as an indexed array 
* 1.6 2023-04-10
  * [optimization] setCurrentArray() now is only used when nav() is called or when the value is returned.
* 1.5 2023-04-07
  * [new] filtercondition() now allow conditions as array. 
* 1.4 2023-04-05
  * [fix] filtercondition() fixed a warning when the value is null.
  * [new] group() now allow to stack elements
  * [new] group() now allow to specify a new column
* 1.3 2023-03-31
  * validation now allow negation ("not" prefix). 
* 1.2 
  * renamed method getValidateArrayByExample() to makeValidateArrayByExample()
  * new method makeRequestArrayByExample()
  * new method setRequest()
  * rename method setCol() to modCol(). Methods that start with "set" are used to initialize the variable.
* 1.1 2023-03-28
  * method filter() now allow a comparison array and a callable function.
  * new method getValidateArrayByExample()
  * new method removeRow()
  * new method removeFirstRow()
  * new mehtod removeLastRow()
  * new method setCsv()
  * new method setJson()
* 1.0 2023-03-26 first version


## License

Copyright Jorge Castro Castillo 2023.
Licensed under dual license: LGPL-3.0 and commercial license.

In short:
- [x] Can I use in a close source application for free? Yes if you don't modify this library.
