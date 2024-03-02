<?php /**
 * @noinspection UnknownInspectionInspection
 */

use eftec\ArrayOne;

include __DIR__.'/../vendor/autoload.php';
include __DIR__.'/libexample.php';

$values=[
    ["id"=>1,"product"=>"apple","type"=>"fruit",'price'=>100],
    ["id"=>2,"product"=>"pear","type"=>"fruit",'price'=>200],
    ["id"=>3,"product"=>"orange","type"=>"fruit",'price'=>300],
    ["id"=>4,"product"=>"cocacola","type"=>"drink",'price'=>100],
    ["id"=>5,"product"=>"fanta","type"=>"drink",'price'=>200],
];
echo "<h1>Returns true if it is an indexed table</h1>";
var_dump2(ArrayOne::isIndexTableArray($values));

echo "<h1>returning all the fruits with price greater or equals than 200</h1>";
var_dump2(ArrayOne::set($values)->filter([['type'=>'eq;fruit'],['price'=>'ge;200']])->getCurrent());
echo "<h1>returning all the fruits with price greater or equals than 200 using a function<h1></h1>";
var_dump2(ArrayOne::set($values)->filter(static function($row) {
    return $row['type']==='fruit' && $row['price']>=200;
    })->getCurrent());
echo "<h1>returning all the fruits with price greater or equals than 200 using a function (lambda)<h1></h1>";
var_dump2(ArrayOne::set($values)->filter(fn($row) => $row['type']==='fruit' && $row['price']>=200)->getCurrent());

echo "<h1>returning all the fruits and drink with price greater or equals than 200</h1>";
var_dump2(ArrayOne::set($values)->filter([['type'=>'in;fruit,drink'],['price'=>'ge;200']])->getCurrent());

echo "<h1>returning all the indexes where the condition is located</h1>";
var_dump2(ArrayOne::set($values)->find([['type'=>'eq;fruit'],['price'=>'ge;200']],false,'key')->getCurrent());
echo "<h1>return the first value who matches the condition</h1>";
var_dump2(ArrayOne::set($values)->find([['type'=>'eq;fruit'],['price'=>'ge;200']],true,'value')->getCurrent());
