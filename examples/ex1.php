<?php

use eftec\ArrayOne;

include __DIR__.'/../vendor/autoload.php';

$array=[1,2,3
    ,'products'=>[
        ['name'=>'cocacola1','price'=>500,'quantity'=>200],
        ['name'=>'cocacola2','price'=>600,'quantity'=>300],
        ['name'=>'cocacola3','price'=>700,'quantity'=>400],
    ]
];

$r=ArrayOne::set($array)
    ->nav('products')
    ->setCol('subtotal',function($col,$index) { return $col['price']*$col['quantity'];})
    ->filter(function($col,$index){ return $col['price']>=600; })
    ->removeCol(['price'])
    ->col('name')
    ->last()
    ->current();

var_dump($r);
var_dump(ArrayOne::$error);

$r=ArrayOne::set($array)
    ->nav('products')
    ->reduce(function($row,$index,$previous) {
        return ['price'=>$previous['price']+$row['price'],
            'quantity'=>$previous['quantity']+$row['quantity'],'counter'=>@$previous['counter']+1];
    })
    ->all();
var_dump('--------');
var_dump($r);

$r=ArrayOne::set($array)
    ->nav('products')
    ->reduce(['price'=>'sum','quantity'=>'sum'])
    ->all();
var_dump('--------');
var_dump($r);

