<?php

$top = [
    "id" => "0001",
    "type" => "donut",
    "name" => "Cake",
    "ppu" => 0.55,
    "batters" => [
        "batter" => [
            [
                "id" => "1001",
                "type" => "Regular"
            ],
            [
                "id" => "1002",
                "type" => "Chocolate"
            ],
            [
                "id" => "1003",
                "type" => "Blueberry"
            ],
            [
                "id" => "1004",
                "type" => "Devil's Food"
            ]
        ]
    ],
    "topping" => [
        [
            "id" => "5001",
            "type" => "None"
        ],
        [
            "id" => "5002",
            "type" => "Glazed"
        ],
        [
            "id" => "5005",
            "type" => "Sugar"
        ],
        [
            "id" => "5007",
            "type" => "Powdered Sugar"
        ],
        [
            "id" => "5006",
            "type" => "Chocolate with Sprinkles"
        ],
        [
            "id" => "5003",
            "type" => "Chocolate"
        ],
        [
            "id" => "5004",
            "type" => "Maple"
        ]
    ]
];

use eftec\ArrayOne;


include __DIR__.'/../vendor/autoload.php';
include __DIR__.'/libexample.php';

echo "<h1>Test2 array</h1>";
var_dump2($top);
echo "<h1>navigation to topping</h1>";
$result= ArrayOne::set($top)->nav('topping')->getCurrent();
var_dump2($result);
echo "<h1>And reducing the values (counting)</h1>";
$result= (new ArrayOne($top))->nav('topping')->reduce(['id'=>'count'])->getCurrent();
var_dump2($result);

