<?php

namespace eftec;

use Closure;
use RuntimeException;

/**
 * Class ArrayOne
 * @see       https://github.com/EFTEC/ArrayOne
 * @copyright Jorge Castro Castillo, dual license, see README.md for licensing.
 */
class ArrayOne
{
    public const VERSION = "1.1";
    /** @var array|null */
    protected $array;
    protected $serviceObject;
    /** @var mixed */
    protected $currentArray;
    protected $curNav;
    public static $error = '';

    /**
     * Constructor<br>
     * You can use (new ArrayOne($array))->method() or use ArrayOne::set($array)->method();
     * @param array|null $array
     */
    public function __construct(?array $array)
    {
        $this->array = $array;
        $this->currentArray =& $array;
    }

    /**
     * It gets the current version of the library
     * @return string
     */
    public function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * It sets the array to be transformed, and it starts the pipeline
     * <b>Example:</b><br>
     * <pre>
     * ArrayOne::set($array)->all();
     * ArrayOne::set($array,$object)->all(); // the object is used by validate()
     * ArrayOne::set($array,SomeClass:class)->all(); // the object is used by validate()
     * </pre>
     * @param array|null         $array
     * @param object|null|string $service the service instance. You can use the class or an object.
     * @return ArrayOne
     */
    public static function set(?array $array, $service = null): ArrayOne
    {
        $instance = new ArrayOne($array);
        if (is_string($service)) {
            $instance->serviceObject = new $service();
        } else {
            $instance->serviceObject = $service;
        }
        return $instance;
    }

    /**
     * It sets the array using a json.
     * <b>Example:</b><br>
     * <pre>
     * ArrayOne::setJson('{"a":3,"b":[1,2,3]}')->all();
     * </pre>
     * @param string $json
     * @return ArrayOne
     */
    public static function setJson(string $json): ArrayOne
    {
        $json = json_decode($json, true);
        return self::set($json);
    }

    /**
     * It sets the array using a csv. This csv must have a header.<br>
     * <b>Example:</b><br>
     * <pre>
     * ArrayOne::setCsv("a,b,c\n1,2,3\n4,5,6")->all();
     * </pre>
     * @param string $string    the string to parse
     * @param string $separator default ",". Set the field delimiter (one character only).
     * @param string $enclosure default '"'. Set the field enclosure character (one character only).
     * @param string $escape    default "\\". Set the escape character (one character only).
     * @return ArrayOne
     */
    public static function setCsv(string $string, string $separator = ",", string $enclosure = '"', string $escape = "\\"): ArrayOne
    {
        $csv = [];
        $string = str_replace("\r\n", "\n", $string);
        $lines = explode("\n", $string);
        $header = str_getcsv($lines[0], $separator, $enclosure, $escape); // get header
        array_shift($lines); // remove column header
        foreach ($lines as $line) {
            $csv[] = array_combine($header, str_getcsv($line, $separator, $enclosure, $escape));
        }
        return self::set($csv);
    }

    /**
     * It sets the array using a head-less csv.<br>
     * <b>Example:</b><br>
     * <pre>
     * ArrayOne::setCsvHeadLess("1,2,3\n4,5,6")->all();
     * ArrayOne::setCsvHeadLess("1,2,3\n4,5,6",['c1','c2','c3'])->all();
     * </pre>
     * @param string     $string    the string to parse
     * @param array|null $header    If the header is null, then it creates an indexed array.<br>
     *                              if the header is an array, then it is used as header
     * @param string     $separator default ",". Set the field delimiter (one character only).
     * @param string     $enclosure default '"'. Set the field enclosure character (one character only).
     * @param string     $escape    default "\\". Set the escape character (one character only).
     * @return ArrayOne
     */
    public static function setCsvHeadLess(string $string, ?array $header = null, string $separator = ",", string $enclosure = '"', string $escape = "\\"): ArrayOne
    {
        $csv = [];
        $string = str_replace("\r\n", "\n", $string);
        $lines = explode("\n", $string);
        foreach ($lines as $line) {
            if ($header !== null) {
                $csv[] = array_combine($header, str_getcsv($line, $separator, $enclosure, $escape));
            }
        }
        return self::set($csv);
    }

    /**
     * Navigate inside the arrays.<br>
     * If you want to select a subcolumn, then you could indicate it separated by dot: column.subcolumn. You
     * can separate up to 5 levels.
     *
     * <b>Example:</b><br>
     * <pre>
     * $this->nav('col');
     * $this->nav(); // return to root.
     * $this->nav('col.subcol.subsubcol'); //  [col=>[subcol=>[subsubcol=>[1,2,3]]]] returns  [1,2,3]
     * </pre>
     * @param string|int|null $colName   the name of the field. If null then it returns to the root.<br>
     *                                   You can add more leves by separating by "."
     * @return $this
     */
    public function nav($colName = null): ArrayOne
    {
        $this->curNav = $colName === null ?: explode('.', $colName);
        $c = count($this->curNav);
        $found = false;
        if ($c > 5) {
            throw new RuntimeException('nav: too many levels');
        }
        if ($c > 0) {
            $found = array_key_exists($this->curNav[0], $this->array);
        }
        if ($c > 1 && $found) {
            $found = array_key_exists($this->curNav[1], $this->array[$this->curNav[0]]);
        }
        if ($c > 2 && $found) {
            $found = array_key_exists($this->curNav[2], $this->array[$this->curNav[0]][$this->curNav[1]]);
        }
        if ($c > 3 && $found) {
            $found = array_key_exists($this->curNav[3], $this->array[$this->curNav[0]][$this->curNav[1]][$this->curNav[2]]);
        }
        if ($c > 4 && $found) {
            $found = array_key_exists($this->curNav[4], $this->array[$this->curNav[0]][$this->curNav[1]][$this->curNav[2]][$this->curNav[3]]);
        }
        if ($found) {
            switch ($c) {
                case 1:
                    $tmpArr = &$this->array[$this->curNav[0]];
                    break;
                case 2:
                    $tmpArr = &$this->array[$this->curNav[0]][$this->curNav[1]];
                    break;
                case 3:
                    $tmpArr = &$this->array[$this->curNav[0]][$this->curNav[1]][$this->curNav[2]];
                    break;
                case 4:
                    $tmpArr = &$this->array[$this->curNav[0]][$this->curNav[1]][$this->curNav[2]][$this->curNav[3]];
                    break;
                case 5:
                    $tmpArr = &$this->array[$this->curNav[0]][$this->curNav[1]][$this->curNav[2]][$this->curNav[3]][$this->curNav[4]];
                    break;
                default:
                    $tmpArr = [];
            }
        }
        if ($this->curNav === null) {
            $this->currentArray = &$this->array;
        } else if (!$found) {
            throw new RuntimeException("nav: level [$colName] not found");
        } else {
            $this->currentArray = $tmpArr;
        }
        $this->setCurrentArray($this->currentArray, false);
        return $this;
    }

    /*
     * Returns the whole array transformed and not only the current navigation.<br>
     * <b>Example:</b><br>
     * <pre>
     * $this->set($array)->nav('field')->all();
     * </pre>
     */
    public function all(): ?array
    {
        return $this->array;
    }

    /**
     * Returns the result indicated by nav(). If you want to return the whole array, then use a()
     * <b>Example:</b><br>
     * <pre>
     * $this->set($array)->nav('field')->current();
     * </pre>
     * @return mixed
     */
    public function current()
    {
        return $this->curNav === null ? $this->array : $this->currentArray;
    }

    /**
     * It adds or modify a column.
     * <b>Example:</b><br>
     * <pre>
     * $this->setCol('col1',function($row,$index) { return $row['col2']*$row['col3'];  });
     * </pre>
     * @param string|int|null $colName   the name of the column. If null, then it uses the entire row
     * @param callable|null   $operation the operation to realize.
     * @return $this
     */
    public function setCol($colName = null, ?callable $operation = null): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        foreach ($this->currentArray as $index => $row) {
            if ($colName === null) {
                $this->currentArray[$index] = $operation($row, $index);
            } else {
                $this->currentArray[$index][$colName] = $operation($row, $index);
            }
        }
        $this->setCurrentArray($this->currentArray, false);
        return $this;
    }

    /**
     * It removes a column<br>
     * <b>Example:</b><br>
     * <pre>
     * $this->removeCol('col1');
     * $this->removeCol(['col1','col2']);
     * </pre>
     * @param mixed $colName The name of the column or columns (array)
     * @return $this
     */
    public function removeCol($colName): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        foreach ($this->currentArray as $index => $row) {
            if (is_array($colName)) {
                foreach ($colName as $c) {
                    unset($this->currentArray[$index][$c]);
                }
            } else {
                unset($this->currentArray[$index][$colName]);
            }
        }
        return $this;
    }

    /**
     * Returns a single column as an array of values.<br>
     * <b>Example:</b><br>
     * <pre>
     * $this->col('c1'); // [['c1'=>1,'c2'=>2],['c1'=>3,'c2'=>4]] => [['c1'=>1],['c1'=>3]];
     * </pre>
     * @param mixed $colName the name of the column
     * @return $this
     */
    public function col($colName): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        foreach ($this->currentArray as $index => $row) {
            $this->currentArray[$index] = $this->currentArray[$index][$colName];
        }
        return $this;
    }

    /**
     * Joins the current array with another array<br>
     * If the columns of both arrays have the same name, then the current name is retained.<br>
     * <b>Example:</b><br>
     * <pre>
     * $products=[['id'=>1,'name'=>'cocacola','idtype'=>123]];
     * $types=[['id'=>123,'desc'=>'it is the type #123']];
     * ArrayOne::set($products)->join($types,'idtype','id')->all()
     * // [['id'=>1,'prod'=>'cocacola','idtype'=>123,'desc'=>'it is the type #123']] "id" is from product.
     * </pre>
     * @param array|null $arrayToJoin
     * @param mixed      $column1 the column of the current array
     * @param mixed      $column2 the column of the array to join.
     * @return $this
     */
    public function join(?array $arrayToJoin, $column1, $column2): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        foreach ($this->currentArray as $index => $row) {
            foreach ($arrayToJoin as $row2) {
                /** @noinspection TypeUnsafeComparisonInspection */
                if ($row[$column1] == $row2[$column2]) {
                    $this->currentArray[$index] = array_merge($row2, $this->currentArray[$index]);
                    break;
                }
            }
        }
        $this->setCurrentArray($this->currentArray, false);
        return $this;
    }

    /**
     * It filters the values. If the condition is false, then the row is deleted. It uses array_filter()<br>
     * The indexes are not rebuilt.
     * <b>Example:</b><br>
     * <pre>
     * $array = [['id' => 1, 'name' => 'chile'], ['id' => 2, 'name' => 'argentina'], ['id' => 3, 'name' => 'peru']];
     * // ['id' => 2, 'name' => 'argentina']
     * $r = ArrayOne::set($array)->filter(function($row, $id) {return $row['id'] === 2;}, true)->result();
     * // [1=>['id' => 2, 'name' => 'argentina']]
     * $r = ArrayOne::set($array)->filter(function($row, $id) {return $row['id'] === 2;}, false)->result();
     * </pre>
     * @param callable|null|array $condition you can use a callable function ($row,$id)<br>
     *                                       or a comparison array ['id'=>'eq;2']
     * @param bool                $flat
     * @return $this
     * @see ArrayOne::validate to see the definition of comparison array
     */
    public function filter($condition, bool $flat = false): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        if ($condition instanceof Closure) {
            $this->currentArray = array_filter($this->currentArray, $condition, ARRAY_FILTER_USE_BOTH);
        } else {
            $this->currentArray = array_filter($this->currentArray,
                function($row, $index) use ($condition) {
                    return $this->filterCondition($row, $index, $condition);
                },
                ARRAY_FILTER_USE_BOTH);
        }
        if ($flat && count($this->currentArray) === 1) {
            $this->currentArray = reset($this->currentArray);
        }
        $this->setCurrentArray($this->currentArray, false);
        return $this;
    }

    /**
     * @param mixed $row
     * @param mixed $index
     * @param array $condition
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    protected function filterCondition($row, $index, array $condition): bool
    {
        if (!is_array($row)) {
            $row = [$row];
        }
        $fail = false;
        foreach ($row as $k => $r) {
            if (isset($condition[$k])) {
                $vparts = explode('|', $condition[$k]);
                foreach ($vparts as $vpart) {
                    $fragment = explode(';', $vpart, 3);
                    $type = $fragment[0];
                    $compValue = $fragment[1] ?? null;
                    if (strpos($compValue, ',') !== false) {
                        $compValue = explode(',', $compValue);
                    }
                    $msg = '';
                    $this->runCondition($r,$compValue,$type,$fail,$msg);
                    if($fail) {
                        break 2;
                    }
                }
            }
        }
        return !$fail;
    }

    /**
     * It calls a function for every element of an array
     * @param callable|null $condition The function to call.
     * @return $this
     */
    public
    function map(?callable $condition): ArrayOne
    {
        $this->setCurrentArray(array_map($condition, $this->currentArray));
        return $this;
    }

    /**
     * It flats the results. If the result is an array with a single row, then it returns the row without the array<br>
     * <b>Example:</b><br>
     * <pre>
     * $this->flat(); // [['a'=>1,'b'=>2]] => ['a'=>1,'b'=>2]
     * </pre>
     * @return $this
     */
    public
    function flat(): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        if (!is_array($this->currentArray) || count($this->currentArray) !== 1) {
            return $this;
        }
        $this->currentArray = reset($this->currentArray);
        $this->setCurrentArray($this->currentArray, false);
        return $this;
    }

    /** @noinspection TypeUnsafeArraySearchInspection */
    private
    function runCondition($r, $compareValue, $compareType, bool &$fail, ?string &$genMsg): void
    {
        if (strpos($compareType, 'f:') === 0) {
            if ($this->serviceObject === null) {
                throw new RuntimeException('validate: no service class');
            }
            $namefunction = substr($compareType, 2); // remove the 'f:'
            $fail = !$this->serviceObject->$namefunction($r, $compareValue, $genMsg);
            return;
        }
        switch ($compareType) {
            case 'contain':
            case 'like':
                if (strpos((string)$r, $compareValue) === false) {
                    $fail = true;
                    $genMsg = '%field contains %comp';
                }
                break;
            case 'notcontain':
                if (strpos((string)$r, $compareValue) !== false) {
                    $fail = true;
                    $genMsg = '%field does not contain %comp';
                }
                break;
            case 'alpha':
                if (!ctype_alpha($r)) {
                    $fail = true;
                    $genMsg = '%field is not alphabetic';
                }
                break;
            case 'alphanumunder':
                if (!ctype_alnum(str_replace('_', '', (string)$r))) {
                    $fail = true;
                    $genMsg = '%field is not alphanumeric with underscore';
                }
                break;
            case 'alphanum':
                //
                if (!ctype_alnum($r)) {
                    $fail = true;
                    $genMsg = '%field is not alphanumeric';
                }
                break;
            case 'text':
                // words, number, accents, spaces, and other characters
                /** @noinspection NotOptimalRegularExpressionsInspection */
                if (!preg_match('^[\p{L}| |.|\/|*|+|.|,|=|_|"|\']+$', (string)$r)) {
                    $fail = true;
                    $genMsg = '%field has characters not allowed';
                }
                break;
            case 'regexp':
                if (!preg_match($compareValue, (string)$r)) {
                    $fail = true;
                    $genMsg = '%field is not allowed';
                }
                break;
            case 'email':
                if (!filter_var($r, FILTER_VALIDATE_EMAIL)) {
                    $fail = true;
                    $genMsg = '%field is not an email';
                }
                break;
            case 'url':
                if (!filter_var($r, FILTER_VALIDATE_URL)) {
                    $fail = true;
                    $genMsg = '%field is not an url';
                }
                break;
            case 'domain':
                if (!filter_var($r, FILTER_VALIDATE_DOMAIN)) {
                    $fail = true;
                    $genMsg = '%field is not a domain';
                }
                break;
            case 'minlen':
                $l = is_array($r) ? count($r) : strlen((string)$r);
                if ($l < $compareValue) {
                    $fail = true;
                    $genMsg = '%field size is less than %comp';
                }
                break;
            case 'maxlen':
                $l = is_array($r) ? count($r) : strlen((string)$r);
                if ($l > $compareValue) {
                    $fail = true;
                    $genMsg = '%field size is great than %comp';
                }
                break;
            case 'betweenlen':
                $rl = is_array($r) ? count($r) : strlen((string)$r);
                if ($rl < $compareValue[0] || $rl > $compareValue[1]) {
                    $fail = true;
                    $genMsg = '%field size is not between %first and %second ';
                }
                break;
            case 'exist':
                if (!isset($r)) { // file uses a different method
                    $fail = true;
                    $genMsg = '%field does not exist';
                }
                break;
            case 'missing':
            case 'notexist':
                if (isset($r)) { // file uses a different method
                    $fail = true;
                    $genMsg = '%field exists';
                }
                break;
            case 'req':
            case 'required':
                if (!$r) {
                    $fail = true;
                    $genMsg = '%field is required';
                }
                break;
            case 'eq':
            case '==':
                if (is_array($compareValue)) {
                    /** @noinspection TypeUnsafeArraySearchInspection */
                    if (!in_array($r, $compareValue)) {
                        $fail = true;
                        $genMsg = '%field is not equals than %comp';
                    }
                } /** @noinspection TypeUnsafeComparisonInspection */ elseif ($r != $compareValue) {
                    $fail = true;
                    $genMsg = '%field is not equals than %comp';
                }
                break;
            case 'ne':
            case '!=':
            case '<>':
                if (is_array($compareValue)) {
                    if (in_array($r, $compareValue)) {
                        $fail = true;
                        $genMsg = '%field is in %comp';
                    }
                } /** @noinspection TypeUnsafeComparisonInspection */ elseif ($r == $compareValue) {
                    $fail = true;
                    $genMsg = '%field is equals than %comp';
                }
                break;
            case 'null':
                if ($r !== null) {
                    $fail = true;
                    $genMsg = '%field is not null';
                }
                break;
            case 'empty':
                if (!empty($r)) {
                    $fail = true;
                    $genMsg = '%field is not empty';
                }
                break;
            case 'notempty':
                if (empty($r)) {
                    $fail = true;
                    $genMsg = '%field is empty';
                }
                break;
            case 'notnull':
                if ($r === null) {
                    $fail = true;
                    $genMsg = '%field is null';
                }
                break;
            case 'lt':
                if ($r >= $compareValue) {
                    $fail = true;
                    $genMsg = '%field is great or equal than %comp';
                }
                break;
            case 'lte':
                if ($r > $compareValue) {
                    $fail = true;
                    $genMsg = '%field is great than %comp';
                }
                break;
            case 'gt':
                if ($r <= $compareValue) {
                    $fail = true;
                    $genMsg = '%field is less or equal than %comp';
                }
                break;
            case 'gte':
                if ($r < $compareValue) {
                    $fail = true;
                    $genMsg = '%field is less than %comp';
                }
                break;
            case 'between':
                $rl = is_array($r) ? count($r) : $r;
                if (!isset($compareValue[0], $compareValue[1])) {
                    $fail = true;
                    $genMsg = '%field (between) lacks conditions';
                } else if ($rl < $compareValue[0] || $rl > $compareValue[1]) {
                    $fail = true;
                    $genMsg = '%field is not between ' . $compareValue[0] . " and " . $compareValue[1];
                }
                break;
            case 'true':
                if ($r === true || $r === 1 || $r === '1') {
                    $fail = true;
                    $genMsg = '%field is not true';
                }
                break;
            case 'false':
                if ($r === false || $r === 0 || $r === '0') {
                    $fail = true;
                    $genMsg = '%field is not false';
                }
                break;
            case 'array':
                if (!is_array($r)) {
                    $fail = true;
                    $genMsg = '%field is not an array';
                }
                break;
            case 'int':
                if (!is_int($r)) {
                    $fail = true;
                    $genMsg = '%field is not an integer';
                }
                break;
            case 'string':
                if (!is_string($r)) {
                    $fail = true;
                    $genMsg = '%field is not a string';
                }
                break;
            case 'float':
                if (!is_float($r)) {
                    $fail = true;
                    $genMsg = '%field is not a float';
                }
                break;
            case 'object':
                if (!is_object($r)) {
                    $fail = true;
                    $genMsg = '%field is not an object';
                }
                break;
            case 'in':
                if (!is_array($compareValue)) {
                    $fail = true;
                    $genMsg = '%field has no values to compare';
                }
                if (!in_array($r, $compareValue)) {
                    $fail = true;
                    $genMsg = '%field is not in list';
                }
                break;
            case 'notin':
                if (!is_array($compareValue)) {
                    $fail = true;
                    $genMsg = '%field has no values to compare';
                }
                if (in_array($r, $compareValue)) {
                    $fail = true;
                    $genMsg = '%field should not be in list';
                }
                break;
            default:
                throw new RuntimeException("ArrayOne comparison [$compareType] does not exist");
                //$fail = true;
                //$genMsg = "Unknown comparison [$compareType]";
        }
    }

    /**
     * It returns the first element of an array.
     * @return $this
     */
    public
    function first(): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        $this->setCurrentArray(reset($this->currentArray));
        return $this;
    }

    /**
     * It returns the last element of an array.
     * @return $this
     */
    public
    function last(): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        $this->setCurrentArray(end($this->currentArray));
        return $this;
    }

    /**
     * It returns the n-position of an array.
     * @param $index
     * @return $this
     */
    public
    function nPos($index): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        if (!array_key_exists($index, $this->currentArray)) {
            throw new RuntimeException("nPos: index [$index] does not exist");
        }
        $this->setCurrentArray($this->currentArray[$index]);
        return $this;
    }

    /**
     * It is used internally to update the link between array and currentarray.
     * @param mixed $values
     * @param bool  $overwrite
     * @return void
     */
    protected
    function setCurrentArray($values, bool $overwrite = true): void
    {
        if ($overwrite) {
            $this->currentArray = $values;
        }
        if ($this->curNav === null) {
            $this->array =& $this->currentArray;
            return;
        }
        $c = count($this->curNav);
        switch ($c) {
            case 1:
                $this->array[$this->curNav[0]] =& $this->currentArray;
                break;
            case 2:
                $this->array[$this->curNav[0]][$this->curNav[1]] =& $this->currentArray;
                break;
            case 3:
                $this->array[$this->curNav[0]][$this->curNav[1]][$this->curNav[2]] =& $this->currentArray;
                break;
            case 4:
                $this->array[$this->curNav[0]][$this->curNav[1]][$this->curNav[2]]
                [$this->curNav[3]] =& $this->currentArray;
                break;
            case 5:
                $this->array[$this->curNav[0]][$this->curNav[1]][$this->curNav[2]]
                [$this->curNav[3]][$this->curNav[4]] =& $this->currentArray;
                break;
        }
    }

    /**
     * You can reduce (flat) an array using aggregations or a custom function.
     * <b>Example:</b><br>
     * <pre>
     * $this->reduce(['col1'=>'sum','col2'=>'avg','col3'=>'min','col4'=>'max']);
     * $this->reduce(function($row,$index,$prev) { return ['col1'=>$row['col1']+$prev['col1]];  });
     * </pre>
     * @param array|callable $functionAggregation An associative array where the index is the column and the value
     *                                            is the function of aggregation<br>
     *                                            A function using the syntax: function ($row,$index,$prev) where $prev
     *                                            is the accumulator value
     * @return $this
     */
    public
    function reduce($functionAggregation): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        $initial = reset($this->currentArray);
        // delete the columns that we won't use
        if (is_array($functionAggregation)) {
            foreach ($initial as $k => $v) {
                if (!array_key_exists($k, $functionAggregation)) {
                    unset($initial[$k]);
                }
            }
        }
        $skip = true;
        if ($functionAggregation instanceof Closure) {
            foreach ($this->currentArray as $index => $row) {
                if ($skip) {
                    $skip = false;
                } else {
                    $initial = $functionAggregation($row, $index, $initial);
                }
            }
        } else {
            foreach ($this->currentArray as $row) {
                if ($skip) {
                    $skip = false;
                } else {
                    foreach ($functionAggregation as $col => $fun) {
                        switch ($fun) {
                            case 'sum':
                            case 'avg':
                                $initial[$col] = $row[$col] + $initial[$col];
                                break;
                            case 'min':
                                $initial[$col] = min($row[$col], $initial[$col]);
                                break;
                            case 'max':
                                $initial[$col] = max($row[$col], $initial[$col]);
                                break;
                        }
                    }
                }
            }
            foreach ($functionAggregation as $col => $fun) {
                switch ($fun) {
                    case 'avg':
                        $initial[$col] /= count($this->currentArray);
                        break;
                    case 'count':
                        $initial[$col] = count($this->currentArray);
                        break;
                }
            }
        }
        $this->setCurrentArray($initial);
        //$this->currentArray = $initial;
        return $this;
    }

    /**
     * It converts the index into a field, and renumerates the array<br>
     * <b>Example:</b><br>
     * <pre>
     * $this->indexToField('colnew'); // ['a'=>['col1'=>'b','col2'=>'c']] => [['colnew'=>'a','col1'=>'b','col2'=>'c']
     * </pre>
     * @param mixed $newColumn the name of the new column
     * @return $this
     */
    public
    function indexToColumn($newColumn): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        foreach ($this->currentArray as $index => &$row) {
            $row[$newColumn] = $index;
        }
        unset($row);
        $this->setCurrentArray(array_values($this->currentArray));
        return $this;
    }

    /**
     * it converts a column into an index<br>
     * <b>Example:</b><br>
     * <pre>
     * $this->indexToField('colold'); //  [['colold'=>'a','col1'=>'b','col2'=>'c'] => ['a'=>['col1'=>'b','col2'=>'c']]
     * </pre>
     * @param mixed $oldColumn the old column. This column will be converted into an index
     * @return $this
     */
    public
    function columnToIndex($oldColumn): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        $result = [];
        foreach ($this->currentArray as $row) {
            $newIndex = $row[$oldColumn];
            unset($row[$oldColumn]);
            $result[$newIndex] = $row;
        }
        $this->setCurrentArray($result);
        return $this;
    }

    /**
     * It groups one column and return its column grouped and values aggregated<br>
     * <b>Example:</b><br>
     * <pre>
     * $this->group('type',['amount'=>'sum','price'=>'sum']);
     * </pre>
     * @param mixed $column              the column to group.
     * @param array $functionAggregation An associative array ['colname'=>'aggregation'] with the aggregations<br>
     *                                   <b>count</b>: Count<br>
     *                                   <b>avg</b>: Average<br>
     *                                   <b>min</b>: Minimum<br>
     *                                   <b>max</b>: Maximum<br>
     *                                   <b>sum</b>: Sum<br>
     *                                   <b>first</b>: First<br>
     *                                   <b>last</b>: last<br>
     *
     * @return $this
     */
    public
    function group($column, array $functionAggregation): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        $groups = [];
        foreach ($this->currentArray as $row) {
            if (array_key_exists($row[$column], $groups)) {
                $initial = $groups[$row[$column]];
                $initial['__count']++;
            } else {
                $initial = ['__count' => 1];
            }
            foreach ($functionAggregation as $col => $fun) {
                switch ($fun) {
                    case 'first':
                        if (!array_key_exists($col, $initial)) {
                            $initial[$col] = $row[$col];
                        }
                        break;
                    case 'last':
                        $initial[$col] = $row[$col];
                        break;
                    case 'avg':
                    case 'sum':
                        $initial[$col] = $row[$col] + ($initial[$col] ?? null);
                        break;
                    case 'min':
                        $initial[$col] = min($row[$col], $initial[$col] ?? PHP_INT_MAX);
                        break;
                    case 'max':
                        $initial[$col] = max($row[$col], $initial[$col] ?? null);
                        break;
                }
            }
            $groups[$row[$column]] = $initial;
        }
        foreach ($groups as $k => $v) {
            foreach ($functionAggregation as $col => $fun) {
                switch ($fun) {
                    case 'avg':
                        $groups[$k][$col] /= $groups[$k]['__count'];
                        break;
                    case 'count':
                        $groups[$k][$col] = $groups[$k]['__count'];
                        break;
                }
            }
            unset($groups[$k]['__count']);
        }
        $this->setCurrentArray($groups);
        return $this;
    }

    /**
     * Sort an array<br>
     * <b>Example:</b><br>
     * <pre>
     * $this->sort('payment','desc'); // sort an array using the column paypent descending.
     * </pre>
     * @param mixed  $column    if column is null, then it sorts the row (instead of a column of the row)
     * @param string $direction =['asc','desc'][$i]  ascending or descending.
     * @return $this
     */
    public
    function sort($column, string $direction = 'asc'): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        if ($column === null) {
            if ($direction === 'asc') {
                sort($this->currentArray);
            } else {
                rsort($this->currentArray);
            }
        } else {
            usort($this->currentArray, static function($row1, $row2) use ($direction, $column) {
                if ($row1[$column] === $row2[$column]) {
                    return 0;
                }
                if ($direction === 'asc') {
                    return ($row1[$column] > $row2[$column]) ? 1 : -1;
                }
                return ($row1[$column] < $row2[$column]) ? 1 : -1;
            });
        }
        $this->setCurrentArray($this->currentArray, false);
        return $this;
    }

    /**
     * todo: pending.
     * @param mixed $colName
     * @return $this
     */
    public
    function removeDuplicate($colName): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        return $this;
    }

    /**
     * It removes the row with the id $rowId. If the row does not exist, then it does nothing
     * <b>Example:</b><br>
     * <pre>
     * $this->removeRow(20);
     * </pre>
     * @param mixed $rowId    The id of the row to delete
     * @param bool  $renumber if true then it renumber the list<br>
     *                        ex: if 1 is deleted then $renumber=true: [0=>0,1=>1,2=>2] =>  [0=>0,1=>2]<br>
     *                        ex: if 1 is deleted then $renumber=false: [0=>0,1=>1,2=>2] =>  [0=>0,2=>2]<br>
     * @return $this
     */
    public
    function removeRow($rowId, bool $renumber = false): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        unset($this->currentArray[$rowId]);
        if ($renumber) {
            $this->currentArray = array_values($this->currentArray);
        }
        $this->setCurrentArray($this->currentArray, false);
        return $this;
    }

    /**
     * It removes the first row or rows. Numeric index could be renumbered.
     * <b>Example:</b><br>
     * <pre>
     * $this->removeFirstRow(3);
     * </pre>
     * @param int  $numberOfRows The number of rows to delete, the default is 1 (the first row)
     * @param bool $renumber     if true then it renumber the list<br>
     *                           ex: if 1 is deleted then $renumber=true: [0=>0,1=>1,'x'=>2] =>  [0=>0,1=>2]<br>
     *                           ex: if 1 is deleted then $renumber=false: [0=>0,1=>1,2=>2] =>  [0=>0,2=>2]<br>
     * @return $this
     */
    public
    function removeFirstRow(int $numberOfRows = 1, bool $renumber = false): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        for ($i = 0; $i < $numberOfRows; $i++) {
            array_shift($this->currentArray);
        }
        if ($renumber) {
            $this->currentArray = array_values($this->currentArray);
        }
        $this->setCurrentArray($this->currentArray, false);
        return $this;
    }

    /**
     * It removes the last row or rows
     * <b>Example:</b><br>
     * <pre>
     * $this->removeLastRow(3);
     * </pre>
     * @param int  $numberOfRows the number of rows to delete
     * @param bool $renumber     if true then it renumber the list (since we are deleting the last value then
     *                           usually we don't need it<br>
     *                           ex: if 1 is deleted then $renumber=true: [0=>0,1=>1,2=>2] =>  [0=>0,1=>2]<br>
     *                           ex: if 1 is deleted then $renumber=false: [0=>0,1=>1,2=>2] =>  [0=>0,2=>2]<br>
     * @return $this
     */
    public
    function removeLastRow(int $numberOfRows = 1, bool $renumber = false): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        for ($i = 0; $i < $numberOfRows; $i++) {
            array_pop($this->currentArray);
        }
        if ($renumber) {
            $this->currentArray = array_values($this->currentArray);
        }
        $this->setCurrentArray($this->currentArray, false);
        return $this;
    }

    /**
     * It generates a validate-array using an example array. It could be used by validation() and filter()<br>
     * <b>Example:</b><br>
     * <pre>
     * $this->getValidateArrayByExample(['1','a','f'=>3.3]); // ['int','string','f'=>'float'];
     * </pre>
     * @param array $array
     * @return array
     */
    public
    static function getValidateArrayByExample(array $array): array
    {
        $result = [];
        self::getValidateArrayByExampleRec($array, $result);
        return $result;
    }

    protected
    static function getValidateArrayByExampleRec($array, &$result): void
    {
        $v = null;
        foreach ($array as $rowid => $row) {
            switch (true) {
                case is_null($row):
                    $v = "nullable|string";
                    break;
                case is_int($row):
                    $v = "int";
                    break;
                case is_float($row):
                    $v = 'float';
                    break;
                case is_bool($row):
                    $v = 'bool';
                    break;
                case is_string($row):
                    $v = 'string';
                    break;
                case is_array($row):
                    $keyPrevious = null;
                    $table = true;
                    foreach ($row as $k2 => $v2) {
                        $result[$rowid][$k2] = [];
                        self::getValidateArrayByExampleRec($v2, $result[$rowid][$k2]);
                        $keys = array_keys($result[$rowid][$k2]);
                        if ($keyPrevious !== null && $keys !== $keyPrevious) {
                            $table = false;
                        }
                        $keyPrevious = $keys;
                    }
                    if ($table) {
                        $result[$rowid] = [$result[$rowid][0]];
                    }
                    break;
                default:
                    $v = $row;
            }
            if (!is_array($row)) {
                $result[$rowid] = $v;
            }
        }
    }

    /**
     * Validate the current array using a comparison table<br>
     * <b>Example:</b><br>
     * <pre>
     * $this->validate([
     *          'id'=>'int',
     *          'table'=>[['col1'=>'int','col2'=>'string']],   // note the double [[ ]] to indicate a table of values
     *          'list'=>[['int']]
     *     ]);
     * </pre>
     * @param array $comparisonTable <br>
     *                               <b>nullable</b> :the value can be a null. If the value is null, then it ignores
     *                               other validations<br>
     *                               <b>contain like</b> :if a text is contained in<br>
     *                               <b>notcontain</b> :if a text is not contained in<br>
     *                               <b>alpha</b> :if the value is alphabetic<br>
     *                               <b>alphanumunder</b> :if the value is alphanumeric or undercase<br>
     *                               <b>alphanum</b> :if the value is alphanumeric<br>
     *                               <b>text</b> :if the value is a text<br>
     *                               <b>regexp</b> :if the value match a regular expression<br>
     *                               <b>email</b> :if the value is an email<br>
     *                               <b>url</b> :if the value is an url<br>
     *                               <b>domain</b> :if the value is a domain<br>
     *                               <b>minlen</b> :the value must have a minimum lenght<br>
     *                               <b>maxlen</b> :the value must have a maximum lenght<br>
     *                               <b>betweenlen</b> :if the value has a size between<br>
     *                               <b>exist</b> :if the value exists<br>
     *                               <b>missing notexist</b> :if the value not exist<br>
     *                               <b>req required</b> :if the value is required<br>
     *                               <b>eq ==</b> :if the value is equals to<br>
     *                               <b>ne != <></b> :if the value is not equals to<br>
     *                               <b>null</b> :if the value is null<br>
     *                               <b>empty</b> :if the value is empty<br>
     *                               <b>notnull</b> :if the value is not null<br>
     *                               <b>lt</b> :if the value is less than<br>
     *                               <b>lte</b> :if the value is less or equals than<br>
     *                               <b>gt</b> :if the value is great than<br>
     *                               <b>gte</b> :if the value is great or equals than<br>
     *                               <b>between</b> :if the value is between<br>
     *                               <b>true</b> :if the value is true<br>
     *                               <b>false</b> :if the value is false<br>
     *                               <b>array</b> :if the value is an array<br>
     *                               <b>int</b> :if the value is an integer<br>
     *                               <b>string</b> :if the value is a string<br>
     *                               <b>float</b> :if the value is a float<br>
     *                               <b>object</b> :if the value is an object<br>
     * @param bool  $extraFieldError if true and the current array has more values than comparison table, then
     *                               it returns an error.
     * @return $this
     */
    public function validate(array $comparisonTable, bool $extraFieldError = false): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        $this->setCurrentArray($this->validateRec($comparisonTable, $this->currentArray, $extraFieldError));
        return $this;
    }

    protected
    function validateRec($comparisonTable, $values, $extraFieldError = false, $type = 'object'): array
    {
        $final = [];
        if ($type === 'object') {
            $final = $this->validateRecInside($comparisonTable, $values, $extraFieldError);
        } else {
            foreach ($values as $rowid => $row) {
                $final[] = $this->validateRecInside($comparisonTable[0], $row, $extraFieldError, $rowid);
            }
        }
        return $final;
    }

    protected
    function validateRecInside($comparisonTable, $values, $extraFieldError = false, $rowId = null)
    {
        $final = [];
        $flatResult = false;
        if (!is_array($values)) {
            $values = [$values];
            $flatResult = true;
        }
        foreach ($values as $field => $node) {
            if (array_key_exists($field, $comparisonTable)) {
                $v = $comparisonTable[$field];
                if (is_array($v)) {
                    // nested comparison
                    $type = 'object';
                    if (isset($comparisonTable[$field][0]) // we expected 'field'=>[[...]]
                        && count($comparisonTable[$field]) === 1 // we discard 'field=>[[...],[...]]
                    ) // we discard 'field'=>[[x]]
                    {
                        $type = 'table'; // the comparison is a table of values
                    }
                    $final[$field] = $this->validateRec($comparisonTable[$field], $node, $extraFieldError, $type);
                } else {
                    $final[$field] = null;
                    $vparts = explode('|', $v);
                    foreach ($vparts as $vpart) {
                        $fragment = explode(';', $vpart, 3);
                        $type = $fragment[0];
                        $compValue = $fragment[1] ?? null;
                        if (strpos($compValue, ',') !== false) {
                            $compValue = explode(',', $compValue);
                        }
                        $msg = $fragment[2] ?? null;
                        $fail = false;
                        $genMsg = null;
                        if ($node === null && $type === 'nullable') {
                            break;
                        }
                        $this->runCondition($node, $compValue, $type, $fail, $genMsg);
                        if ($fail) {
                            $msg = $msg ?? $genMsg;
                            if (is_array($compValue)) {
                                $first = @$compValue[0];
                                $second = @$compValue[1];
                            } else {
                                $first = null;
                                $second = null;
                            }
                            $compValueMsg = is_array($compValue) ? implode(',', $compValue) : $compValue;
                            $nodeMsg = is_array($node) || is_object($node) ? '(values)' : $node;
                            $msg = str_replace(
                                ['%field', '%value', '%comp', '%first', '%second', '%rowid', '\,'],
                                [$field, $nodeMsg, $compValueMsg, $first, $second, $rowId, ','], $msg);
                        } else {
                            // no error, no message.
                            $msg = null;
                        }
                        if (isset($final[$field])) {
                            $final[$field] .= '|' . $msg;
                        } else {
                            $final[$field] = $msg;
                        }
                    }
                }
            } else {
                // is a field that is not part of the comparison.
                $final[$field] = $extraFieldError ? "fiend $field not found" : null;
            }
        }
        if ($flatResult) {
            $final = $final[0];
        }
        return $final;
    }

    /**
     * It masks the current array using another array.<br>
     * Masking deletes all field that are not part of our mask<br>
     * The mask is smart to recognize a table, so it could mask multiples values by only specifying the first row.<br>
     * <b>Example:</b><br>
     * <pre>
     * $array=['a'=>1,'b'=>2,'c'=>3,'items'=>[[a1'=>1,'a2'=>2,'a3'=3],[a1'=>1,'a2'=>2,'a3'=3]];
     * $mask=['a'=>1,'items'=>[[a1'=>1]]; // [[a1'=>1]] masks an entire table
     * $this->mask($mask); // $array=['a'=>1,'items'=>[[a1'=>1],[a1'=>1]];
     * </pre>
     * @param array $arrayMask An associative array with the mask. The mask could contain any value.
     * @return ArrayOne
     */
    public
    function mask(array $arrayMask): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        $this->maskRec($arrayMask, $this->currentArray);
        $this->setCurrentArray($this->currentArray, false);
        return $this;
    }

    public
    function maskRec(array $arrayMask, &$current): void
    {
        foreach ($current as $k => $v) {
            if (!array_key_exists($k, $arrayMask)) {
                unset($current[$k]);
            } else if (is_array($v) && is_array($arrayMask[$k])) {
                $am = $arrayMask[$k];
                if (array_key_exists(0, $am) && count($am) === 1) {
                    foreach ($v as $k2 => $v2) {
                        $this->maskRec($am[0], $current[$k][$k2]);
                    }
                }
            }
        }
    }
}
