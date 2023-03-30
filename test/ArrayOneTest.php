<?php

namespace eftec\tests;

use eftec\ArrayOne;
use Exception;
use PHPUnit\Framework\TestCase;

class ServiceClass
{
    /**
     * @param mixed   $value   the value to evaluate
     * @param mixed   $compare the value to compare (optional)
     * @param ?string $msg     the message to return if the comparison is false
     * @return bool
     */
    public function test($value, $compare = null, &$msg = null): bool
    {
        if ($value === $compare) {
            $msg = null;
            return true;
        }
        $msg = "error genenerated by test"; // it could be overridden by a custom message.
        return false;
    }
}

class ArrayOneTest extends TestCase
{
    public function test1(): void
    {
        $version = (new ArrayOne([]))->getVersion();
        $this->assertNotEmpty($version);
        $invoice = [
            'id' => 1,
            'customer' => 10,
            'detail' => [
                ['idproduct' => 1, 'unitPrice' => 200, 'quantity' => 3],
                ['idproduct' => 2, 'unitPrice' => 300, 'quantity' => 4],
                ['idproduct' => 3, 'unitPrice' => 300, 'quantity' => 5],
                5,
            ]
        ];
        $arr = ArrayOne::set($invoice)
            ->nav('detail')
            ->reduce(['unitPrice' => 'sum', 'quantity' => 'sum'])
            ->all();
        $this->assertEquals(['id' => 1, 'customer' => 10, 'detail' => ['unitPrice' => 800, 'quantity' => 12]], $arr);
    }

    public function testJson(): void
    {
        $json = '{"a":3,"b":[1,2,3]}';
        $this->assertEquals(['a' => 3, 'b' => [1, 2, 3]], ArrayOne::setJson($json)->all());
    }
    public function testDuplicate(): void
    {
        $array=[
            ['a'=>1,'b'=>2,'c'=>3],
            ['a'=>2,'b'=>3,'c'=>3],
            ['a'=>3,'b'=>4,'c'=>4],
            ['a'=>4,'b'=>5,'c'=>4],
            '333'
        ];
        $r=ArrayOne::set($array)->removeDuplicate('c')->all();
        $result=array (
            0 =>
                array (
                    'a' => 1,
                    'b' => 2,
                    'c' => 3,
                ),
            2 =>
                array (
                    'a' => 3,
                    'b' => 4,
                    'c' => 4,
                ),
            4 => '333',
        );
        $this->assertEquals($result,$r);

    }

    public function testValidateExample(): void
    {
        $invoice = [
            'id' => 1,
            'customer' => 'abc',
            'null' => null,
            'table' => [
                ['idproduct' => 1, 'unitPrice' => 200, 'quantity' => 'b'],
                ['idproduct' => 2, 'unitPrice' => 300, 'quantity' => 'aaaaaaaaaaaaa'],
                ['idproduct' => 3, 'unitPrice' => 300, 'quantity' => 'c'],
                2,
            ],
            'nottable' => [
                ['idproduct' => 1, 'unitPrice' => 200, 'quantity' => 3],
                ['idproductx' => 2, 'unitPrice' => 300, 'quantity' => 4],
                ['idproducty' => 3, 'unitPrice' => 300, 'quantity' => 5],
                3,
            ],
        ];
        $r = ArrayOne::makeValidateArrayByExample($invoice);
        $expected = [
            'id' => 'int',
            'customer' => 'string',
            'null' => 'nullable|string',
            'table' =>
                [
                    0 =>
                        ['idproduct' => 'int', 'unitPrice' => 'int', 'quantity' => 'string',],
                ],
            'nottable' =>
                [
                    0 =>
                        ['idproduct' => 'int', 'unitPrice' => 'int', 'quantity' => 'int',],
                    1 =>
                        ['idproductx' => 'int', 'unitPrice' => 'int', 'quantity' => 'int',],
                    2 =>
                        ['idproducty' => 'int', 'unitPrice' => 'int', 'quantity' => 'int',],
                    3=>'int'
                ],
        ];
        $this->assertEquals($expected, $r);
    }

    public function testCsv(): void
    {
        $txt = "a,b,c,d\r\n1,\"aaa\",3,4\r\n5,\"bbb\",7,8";
        $this->assertEquals([
            ['a' => '1', 'b' => 'aaa', 'c' => '3', 'd' => '4'],
            ['a' => '5', 'b' => 'bbb', 'c' => '7', 'd' => '8']
        ], ArrayOne::setCsv($txt)->all());
        $txt = "1,\"aaa\",3,4\r\n5,\"bbb\",7,8";
        $this->assertEquals([
            ['a' => '1', 'b' => 'aaa', 'c' => '3', 'd' => '4'],
            ['a' => '5', 'b' => 'bbb', 'c' => '7', 'd' => '8']
        ], ArrayOne::setCsvHeadLess($txt, ['a', 'b', 'c', 'd'])->all());
    }

    public function testRemoveRow(): void
    {
        $array = ['a', 'b', 't' => 'c', 'd' => ['d1', 'd2'], 2 => 'e'];
        $this->assertEquals(['a', 'b', 'd' => ['d1', 'd2'], 2 => 'e'], ArrayOne::set($array)->removeRow('t')->all());
        $this->assertEquals([0 => 'c', 1 => ['d1', 'd2'], 2 => 'e'], ArrayOne::set($array)->removeFirstRow(2, true)->all());
        $this->assertEquals(['t' => 'c', 'd' => ['d1', 'd2'], 0 => 'e'], ArrayOne::set($array)->removeFirstRow(2)->all());
        $this->assertEquals(['a', 'b', 't' => 'c'], ArrayOne::set($array)->removeLastRow(2)->all());
    }

    public function testRequest(): void
    {
        $_COOKIE['COOKIE1'] = 'my magic cookie';
        $_SERVER['HTTP_USER_AGENT'] = 'TEST';
        $_GET = ['id' => 'it is id get'];
        $_POST = ['id2' => 'it is id2', 'id3' => 'it is id3', 'obj.obj1' => 'it is obj.obj1', 'obj.obj2' => 'it is obj.obj2',
            'table1.col1' => [1, 2, 3],
            'table1.col2' => [3, 4, 5],
        ];
        $r = ArrayOne::setRequest([
            'id' => 'get',
            'idmissing' => 'get;missing value',
            'id2' => 'post',
            'id3' => 'request',
            'USER_AGENT' => 'header',
            'COOKIE1' => 'cookie',
            'body' => 'body',
            'obj' => ['obj1' => 'post', 'obj2' => 'post'],
            'table1' => [['col1' => 'post', 'col2' => 'post']],
        ])->all();
        $expected = [
            'id' => 'it is id get',
            'idmissing' => 'missing value',
            'id2' => 'it is id2',
            'id3' => 'it is id3',
            'USER_AGENT' => 'TEST',
            'COOKIE1' => 'my magic cookie',
            'body' => NULL,
            'obj' =>
                [
                    'obj1' => 'it is obj.obj1',
                    'obj2' => 'it is obj.obj2',
                ],
            'table1' =>
                [
                    0 =>
                        [
                            'col1' => 1,
                            'col2' => 3,
                        ],
                    1 =>
                        [
                            'col1' => 2,
                            'col2' => 4,
                        ],
                    2 =>
                        [
                            'col1' => 3,
                            'col2' => 5,
                        ],
                ],
        ];
        $this->assertEquals($expected, $r);
    }

    public function testmakeRequestArrayByExample(): void
    {
        $r = ArrayOne::makeRequestArrayByExample(
            ['a' => 444,
                'b' => 'xxx',
                'c' => '22222222',
                'value' => ['a' => 111, 'b' => 'c', 'c' => 'ddd'],
                'table' => [['col1' => 122, 'col2' => 2], ['col1' => 133, 'col2' => 2],3],
            ]);
        $expected = [
            'a' => 'POST',
            'b' => 'POST',
            'c' => 'POST',
            'value' =>
                ['a' => 'POST', 'b' =>'POST', 'c' => 'POST',],
            'table' => [0 =>
                        [
                            'col1' => 'POST',
                            'col2' => 'POST',
                        ],
                ],
        ];
        $this->assertEquals($expected, $r);
    }

    public function testLevel(): void
    {
        $array = ['one' => ['two' => ['three' => ['four' => ['five' => [1, 2, 3]]]]]];
        $this->assertEquals([1, 2, 3], ArrayOne::set($array)->nav('one.two.three.four.five')->current());
        try {
            ArrayOne::set($array)->nav('one.two.three.fourx.five')->current(); // this must throw an exception.
            $this->assertEquals(false, true);
        } catch (Exception $ex) {
            $this->assertEquals(true, true);
        }
    }

    public function testLevel2(): void
    {
        $array = ['one' => ['two' => ['three' => ['four' => ['five' => [1, 2, 3]]]]]];
        $this->assertEquals([10, 20, 30], ArrayOne::set($array)->nav('one.two.three.four.five')
            ->map(function($row) {
                return $row * 10;
            })
            ->current());
        try {
            ArrayOne::set($array)->nav('one.two.three.fourx.five')->current(); // this must throw an exception.
            $this->assertEquals(false, true);
        } catch (Exception $ex) {
            $this->assertEquals(true, true);
        }
    }

    public function testMask(): void
    {
        $invoice = [
            'id' => 1,
            'customer' => 10,
            'detail' => [
                ['idproduct' => 1, 'unitPrice' => 200, 'quantity' => 3],
                ['idproduct' => 2, 'unitPrice' => 300, 'quantity' => 4],
                ['idproduct' => 3, 'unitPrice' => 300, 'quantity' => 5],
                5,
            ]
        ];
        $mask = [
            'id' => 1,
            'detail' => [['unitPrice' => 200, 'quantity' => 3]]
        ];
        $result = [
            'id' => 1,
            'detail' => [
                ['unitPrice' => 200, 'quantity' => 3],
                ['unitPrice' => 300, 'quantity' => 4],
                ['unitPrice' => 300, 'quantity' => 5]
            ]
        ];
        $this->assertEquals($result, ArrayOne::set($invoice)->mask($mask)->all());
    }

    public function testValidate2(): void
    {
        $array = [
            'fint' => 1,
            'fbetween' => 2333,
            'farray' => [1, 2, 'x'],
            'farraysize' => [1, 2, 'x'],
            'fnull' => 'aaaaa',
            'fmissing' => 'bbbb',
            'fcustomtext' => 'bbbb',
            'f6' => null,
            'f7' => null,
            'fservicefunction' => 'abc',
            'fin' => 'apple',
            'fin2' => 'applex',
            'fnotin' => 'apple',];
        $validate = [
            'fint' => 'int',
            'fbetween' => 'int|between;1,3',
            'farray' => [['int']],
            'farraysize' => 'between;1,3',
            'fnull' => 'null',
            'fcustomtext' => 'int;xxx;it is a custom text field:%field; comp:%comp; value:%value; first:%first; second:%second; rowid:%rowid',
            'f6' => 'nullable|string',
            'f7' => 'string',
            'fservicefunction' => 'f:test;value;custommsg',
            'fin' => 'in;apple,pear,orange',
            'fin2' => 'in;apple,pear,orange',
            'fnotin' => 'notin;apple,pear,orange',]; // @see \eftec\tests\ServiceClass::test
        $expected = [
            'fint' => NULL,
            'fbetween' => 'fbetween is not between 1 and 3',
            'farray' =>
                [
                    0 => NULL,
                    1 => NULL,
                    2 => '0 is not an integer',
                ],
            'farraysize' => NULL,
            'fnull' => 'fnull is not null',
            'fmissing' => 'fiend fmissing not found',
            'fcustomtext' => 'it is a custom text field:fcustomtext; comp:xxx; value:bbbb; first:; second:; rowid:',
            'f6' => NULL,
            'f7' => 'f7 is not a string',
            'fservicefunction' => 'custommsg',
            'fin' => NULL,
            'fin2' => 'fin2 is not in list',
            'fnotin' => 'fnotin should not be in list',
        ];
        $service = new ServiceClass();
        $run = ArrayOne::set($array, ServiceClass::class)->validate($validate, true)->all();
        $this->assertEquals($expected, $run);
        $run = ArrayOne::set($array, $service)->validate($validate, true)->all();
        $this->assertEquals($expected, $run);
    }

    public function testValidate(): void
    {
        $array = [1, 2, 3
            , 'customer' => ['name' => 'john', 'age' => 33]
            , 'products' => [
                ['name' => 'cocacola1', 'price' => 500, 'quantity' => 200],
                ['name' => 'cocacola2', 'price' => 600, 'quantity' => 300],
                ['name' => 'cocacola3', 'price' => 700, 'quantity' => 400],
                5
            ],
            'types' => ['type1', 'type2', 'type3']
        ];
        $result = ArrayOne::set($array)->validate(
            ['int',
                'int',
                'int',
                'customer' => ['name' => 'string', 'age' => 'int'],
                'products' => [['name' => 'int',
                    'price' => 'int',
                    'quantity' => 'int']],
                'types' => [['string']]
            ])->all();
        $expected = [
            0 => NULL,
            1 => NULL,
            2 => NULL,
            'customer' =>
                [
                    'name' => NULL,
                    'age' => NULL,
                ],
            'products' =>
                [
                    0 =>
                        [
                            'name' => 'name is not an integer',
                            'price' => NULL,
                            'quantity' => NULL,
                        ],
                    1 =>
                        [
                            'name' => 'name is not an integer',
                            'price' => NULL,
                            'quantity' => NULL,
                        ],
                    2 =>
                        [
                            'name' => 'name is not an integer',
                            'price' => NULL,
                            'quantity' => NULL,
                        ],
                    3=>null
                ],
            'types' =>
                [
                    0 => NULL,
                    1 => NULL,
                    2 => NULL,
                ],
        ];
        $this->assertEquals($expected, $result);
    }

    public function testCol(): void
    {
        $array = [1, 2, 3
            , 'products' => [
                ['name' => 'cocacola1', 'price' => 500, 'quantity' => 200],
                ['name' => 'cocacola2', 'price' => 600, 'quantity' => 300],
                ['name' => 'cocacola3', 'price' => 700, 'quantity' => 400],
            ]
        ];
        $r = ArrayOne::set($array)
            ->nav('products')
            ->modCol('name', function($col, $index) {
                return strtoupper($col['name']);
            })
            ->all();
        $this->assertEquals([1, 2, 3
            , 'products' => [
                ['name' => 'COCACOLA1', 'price' => 500, 'quantity' => 200],
                ['name' => 'COCACOLA2', 'price' => 600, 'quantity' => 300],
                ['name' => 'COCACOLA3', 'price' => 700, 'quantity' => 400],
            ]
        ], $r);
    }

    public function testSort(): void
    {
        $array = [1, 2, 3
            , 'products' => [
                ['name' => 'cocacola1', 'price' => 500, 'quantity' => 200, 'type' => 'type1'],
                ['name' => 'cocacola2', 'price' => 600, 'quantity' => 300, 'type' => 'type2'],
                ['name' => 'cocacola3', 'price' => 700, 'quantity' => 400, 'type' => 'type1'],
            ]
        ];
        $r = ArrayOne::set($array)->nav('products')->sort('name')->all();
        $this->assertEquals([1, 2, 3, 'products' => [['name' => 'cocacola1', 'price' => 500, 'quantity' => 200, 'type' => 'type1'],
            ['name' => 'cocacola2', 'price' => 600, 'quantity' => 300, 'type' => 'type2'],
            ['name' => 'cocacola3', 'price' => 700, 'quantity' => 400, 'type' => 'type1']]], $r);
        $r = ArrayOne::set($array)->nav('products')->sort('name', 'desc')->all();
        $this->assertEquals([1, 2, 3, 'products' => [
            ['name' => 'cocacola3', 'price' => 700, 'quantity' => 400, 'type' => 'type1'],
            ['name' => 'cocacola2', 'price' => 600, 'quantity' => 300, 'type' => 'type2'],
            ['name' => 'cocacola1', 'price' => 500, 'quantity' => 200, 'type' => 'type1']
        ]], $r);
        $array = ['chile', 'argentina', 'peru'];
        $r = ArrayOne::set($array)->sort(null)->all();
        $this->assertEquals(['argentina', 'chile', 'peru'], $r);
        $r = ArrayOne::set($array)->sort(null, 'desc')->all();
        $this->assertEquals(['peru', 'chile', 'argentina'], $r);
    }

    public function testGroup2(): void
    {
        $array = [
            ['cat' => 'cat1', 'col_min' => 1, 'col_max' => 1, 'col_sum' => 1, 'col_avg' => 1, 'col_first' => 'john1', 'col_last' => 'doe1'],
            ['cat' => 'cat2', 'col_min' => 2, 'col_max' => 2, 'col_sum' => 2, 'col_avg' => 2, 'col_first' => 'john2', 'col_last' => 'doe2'],
            ['cat' => 'cat3', 'col_min' => 3, 'col_max' => 3, 'col_sum' => 3, 'col_avg' => 3, 'col_first' => 'john3', 'col_last' => 'doe3'],
            ['cat' => 'cat1', 'col_min' => 4, 'col_max' => 4, 'col_sum' => 4, 'col_avg' => 4, 'col_first' => 'john4', 'col_last' => 'doe4'],
            ['cat' => 'cat2', 'col_min' => 5, 'col_max' => 5, 'col_sum' => 5, 'col_avg' => 5, 'col_first' => 'john5', 'col_last' => 'doe5']
        ];
        $result = ArrayOne::set($array)
            ->group('cat', [
                'col_min' => 'min',
                'col_max' => 'max',
                'col_sum' => 'sum',
                'col_avg' => 'avg',
                'col_count' => 'count',
                'col_first' => 'first',
                'col_last' => 'last',
            ])
            ->all();
        $expected = [
            'cat1' =>
                ['col_min' => 1, 'col_max' => 4, 'col_sum' => 5, 'col_avg' => 2.5, 'col_first' => 'john1', 'col_last' => 'doe4', 'col_count' => 2,],
            'cat2' =>
                ['col_min' => 2, 'col_max' => 5, 'col_sum' => 7, 'col_avg' => 3.5, 'col_first' => 'john2', 'col_last' => 'doe5', 'col_count' => 2,],
            'cat3' =>
                ['col_min' => 3, 'col_max' => 3, 'col_sum' => 3, 'col_avg' => 3, 'col_first' => 'john3', 'col_last' => 'doe3', 'col_count' => 1,],
        ];
        $this->assertEquals($expected, $result);
    }

    public function testGroup(): void
    {
        $array = [1, 2, 3
            , 'products' => [
                ['name' => 'cocacola1', 'price' => 500, 'quantity' => 200, 'type' => 'type1'],
                ['name' => 'cocacola2', 'price' => 600, 'quantity' => 300, 'type' => 'type2'],
                ['name' => 'cocacola3', 'price' => 700, 'quantity' => 400, 'type' => 'type1'],
            ]
        ];
        $r = ArrayOne::set($array)
            ->nav('products')
            ->group('type', ['price' => 'sum', 'quantity' => 'sum'])->all();
        $this->assertEquals([1, 2, 3,
            'products' => [
                'type1' => ['price' => 1200, 'quantity' => 600]
                , 'type2' => ['price' => 600, 'quantity' => 300]
            ]], $r);
        $r = ArrayOne::set($array)
            ->nav('products')
            ->group('type', ['price' => 'sum', 'quantity' => 'sum'])->indexToColumn('type')->all();
        $this->assertEquals([1, 2, 3,
            'products' => [
                ['type' => 'type1', 'price' => 1200, 'quantity' => 600]
                , ['type' => 'type2', 'price' => 600, 'quantity' => 300]
            ]], $r);
        $this->assertEquals([1, 2, 3,
            'products' => [
                'type1' => ['price' => 1200, 'quantity' => 600]
                , 'type2' => ['price' => 600, 'quantity' => 300]
            ]], ArrayOne::set($r)->nav('products')->columnToIndex('type')->all());
    }

    public function testFlat(): void
    {
        $array = [1, 2, 3
            , 'products' => [
                ['name' => 'cocacola1', 'price' => 500, 'quantity' => 200]
            ]
        ];
        $arrayFlat = [1, 2, 3
            , 'products' => ['name' => 'cocacola1', 'price' => 500, 'quantity' => 200]
        ];
        $this->assertEquals($arrayFlat, ArrayOne::set($array)->nav('products')->flat()->all());
    }

    public function testMerger(): void
    {
        $array = [1, 2, 3
            , 'products' => [
                ['name' => 'cocacola1', 'price' => 500, 'quantity' => 200, 'type' => 'type1'],
                ['name' => 'cocacola2', 'price' => 600, 'quantity' => 300, 'type' => 'type2'],
                ['name' => 'cocacola3', 'price' => 700, 'quantity' => 400, 'type' => 'type1'],
            ]
        ];
        $arrayExpected = [1, 2, 3
            , 'products' => [
                ['name' => 'cocacola1', 'price' => 500, 'quantity' => 200, 'type' => 'type1', 'desc' => 'it is the type #1'],
                ['name' => 'cocacola2', 'price' => 600, 'quantity' => 300, 'type' => 'type2', 'desc' => 'it is the type #2'],
                ['name' => 'cocacola3', 'price' => 700, 'quantity' => 400, 'type' => 'type1', 'desc' => 'it is the type #1'],
            ]
        ];
        $types = [['name' => 'type1', 'desc' => 'it is the type #1'], ['name' => 'type2', 'desc' => 'it is the type #2']];
        $this->assertEquals($arrayExpected, ArrayOne::set($array)->nav('products')->join($types, 'type', 'name')->all());
    }

    public function test2(): void
    {
        $array = [1, 2, 3
            , 'products' => [
                ['name' => 'cocacola1', 'price' => 500, 'quantity' => 200],
                ['name' => 'cocacola2', 'price' => 600, 'quantity' => 300],
                ['name' => 'cocacola3', 'price' => 700, 'quantity' => 400],
            ]
        ];
        $r = ArrayOne::set($array)
            ->nav('products')
            ->modCol('subtotal', function($col, $index) {
                return $col['price'] * $col['quantity'];
            })
            ->filter(function($col, $index) {
                return $col['price'] >= 600;
            })
            ->removeCol(['price'])
            ->col('name')
            ->last()
            ->all();
        $this->assertEquals([1, 2, 3
            , 'products' => 'cocacola3'
        ], $r);
        $r = ArrayOne::set($array)->nav('products')->nPos(1)->current();
        $this->assertEquals(['name' => 'cocacola2', 'price' => 600, 'quantity' => 300], $r);
        $r = ArrayOne::set($array)
            ->nav('products')
            ->nPos(1)
            ->current();
        $this->assertEquals(['name' => 'cocacola2',
            'price' => 600,
            'quantity' => 300], $r);
    }

    public function testfilter(): void
    {
        $array = [['id' => 1, 'name' => 'chile'], ['id' => 2, 'name' => 'argentina'], ['id' => 3, 'name' => 'peru']];
        $r = ArrayOne::set($array)->filter(function($row, $id) {
            return $row['id'] === 2;
        }, true)->current();
        $this->assertEquals(['id' => 2, 'name' => 'argentina'], $r);
        $r = ArrayOne::set($array)->filter(function($row, $id) {
            return $row['id'] === 2;
        }, false)->current();
        $this->assertEquals([1 => ['id' => 2, 'name' => 'argentina']], $r);
    }

    public function testfilter2(): void
    {
        $array = [['id' => 1, 'name' => 'chile'], ['id' => 2, 'name' => 'argentina'], ['id' => 3, 'name' => 'peru']];
        $r = ArrayOne::set($array)->filter(['id' => 'eq;2'], true)->current();
        $this->assertEquals(['id' => 2, 'name' => 'argentina'], $r);
        $r = ArrayOne::set($array)->filter(['id' => 'gte;2'])->current();
        $this->assertEquals([1 => ['id' => 2, 'name' => 'argentina'], ['id' => 3, 'name' => 'peru']], $r);
    }

    public function testRow(): void
    {
        $array = [1, 2, 3
            , 'products' => [
                ['name' => 'cocacola1', 'price' => 500, 'quantity' => 200],
                ['name' => 'cocacola2', 'price' => 600, 'quantity' => 300],
                ['name' => 'cocacola3', 'price' => 700, 'quantity' => 400],
            ]
        ];
        $this->assertEquals([1, 2, 3, 'products' => [
            'name' => 'cocacola1',
            'price' => 500,
            'quantity' => 200]],
            ArrayOne::set($array)->nav('products')->first()->all());
        $this->assertEquals([1, 2, 3, 'products' => [
            'name' => 'cocacola3',
            'price' => 700,
            'quantity' => 400]],
            ArrayOne::set($array)->nav('products')->last()->all());
    }

    public function testReduce(): void
    {
        $array = [1, 2, 3
            , 'products' => [
                ['name' => 'cocacola1', 'price' => 500, 'quantity' => 200],
                ['name' => 'cocacola2', 'price' => 600, 'quantity' => 300],
                ['name' => 'cocacola3', 'price' => 700, 'quantity' => 400],
            ]
        ];
        $r = ArrayOne::set($array)
            ->nav('products')
            ->reduce(function($row, $index, $previous) {
                return ['price' => $previous['price'] + $row['price'],
                    'quantity' => $previous['quantity'] + $row['quantity'], 'counter' => @$previous['counter'] + 1];
            })
            ->all();
        $this->assertEquals([
            0 => 1,
            1 => 2,
            2 => 3,
            'products' =>
                [
                    'price' => 1800,
                    'quantity' => 900,
                    'counter' => 2,
                ],
        ], $r);
        $r = ArrayOne::set($array)
            ->nav('products')
            ->reduce(['price' => 'sum', 'quantity' => 'sum'])
            ->all();
        $this->assertEquals([
            0 => 1,
            1 => 2,
            2 => 3,
            'products' =>
                [
                    'price' => 1800,
                    'quantity' => 900,
                ],
        ], $r);
    }
}
