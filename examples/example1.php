<?php

use eftec\ArrayOne;

include __DIR__.'/../vendor/autoload.php';
include __DIR__.'/libexample.php';

$array=[1,2,3
    ,'products'=>[
        ['name'=>'cocacola1','price'=>500,'quantity'=>200],
        ['name'=>'cocacola2','price'=>600,'quantity'=>300],
        ['name'=>'cocacola3','price'=>700,'quantity'=>400],
    ]
];

$r=ArrayOne::set($array)
    ->nav('products')
    ->modCol('subtotal',function($col, $index) { return $col['price']*$col['quantity'];})
    ->filter(function($col,$index){ return $col['price']>=600; })
    ->removeCol(['price'])
    ->col('name')
    ->last()
    ->getCurrent();
echo "<h1>Example1 array:</h1>";
var_dump2($array);
echo "<h1>nav to product, creating a new column, filtering, removing column price, returning a column and getting the last value</h1>";
var_dump($r);
echo "<h1>Showing errors</h1>";
var_dump2(ArrayOne::$error);
echo "<h1>reducing products</h1>";
$r=ArrayOne::set($array)
    ->nav('products')
    ->reduce(function($row,$index,$previous) {
        return ['price'=>$previous['price']+$row['price'],
            'quantity'=>$previous['quantity']+$row['quantity'],'counter'=>@$previous['counter']+1];
    })
    ->all();

var_dump2($r);
echo "<h1>reducing products</h1>";
$r=ArrayOne::set($array)
    ->nav('products')
    ->reduce(['price'=>'sum','quantity'=>'sum'])
    ->all();

var_dump2($r);

