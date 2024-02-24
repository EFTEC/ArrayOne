<?php

namespace eftec\test;

use eftec\ArrayOne;
use Exception;
use PHPUnit\Framework\TestCase;
use stdClass;

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
    public function testVersion(): void
    {
        $version = (new ArrayOne([]))->getVersion();
        $this->assertNotEmpty($version);
    }

    public function testAsArray(): void
    {
        $values = [['idproduct' => 1, 'unitPrice' => 200, 'quantity' => 3],
            ['idproduct' => 2, 'unitPrice' => 300, 'quantity' => 4],
            ['idproduct' => 3, 'unitPrice' => 300, 'quantity' => 5]];
        $this->assertEquals(['idproduct' => 1, 'unitPrice' => 200, 'quantity' => 3], ArrayOne::set($values)[0]);
        foreach (ArrayOne::set($values) as $v) {
            var_dump($v);
        }
    }

    public function test1(): void
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
        $arr = ArrayOne::set($invoice)
            ->nav('detail')
            ->reduce(['unitPrice' => 'sum', 'quantity' => 'sum'])
            ->all();
        $this->assertEquals(['id' => 1, 'customer' => 10, 'detail' => ['unitPrice' => 800, 'quantity' => 12]], $arr);
    }

    public function testRowToValue(): void
    {
        $array = [
            ['a' => 1, 'b' => 2, 'c' => 3],
            ['a' => 2, 'b' => 3, 'c' => 3],
            ['a' => 3, 'b' => 4, 'c' => 4],
            ['a' => 4, 'b' => 5, 'c' => 4],
            '333'
        ];
        $this->assertEquals([1, 2, 3, 4, null], ArrayOne::set($array)->rowToValue('a', true)->all());
        $this->assertEquals([1, 2, 3, 4], ArrayOne::set($array)->rowToValue('a')->all());
    }

    public function testJson(): void
    {
        $json = '{"a":3,"b":[1,2,3]}';
        $this->assertEquals(['a' => 3, 'b' => [1, 2, 3]], ArrayOne::setJson($json)->all());
    }

    public function testDuplicate(): void
    {
        $array = [
            ['a' => 1, 'b' => 2, 'c' => 3],
            ['a' => 2, 'b' => 3, 'c' => 3],
            ['a' => 3, 'b' => 4, 'c' => 4],
            ['a' => 4, 'b' => 5, 'c' => 4],
            '333'
        ];
        $r = ArrayOne::set($array)->removeDuplicate('c')->all();
        $result = [
            0 =>
                [
                    'a' => 1,
                    'b' => 2,
                    'c' => 3,
                ],
            2 =>
                [
                    'a' => 3,
                    'b' => 4,
                    'c' => 4,
                ],
            4 => '333',
        ];
        $this->assertEquals($result, $r);
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
                    3 => 'int'
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
                'table' => [['col1' => 122, 'col2' => 2], ['col1' => 133, 'col2' => 2], 3],
            ]);
        $expected = [
            'a' => 'post',
            'b' => 'post',
            'c' => 'post',
            'value' =>
                ['a' => 'post', 'b' => 'post', 'c' => 'post',],
            'table' => [0 =>
                [
                    'col1' => 'post',
                    'col2' => 'post',
                ],
            ],
        ];
        $this->assertEquals($expected, $r);
    }

    public function testLevel(): void
    {
        $array = ['one' => ['two' => ['three' => ['four' => ['five' => [1, 2, 3]]]]]];
        $this->assertEquals([1, 2, 3], ArrayOne::set($array)->nav('one.two.three.four.five')->getCurrent());
        try {
            ArrayOne::set($array)->nav('one.two.three.fourx.five')->getCurrent(); // this must throw an exception.
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
            ->getCurrent());
        try {
            ArrayOne::set($array)->nav('one.two.three.fourx.five')->getCurrent(); // this must throw an exception.
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
        $this->assertEquals($result, ArrayOne::set($invoice)->mask($mask)->getAll());
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
            'fnotin' => 'fnotin is in list',
        ];
        $service = new ServiceClass();
        $val = ArrayOne::set($array, ServiceClass::class)->validate($validate, true);
        $run = $val->all();
        $this->assertEquals(false, $val->isValid());
        $this->assertEquals([
            'fbetween' => 'fbetween is not between 1 and 3',
            0 => '0 is not an integer',
            'fnull' => 'fnull is not null',
            'fmissing' => 'fiend fmissing not found',
            'fcustomtext' => 'it is a custom text field:fcustomtext; comp:xxx; value:bbbb; first:; second:; rowid:',
            'f7' => 'f7 is not a string',
            'fservicefunction' => 'custommsg',
            'fin2' => 'fin2 is not in list',
            'fnotin' => 'fnotin is in list',
        ], $val->errorStack);
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
                    3 => null
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
                'col_stack' => 'stack(cat)',
                'col_min2' => 'min(col_min)',
                'col_max' => 'max',
                'col_sum' => 'sum',
                'col_avg' => 'avg',
                'col_count' => 'count',
                'col_first' => 'first',
                'col_last' => 'last',
                'col_sum2' => static function($cumulate, $row) {
                    return $cumulate + ($row['col_sum'] / 2);
                },
                'col_sum3' => [
                    static function($cumulate, $row) {
                        return $cumulate + ($row['col_sum'] / 2);
                    },
                    static function($cumulate, $numRow) {
                        return $cumulate / $numRow;
                    },
                ],
            ])
            ->all();
        $expected = [
            'cat1' =>
                ['col_min2' => 1, 'col_max' => 4, 'col_sum' => 5, 'col_avg' => 2.5, 'col_first' => 'john1', 'col_last' => 'doe4', 'col_count' => 2, 'col_sum2' => 2.5, 'col_sum3' => 1.25,
                    'col_stack' => [
                        ['cat' => 'cat1', 'col_min' => 1, 'col_max' => 1, 'col_sum' => 1, 'col_avg' => 1, 'col_first' => 'john1', 'col_last' => 'doe1']
                        , ['cat' => 'cat1', 'col_min' => 4, 'col_max' => 4, 'col_sum' => 4, 'col_avg' => 4, 'col_first' => 'john4', 'col_last' => 'doe4'],]],
            'cat2' =>
                ['col_min2' => 2, 'col_max' => 5, 'col_sum' => 7, 'col_avg' => 3.5, 'col_first' => 'john2', 'col_last' => 'doe5', 'col_count' => 2, 'col_sum2' => 3.5, 'col_sum3' => 1.75,
                    'col_stack' => [['cat' => 'cat2', 'col_min' => 2, 'col_max' => 2, 'col_sum' => 2, 'col_avg' => 2, 'col_first' => 'john2', 'col_last' => 'doe2'],
                        ['cat' => 'cat2', 'col_min' => 5, 'col_max' => 5, 'col_sum' => 5, 'col_avg' => 5, 'col_first' => 'john5', 'col_last' => 'doe5']]],
            'cat3' =>
                ['col_min2' => 3, 'col_max' => 3, 'col_sum' => 3, 'col_avg' => 3, 'col_first' => 'john3', 'col_last' => 'doe3', 'col_count' => 1, 'col_sum2' => 1.5, 'col_sum3' => 1.5,
                    'col_stack' => [['cat' => 'cat3', 'col_min' => 3, 'col_max' => 3, 'col_sum' => 3, 'col_avg' => 3, 'col_first' => 'john3', 'col_last' => 'doe3'],]],
        ];
        $this->assertEquals($expected, $result);
        $result = ArrayOne::set($array)
            ->group('cat', [
                'groupby' => 'group',
                'col_stack' => 'stack(cat)',
                'col_min2' => 'min(col_min)',
                'col_max' => 'max',
                'col_sum' => 'sum',
                'col_avg' => 'avg',
                'col_count' => 'count',
                'col_first' => 'first',
                'col_last' => 'last'
            ], false)
            ->all();
        $expected = [
            ['groupby' => 'cat1', 'col_min2' => 1, 'col_max' => 4, 'col_sum' => 5, 'col_avg' => 2.5, 'col_first' => 'john1', 'col_last' => 'doe4', 'col_count' => 2,
                'col_stack' => [
                    ['cat' => 'cat1', 'col_min' => 1, 'col_max' => 1, 'col_sum' => 1, 'col_avg' => 1, 'col_first' => 'john1', 'col_last' => 'doe1']
                    , ['cat' => 'cat1', 'col_min' => 4, 'col_max' => 4, 'col_sum' => 4, 'col_avg' => 4, 'col_first' => 'john4', 'col_last' => 'doe4'],]],
            ['groupby' => 'cat2', 'col_min2' => 2, 'col_max' => 5, 'col_sum' => 7, 'col_avg' => 3.5, 'col_first' => 'john2', 'col_last' => 'doe5', 'col_count' => 2,
                'col_stack' => [['cat' => 'cat2', 'col_min' => 2, 'col_max' => 2, 'col_sum' => 2, 'col_avg' => 2, 'col_first' => 'john2', 'col_last' => 'doe2'],
                    ['cat' => 'cat2', 'col_min' => 5, 'col_max' => 5, 'col_sum' => 5, 'col_avg' => 5, 'col_first' => 'john5', 'col_last' => 'doe5']]],
            ['groupby' => 'cat3', 'col_min2' => 3, 'col_max' => 3, 'col_sum' => 3, 'col_avg' => 3, 'col_first' => 'john3', 'col_last' => 'doe3', 'col_count' => 1,
                'col_stack' => [['cat' => 'cat3', 'col_min' => 3, 'col_max' => 3, 'col_sum' => 3, 'col_avg' => 3, 'col_first' => 'john3', 'col_last' => 'doe3'],]],
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
            ->group('type', ['price' => 'sum', 'quantity' => 'sum'])->indexToCol('type')->all();
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

    public function testIndexToCol(): void
    {
        $array = [1, 2, 3, 'nav' => ['c' => [1, 2, 3], 'd' => [1, 2, 3]]];
        $r = ArrayOne::set($array)->nav('nav')->indexToCol('col1')->all();
        //var_export($r);
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3, 'nav' =>
            [
                0 =>
                    [0 => 1, 1 => 2, 2 => 3, 'col1' => 'c',],
                1 =>
                    [0 => 1, 1 => 2, 2 => 3, 'col1' => 'd',],
            ],], $r);
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
        $r = ArrayOne::set($array)->nav('products')->nPos(1)->getCurrent();
        $this->assertEquals(['name' => 'cocacola2', 'price' => 600, 'quantity' => 300], $r);
        $r = ArrayOne::set($array)
            ->nav('products')
            ->nPos(1)
            ->getCurrent();
        $this->assertEquals(['name' => 'cocacola2',
            'price' => 600,
            'quantity' => 300], $r);
    }

    public function testValidationFull(): void
    {
        $array = ['contain like' => "helloworld",
            'alpha' => "hello",
            'alphanumunder' => "hello_33",
            'alphanum' => "hello33",
            'text' => "hello",
            'regexp' => "abc123",
            'email' => "aaa@bbb.com",
            'url' => "https://www.nic.cl",
            'domain' => "www.nic.cl",
            'minlen' => "hello",
            'maxlen' => "h",
            'betweenlen' => "hello",
            'exist' => "hi",
            'missing' => null,
            'req,required' => "hi",
            'eq ==' => 1,
            'ne != <>' => 1,
            'null' => null,
            'empty' => "",
            'lt' => 1,
            'lte' => 1,
            'gt' => 10,
            'gte' => 10,
            'between' => 5,
            'true' => true,
            'false' => false,
            'array' => [1, 2, 3],
            'int' => 1,
            'string' => "hello",
            'float' => 333.3,
            'object' => new stdClass(),
            'in' => "a",
        ];
        $arrayError = ['contain like' => "hello",
            'alpha' => "hello33",
            'alphanumunder' => "hello!33",
            'alphanum' => "hello!33",
            'text' => [1],
            'regexp' => "xyz123",
            'email' => "aaa.bbb.com",
            'url' => "aaaa",
            'domain' => "....",
            'minlen' => "h",
            'maxlen' => "hello",
            'betweenlen' => "h",
            'exist' => null,
            'missing' => "hi",
            'req,required' => null,
            'eq ==' => 0,
            'ne != <>' => 0,
            'null' => "hello",
            'empty' => "hello",
            'lt' => 10,
            'lte' => 10,
            'gt' => 1,
            'gte' => 1,
            'between' => 0,
            'true' => false,
            'false' => true,
            'array' => 1,
            'int' => "hello",
            'string' => true,
            'float' => "hello",
            'object' => 1,
            'in' => "x",
        ];
        $validationArray = [
            'contain like' => 'contain;world',
            'alpha' => 'alpha',
            'alphanumunder' => 'alphanumunder',
            'alphanum' => 'alphanum',
            'text' => 'text',
            'regexp' => 'regexp;/abc*/',
            'email' => 'email',
            'url' => 'url',
            'domain' => 'domain',
            'minlen' => 'minlen;3',
            'maxlen' => 'maxlen;3',
            'betweenlen' => 'betweenlen;4,5',
            'exist' => 'exist',
            'missing' => 'missing',
            'req,required' => 'req',
            'eq ==' => 'eq;1',
            'ne != <>' => 'ne;0',
            'null' => 'null',
            'empty' => 'empty',
            'lt' => 'lt;5',
            'lte' => 'lte;5',
            'gt' => 'gt;5',
            'gte' => 'gte;5',
            'between' => 'between;4,5',
            'true' => 'true',
            'false' => 'false',
            'array' => 'array',
            'int' => 'int',
            'string' => 'string',
            'float' => 'float',
            'object' => 'object',
            'in' => 'in;a,b,c',
        ];
        $validationArrayNeg = [
            'contain like' => 'notcontain;world',
            'alpha' => 'notalpha',
            'alphanumunder' => 'notalphanumunder',
            'alphanum' => 'notalphanum',
            'text' => 'nottext',
            'regexp' => 'notregexp;/abc*/',
            'email' => 'notemail',
            'url' => 'noturl',
            'domain' => 'notdomain',
            'minlen' => 'notminlen;3',
            'maxlen' => 'notmaxlen;3',
            'betweenlen' => 'notbetweenlen;4,5',
            'exist' => 'notexist',
            'missing' => 'notmissing',
            'req,required' => 'notreq',
            'eq ==' => 'noteq;1',
            'ne != <>' => 'notne;0',
            'null' => 'notnull',
            'empty' => 'notempty',
            'lt' => 'notlt;5',
            'lte' => 'notlte;5',
            'gt' => 'notgt;5',
            'gte' => 'notgte;5',
            'between' => 'notbetween;4,5',
            'true' => 'nottrue',
            'false' => 'notfalse',
            'array' => 'notarray',
            'int' => 'notint',
            'string' => 'notstring',
            'float' => 'notfloat',
            'object' => 'notobject',
            'in' => 'notin;a,b,c',
        ];
        $expectedOK = [
            'contain like' => NULL,
            'alpha' => NULL,
            'alphanumunder' => NULL,
            'alphanum' => NULL,
            'text' => NULL,
            'regexp' => NULL,
            'email' => NULL,
            'url' => NULL,
            'domain' => NULL,
            'minlen' => NULL,
            'maxlen' => NULL,
            'betweenlen' => NULL,
            'exist' => NULL,
            'missing' => NULL,
            'req,required' => NULL,
            'eq ==' => NULL,
            'ne != <>' => NULL,
            'null' => NULL,
            'empty' => NULL,
            'lt' => NULL,
            'lte' => NULL,
            'gt' => NULL,
            'gte' => NULL,
            'between' => NULL,
            'true' => NULL,
            'false' => NULL,
            'array' => NULL,
            'int' => NULL,
            'string' => NULL,
            'float' => NULL,
            'object' => NULL,
            'in' => NULL,
        ];
        $expectedERROR = [
            'contain like' => 'contain like contains world',
            'alpha' => 'alpha is not alphabetic',
            'alphanumunder' => 'alphanumunder is not alphanumeric with underscore',
            'alphanum' => 'alphanum is not alphanumeric',
            'text' => 'text has characters not allowed',
            'regexp' => 'regexp does not match exp',
            'email' => 'email is not an email',
            'url' => 'url is not an url',
            'domain' => 'domain is not a domain',
            'minlen' => 'minlen size is less than 3',
            'maxlen' => 'maxlen size is great than 3',
            'betweenlen' => 'betweenlen size is not between 4 and 5',
            'exist' => 'exist does not exist',
            'missing' => 'missing exists',
            'req,required' => 'req,required is required',
            'eq ==' => 'eq == is not equals than 1',
            'ne != <>' => 'ne != <> is equals than 0',
            'null' => 'null is not null',
            'empty' => 'empty is not empty',
            'lt' => 'lt is great or equal than 5',
            'lte' => 'lte is great than 5',
            'gt' => 'gt is less or equal than 5',
            'gte' => 'gte is less than 5',
            'between' => 'between is not between 4 and 5',
            'true' => 'true is not true',
            'false' => 'false is not false',
            'array' => 'array is not an array',
            'int' => 'int is not an integer',
            'string' => 'string is not a string',
            'float' => 'float is not a float',
            'object' => 'object is not an object',
            'in' => 'in is not in list',
        ];
        $expectedNEG = [
            'contain like' => 'contain like does not contains world',
            'alpha' => 'alpha is alphabetic',
            'alphanumunder' => 'alphanumunder is alphanumeric with underscore',
            'alphanum' => 'alphanum is alphanumeric',
            'text' => 'text hasn\'t characters not allowed',
            'regexp' => 'regexp does match exp',
            'email' => 'email is an email',
            'url' => 'url is an url',
            'domain' => 'domain is a domain',
            'minlen' => 'minlen size is not less than 3',
            'maxlen' => 'maxlen size is not great than 3',
            'betweenlen' => 'betweenlen size is between 4 and 5',
            'exist' => 'exist does exist',
            'missing' => 'missing does not exist',
            'req,required' => 'req,required is not required',
            'eq ==' => 'eq == is equals than 1',
            'ne != <>' => 'ne != <> is not equals than 0',
            'null' => 'null is null',
            'empty' => 'empty is empty',
            'lt' => 'lt is not great or equal than 5',
            'lte' => 'lte is not great than 5',
            'gt' => 'gt is not less or equal than 5',
            'gte' => 'gte is not less than 5',
            'between' => 'between is not between 4 and 5',
            'true' => 'true is  true',
            'false' => 'false is false',
            'array' => 'array is an array',
            'int' => 'int is an integer',
            'string' => 'string is a string',
            'float' => 'float is a float',
            'object' => 'object is an object',
            'in' => 'in is in list',
        ];
        $final = ArrayOne::set($array)->validate($validationArray)->all();
        $this->assertEquals($expectedOK, $final);
        $final = ArrayOne::set($arrayError)->validate($validationArray)->all();
        $this->assertEquals($expectedERROR, $final);
        $final = ArrayOne::set($array)->validate($validationArrayNeg)->all();
        $this->assertEquals($expectedNEG, $final);
    }

    public function testfilter(): void
    {
        $array = [['id' => 1, 'name' => 'chile'], ['id' => 2, 'name' => 'argentina'], ['id' => 3, 'name' => 'peru']];
        $r = ArrayOne::set($array)->filter(function($row, $id) {
            return $row['id'] === 2;
        }, true)->getCurrent();
        $this->assertEquals(['id' => 2, 'name' => 'argentina'], $r);
        $r = ArrayOne::set($array)->filter(function($row, $id) {
            return $row['id'] === 2;
        }, false)->getCurrent();
        $this->assertEquals([1 => ['id' => 2, 'name' => 'argentina']], $r);
    }

    public function testfilter2(): void
    {
        $array = [['id' => 1, 'name' => 'chile'], ['id' => 2, 'name' => 'argentina'], ['id' => 3, 'name' => 'peru']];
        $r = ArrayOne::set($array)->filter(['id' => 'eq;2'], true)->getCurrent();
        $this->assertEquals(['id' => 2, 'name' => 'argentina'], $r);
        $r = ArrayOne::set($array)->filter(['id' => 'gte;2'])->getCurrent();
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
