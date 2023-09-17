<?php /** @noinspection PhpLanguageLevelInspection */
/** @noinspection GrazieInspection */

/** @noinspection UnknownInspectionInspection */

namespace eftec;

use ArrayAccess;
use Closure;
use Exception;
use RuntimeException;

/**
 * Class ArrayOne
 * @see       https://github.com/EFTEC/ArrayOne
 * @copyright Jorge Castro Castillo, dual license, see README.md for licensing.
 */
class ArrayOne implements ArrayAccess
{
    public const VERSION = "1.8.1";
    /** @var array|null */
    protected $array;
    protected $serviceObject;
    /** @var mixed */
    protected $currentArray;
    protected $curNav;
    public static $error = '';

    /**
     * Constructor<br/>
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
     * <b>Example:</b><br/>
     * ```php
     * ArrayOne::set($array)->all();
     * ArrayOne::set($array,$object)->all(); // the object is used by validate()
     * ArrayOne::set($array,SomeClass:class)->all(); // the object is used by validate()
     * ```
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
     * It sets the initial array readint the values from the request (get/post/header/etc.)<br/>
     * <b>Example:</b><br/>
     * ```php
     * ArrayOne::setRequest([
     *     'id'=>'get', // $_GET['id'] if not found then it uses the default value (null)
     *     'name'=>'post|default', // $_POST['name'], if not found then it uses "default"
     *     'content'=>'body' // it reads from the POST body
     * ],null); // null is the default value if not other default value is set.
     * ```
     * @param array   $fields          An associative array when the values to read 'id'=>'type;defaultvalue'.
     *                                 Types:<br/>
     *                                 <b>get</b>: get it from the query string <br/>
     *                                 <b>post</b>: get it from the post<br/>
     *                                 <b>header</b>: get if from the header<br/>
     *                                 <b>request</b>: get if from the post, otherwise from get<br/>
     *                                 <b>cookie</b>: get if from the cookies<br/>
     *                                 <b>body</b>: get if from the post body (values are not serialized)<br/>
     *                                 <b>verb</b>: get if from the request method (GET/POST/PUT,etc.)<br/>
     * @param mixed   $defaultValueAll the default value if the value is not found and not other default value is set.
     * @param ?string $separator       Def:'.', The separator character used when the field is nested.<br/>
     *                                 example using '.' as separator html:<input name='a.b' value="hello" /><br/>
     *                                 result obtained:$result['a']['b']='hello';
     * @return ArrayOne
     */
    public static function setRequest(array $fields, $defaultValueAll = null, ?string $separator = '.'): ArrayOne
    {
        self::setRequestRec($fields, $defaultValueAll, $separator, '');
        return self::set($fields);
    }

    public static function setRequestRec(&$req, $defaultValueAll, $separator, $prefix)
    {
        if ($req === null) {
            return $req;
        }
        foreach ($req as $k => $v) {
            if (is_array($v)) {
                if (count($v) === 1 && isset($v[0])) {
                    // is a table
                    $cols = [];
                    foreach ($v[0] as $k2 => $v2) {
                        $cols[$k2] = [$k2 => $v2];
                        self::setRequestRec($cols[$k2], $defaultValueAll, $separator, $prefix . $k . $separator);
                    }
                    $table = [];
                    foreach ($cols as $k2 => $v2) {
                        foreach ($v2[$k2] as $k3 => $v3) {
                            $table[$k3][$k2] = $v3;
                        }
                    }
                    $req[$prefix . $k] = $table;
                } else {
                    // is an object or a structure.
                    self::setRequestRec($req[$prefix . $k], $defaultValueAll, $separator, $prefix . $k . $separator);
                }
            } else {
                $v = explode(';', $v);
                $type = $v[0];
                $default = array_key_exists(1, $v) ? $v[1] : $defaultValueAll;
                switch (strtolower($type)) {
                    case 'post':
                        $req[$k] = $_POST[$prefix . $k] ?? $default;
                        break;
                    case 'get':
                        $req[$k] = $_GET[$prefix . $k] ?? $default;
                        break;
                    case 'request':
                        $req[$k] = $_POST[$prefix . $k] ?? ($_GET[$prefix . $k] ?? $default);
                        break;
                    case 'cookie':
                        $req[$k] = $_COOKIE[$prefix . $k] ?? $default;
                        break;
                    case 'body':
                        $req[$k] = file_get_contents('php://input') ?: $default;
                        break;
                    case 'verb':
                        $req[$k] = $_SERVER['REQUEST_METHOD'] ?: $default;
                        break;
                    case 'header':
                        $req[$prefix . $k] = $_SERVER['HTTP_' . $prefix . $k] ?? $default;
                        break;
                    default:
                        throw new RuntimeException("ArrayOne::unknow request type [$type]");
                }
            }
        }
        return $req;
    }

    /**
     * It sets the array using a json.
     * <b>Example:</b><br/>
     * ```php
     * ArrayOne::setJson('{"a":3,"b":[1,2,3]}')->all();
     * ```
     * @param string $json
     * @return ArrayOne
     */
    public static function setJson(string $json): ArrayOne
    {
        $json = json_decode($json, true);
        return self::set($json);
    }

    /**
     * It sets the array using a csv. This csv must have a header.<br/>
     * <b>Example:</b><br/>
     * ```php
     * ArrayOne::setCsv("a,b,c\n1,2,3\n4,5,6")->all();
     * ```
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
     * It sets the array using a head-less csv.<br/>
     * <b>Example:</b><br/>
     * ```php
     * ArrayOne::setCsvHeadLess("1,2,3\n4,5,6")->all();
     * ArrayOne::setCsvHeadLess("1,2,3\n4,5,6",['c1','c2','c3'])->all();
     * ```
     * @param string     $string    the string to parse
     * @param array|null $header    If the header is null, then it creates an indexed array.<br/>
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
     * Navigate inside the arrays.<br/>
     * If you want to select a subcolumn, then you could indicate it separated by dot: column.subcolumn. You
     * can separate up to 5 levels.
     *
     * <b>Example:</b><br/>
     * ```php
     * $this->nav('col');
     * $this->nav(); // return to root.
     * $this->nav('col.subcol.subsubcol'); //  [col=>[subcol=>[subsubcol=>[1,2,3]]]] returns  [1,2,3]
     * ```
     * @param string|int|null $colName   the name of the field. If null then it returns to the root.<br/>
     *                                   You can add more leves by separating by "."
     * @return $this
     */
    public function nav($colName = null): ArrayOne
    {
        $this->setCurrentArray();
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
        return $this;
    }

    /**
     * Returns the whole array transformed and not only the current navigation.<br/>
     * <b>Example:</b><br/>
     * ```php
     * $this->set($array)->nav('field')->all();
     * ```
     */
    public function all(): ?array
    {
        $this->setCurrentArray();
        return $this->array;
    }

    /**
     * @return array|mixed|null
     * @deprecated This functionality will work 1.x but in 2.x will be discontinued. Use getCurrent()
     */
    public function current()
    {
        return $this->getCurrent();
    }

    /**
     * Returns the result indicated by nav(). If you want to return the whole array, then use a()
     * <b>Example:</b><br/>
     * ```php
     * $this->set($array)->nav('field')->getCurrent();
     * ```
     * @return mixed
     */
    public function getCurrent()
    {
        $this->setCurrentArray();
        return $this->curNav === null ? $this->array : $this->currentArray;
    }

    /**
     * It adds or modify a column.
     * <b>Example:</b><br/>
     * ```php
     * $this->modCol('col1',function($row,$index) { return $row['col2']*$row['col3'];  });
     * ```
     * @param string|int|null $colName   the name of the column. If null, then it uses the entire row
     * @param callable|null   $operation the operation to realize.
     * @return $this
     */
    public function modCol($colName = null, ?callable $operation = null): ArrayOne
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
        return $this;
    }

    /**
     * It removes a column<br/>
     * <b>Example:</b><br/>
     * ```php
     * $this->removeCol('col1');
     * $this->removeCol(['col1','col2']);
     * ```
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
     * Returns a single column as an array of values.<br/>
     * <b>Example:</b><br/>
     * ```php
     * $this->col('c1'); // [['c1'=>1,'c2'=>2],['c1'=>3,'c2'=>4]] => [['c1'=>1],['c1'=>3]];
     * ```
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
     * Joins the current array with another array<br/>
     * If the columns of both arrays have the same name, then the current name is retained.<br/>
     * <b>Example:</b><br/>
     * ```php
     * $products=[['id'=>1,'name'=>'cocacola','idtype'=>123]];
     * $types=[['id'=>123,'desc'=>'it is the type #123']];
     * ArrayOne::set($products)->join($types,'idtype','id')->all()
     * // [['id'=>1,'prod'=>'cocacola','idtype'=>123,'desc'=>'it is the type #123']] "id" is from product.
     * ```
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
        return $this;
    }

    /**
     * It filters the values. If the condition is false, then the row is deleted. It uses array_filter()<br/>
     * The indexes are not rebuilt.<br>
     * <b>Example:</b><br/>
     * ```php
     * $array = [['id' => 1, 'name' => 'chile'], ['id' => 2, 'name' => 'argentina'], ['id' => 3, 'name' => 'peru']];
     * // get the row #2 "argentina":
     * // using a function:
     * $r = ArrayOne::set($array)->filter(function($row, $id) {return $row['id'] === 2;}, true)->result();
     * // using a function a returning a flat result:
     * $r = ArrayOne::set($array)->filter(function($row, $id) {return $row['id'] === 2;}, false)->result();
     * // using an associative array:
     * $r = ArrayOne::set($array)->filter(['id'=>'eq;2'], false)->result();
     * // using an associative array that contains an array:
     * $r = ArrayOne::set($array)->filter(['id'=>['eq,2], false)->result();
     * ```
     * @param callable|null|array $condition you can use a callable function ($row,$id)<br/>
     *                                       or a comparison array ['id'=>'eq;2|lt;3'] "|" adds more comparisons<br>
     *                                       or a comparison array ['id'=>[['eq',2],['lt',3]]<br>
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
        return $this;
    }

    /**
     * It is used internally to filter rows unsing a specific condition
     * @param mixed $row
     * @param mixed $index
     * @param array $condition as text:['col'=>'eq;2']<br/>
     *                         as array:['col'=>['eq',2]]</br>
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
                if (is_array($condition[$k])) {
                    if (is_array($condition[$k][0])) {
                        $conds = $condition[$k]; // [['eq',2],['eq',3]]
                    } else {
                        $conds = [$condition[$k]]; // // ['eq',2] =>[['eq',2']]
                    }
                    foreach ($conds as $cond) {
                        $compValue = $cond[1] ?? null;
                        $type = $cond[0] ?? '';
                        $msg = '';
                        $this->runCondition($r, $compValue, $type, $fail, $msg);
                        if ($fail) {
                            break 2;
                        }
                    }
                } else {
                    $vparts = explode('|', $condition[$k]);
                    foreach ($vparts as $vpart) {
                        $fragment = explode(';', $vpart, 3);
                        $type = $fragment[0];
                        $compValue = $fragment[1] ?? null;
                        if ($compValue !== null && strpos($compValue, ',') !== false) {
                            $compValue = explode(',', $compValue);
                        }
                        $msg = '';
                        $this->runCondition($r, $compValue, $type, $fail, $msg);
                        if ($fail) {
                            break 2;
                        }
                    }
                }
            }
        }
        return !$fail;
    }

    /**
     * It calls a function for every element of an array<br>
     * <b>Example:</b><br/>
     * <i>$this->map(function($row) { return strtoupper($row); });</i><br>
     *
     * @param callable|null $condition The function to call.<br>
     *                                 It must has an argument (the current row) and it must returns a value
     * @return $this
     */
    public function map(?callable $condition): ArrayOne
    {
        $this->currentArray = array_map($condition, $this->currentArray);
        return $this;
    }

    /**
     * It flats the results. If the result is an array with a single row, then it returns the row without the array<br/>
     * <b>Example:</b><br/>
     * ```php
     * $this->flat(); // [['a'=>1,'b'=>2]] => ['a'=>1,'b'=>2]
     * ```
     * @return $this
     */
    public function flat(): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        if (!is_array($this->currentArray) || count($this->currentArray) !== 1) {
            return $this;
        }
        $this->currentArray = reset($this->currentArray);
        return $this;
    }

    /** @noinspection TypeUnsafeArraySearchInspection
     * @noinspection TypeUnsafeComparisonInspection
     */
    private function runCondition($r, $compareValue, string $compareType, bool &$fail, ?string &$genMsg): void
    {
        if (strpos($compareType, 'f:') === 0) {
            if ($this->serviceObject === null) {
                throw new RuntimeException('validate: no service class');
            }
            $namefunction = substr($compareType, 2); // remove the 'f:'
            $fail = !$this->serviceObject->$namefunction($r, $compareValue, $genMsg);
            return;
        }
        if (strpos($compareType, 'not') === 0) {
            $negation = true;
            $compareType = substr($compareType, 3);
        } else {
            $negation = false;
        }
        switch ($compareType) {
            case 'contain':
            case 'like':
                if ((strpos((string)$r, $compareValue) !== false) === $negation) {
                    $fail = true;
                    $genMsg = $negation ? '%field does not contains %comp' : '%field contains %comp';
                }
                break;
            case 'alpha':
                if (ctype_alpha($r) === $negation) {
                    $fail = true;
                    $genMsg = $negation ? '%field is alphabetic' : '%field is not alphabetic';
                }
                break;
            case 'alphanumunder':
                if (ctype_alnum(str_replace('_', '', (string)$r)) === $negation) {
                    $fail = true;
                    $genMsg = $negation ?
                        '%field is alphanumeric with underscore' :
                        '%field is not alphanumeric with underscore';
                }
                break;
            case 'alphanum':
                //
                if (ctype_alnum($r) === $negation) {
                    $fail = true;
                    $genMsg = $negation ? '%field is alphanumeric' : '%field is not alphanumeric';
                }
                break;
            case 'text':
                // words, number, accents, spaces, and other characters
                try {
                    $r = (string)$r;
                } catch (Exception $ex) {
                    $r = '!';
                }
                if (preg_match('/^[\pL\pM\p{Zs}.-]+$/u', (string)$r) == $negation) {
                    $fail = true;
                    $genMsg = $negation ?
                        '%field hasn\'t characters not allowed' :
                        '%field has characters not allowed';
                }
                break;
            case 'regexp':
                if (preg_match($compareValue, (string)$r) == $negation) {
                    $fail = true;
                    $genMsg = $negation ? '%field does match exp' : '%field does not match exp';
                }
                break;
            case 'email':
                if (filter_var($r, FILTER_VALIDATE_EMAIL) == $negation) {
                    $fail = true;
                    $genMsg = $negation ? '%field is an email' : '%field is not an email';
                }
                break;
            case 'url':
                if (filter_var($r, FILTER_VALIDATE_URL) == $negation) {
                    $fail = true;
                    $genMsg = $negation ? '%field is an url' : '%field is not an url';
                }
                break;
            case 'domain':
                if (filter_var($r, FILTER_VALIDATE_DOMAIN) == $negation) {
                    $fail = true;
                    $genMsg = $negation ? '%field is a domain' : '%field is not a domain';
                }
                break;
            case 'minlen':
                $l = is_array($r) ? count($r) : strlen((string)$r);
                if (($l < $compareValue) !== $negation) {
                    $fail = true;
                    $genMsg = $negation ? '%field size is not less than %comp' : '%field size is less than %comp';
                }
                break;
            case 'maxlen':
                $l = is_array($r) ? count($r) : strlen((string)$r);
                if (($l > $compareValue) !== $negation) {
                    $fail = true;
                    $genMsg = $negation ? '%field size is not great than %comp' : '%field size is great than %comp';
                }
                break;
            case 'betweenlen':
                $rl = is_array($r) ? count($r) : strlen((string)$r);
                if (($rl < $compareValue[0] || $rl > $compareValue[1]) !== $negation) {
                    $fail = true;
                    $genMsg = $negation ? '%field size is between %first and %second' : '%field size is not between %first and %second';
                }
                break;
            case 'exist':
                if (isset($r) === $negation) { // file uses a different method
                    $fail = true;
                    $genMsg = $negation ? '%field does exist' : '%field does not exist';
                }
                break;
            case 'missing':
            case 'notexist':
                if (isset($r) !== $negation) { // file uses a different method
                    $fail = true;
                    $genMsg = $negation ? '%field does not exist' : '%field exists';
                }
                break;
            case 'req':
            case 'required':
                if ((!$r) !== $negation) {
                    $fail = true;
                    $genMsg = $negation ? '%field is not required' : '%field is required';
                }
                break;
            case 'eq':
            case '==':
                if (is_array($compareValue)) {
                    /** @noinspection TypeUnsafeArraySearchInspection */
                    if (in_array($r, $compareValue) === $negation) {
                        $fail = true;
                        $genMsg = $negation ? '%field is equals than %comp' : '%field is not equals than %comp';
                    }
                } /** @noinspection TypeUnsafeComparisonInspection */ elseif (($r == $compareValue) === $negation) {
                    $fail = true;
                    $genMsg = $negation ? '%field is equals than %comp' : '%field is not equals than %comp';
                }
                break;
            case 'ne':
            case '!=':
            case '<>':
                if (is_array($compareValue)) {
                    if (in_array($r, $compareValue) !== $negation) {
                        $fail = true;
                        $genMsg = $negation ? '%field is not in %comp' : '%field is in %comp';
                    }
                } /** @noinspection TypeUnsafeComparisonInspection */ elseif (($r == $compareValue) != $negation) {
                    $fail = true;
                    $genMsg = $negation ? '%field is not equals than %comp' : '%field is equals than %comp';
                }
                break;
            case 'null':
                if (($r !== null) !== $negation) {
                    $fail = true;
                    $genMsg = $negation ? '%field is null' : '%field is not null';
                }
                break;
            case 'empty':
                if (empty($r) === $negation) {
                    $fail = true;
                    $genMsg = $negation ? '%field is empty' : '%field is not empty';
                }
                break;
            case 'lt':
                if (($r >= $compareValue) !== $negation) {
                    $fail = true;
                    $genMsg = $negation ?
                        '%field is not great or equal than %comp' :
                        '%field is great or equal than %comp';
                }
                break;
            case 'lte':
                if (($r > $compareValue) !== $negation) {
                    $fail = true;
                    $genMsg = $negation ?
                        '%field is not great than %comp' :
                        '%field is great than %comp';
                }
                break;
            case 'gt':
                if (($r <= $compareValue) !== $negation) {
                    $fail = true;
                    $genMsg = $negation ?
                        '%field is not less or equal than %comp' :
                        '%field is less or equal than %comp';
                }
                break;
            case 'gte':
                if (($r < $compareValue) !== $negation) {
                    $fail = true;
                    $genMsg = $negation ?
                        '%field is not less than %comp' :
                        '%field is less than %comp';
                }
                break;
            case 'between':
                $rl = is_array($r) ? count($r) : $r;
                if (!isset($compareValue[0], $compareValue[1])) {
                    $fail = true;
                    $genMsg = '%field (between) lacks conditions';
                } else if (($rl < $compareValue[0] || $rl > $compareValue[1]) !== $negation) {
                    $fail = true;
                    $genMsg = '%field is not between ' . $compareValue[0] . " and " . $compareValue[1];
                }
                break;
            case 'true':
                if (($r === true || $r === 1 || $r === '1') === $negation) {
                    $fail = true;
                    $genMsg = $negation ? '%field is  true' : '%field is not true';
                }
                break;
            case 'false':
                if (($r === false || $r === 0 || $r === '0') === $negation) {
                    $fail = true;
                    $genMsg = $negation ? '%field is false' : '%field is not false';
                }
                break;
            case 'array':
                if (is_array($r) === $negation) {
                    $fail = true;
                    $genMsg = $negation ? '%field is an array' : '%field is not an array';
                }
                break;
            case 'int':
                if (is_int($r) === $negation) {
                    $fail = true;
                    $genMsg = $negation ? '%field is an integer' : '%field is not an integer';
                }
                break;
            case 'string':
                if (is_string($r) === $negation) {
                    $fail = true;
                    $genMsg = $negation ? '%field is a string' : '%field is not a string';
                }
                break;
            case 'float':
                if (is_float($r) === $negation) {
                    $fail = true;
                    $genMsg = $negation ? '%field is a float' : '%field is not a float';
                }
                break;
            case 'object':
                if (is_object($r) === $negation) {
                    $fail = true;
                    $genMsg = $negation ? '%field is an object' : '%field is not an object';
                }
                break;
            case 'in':
                if (!is_array($compareValue)) {
                    $fail = true;
                    $genMsg = '%field has no values to compare';
                }
                if (in_array($r, $compareValue) === $negation) {
                    $fail = true;
                    $genMsg = $negation ? '%field is in list' : '%field is not in list';
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
    public function first(): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        $this->currentArray = reset($this->currentArray);
        return $this;
    }

    /**
     * It returns the last element of an array.
     * @return $this
     */
    public function last(): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        $this->currentArray = end($this->currentArray);
        return $this;
    }

    /**
     * It returns the n-position of an array.
     * @param $index
     * @return $this
     */
    public function nPos($index): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        if (!array_key_exists($index, $this->currentArray)) {
            throw new RuntimeException("nPos: index [$index] does not exist");
        }
        $this->currentArray = $this->currentArray[$index];
        return $this;
    }

    /**
     * It is used internally to update the link between array and currentarray.<br>
     * It should be called when:<br>
     * a) the array is returned
     * b) we change the navigation
     * @return void
     */
    protected function setCurrentArray(): void
    {
        if ($this->curNav === null) {
            $this->array = $this->currentArray;
            return;
        }
        $c = count($this->curNav);
        switch ($c) {
            case 1:
                $this->array[$this->curNav[0]] = $this->currentArray;
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
     * <b>Example:</b><br/>
     * ```php
     * $this->reduce(['col1'=>'sum','col2'=>'avg','col3'=>'min','col4'=>'max']);
     * $this->reduce(function($row,$index,$prev) { return ['col1'=>$row['col1']+$prev['col1]];  });
     * ```
     * @param array|callable $functionAggregation An associative array where the index is the column and the value
     *                                            is the function of aggregation<br/>
     *                                            A function using the syntax: function ($row,$index,$prev) where $prev
     *                                            is the accumulator value
     * @return $this
     */
    public function reduce($functionAggregation): ArrayOne
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
                } else if (is_array($row)) {
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
        $this->currentArray = $initial;
        return $this;
    }

    /**
     * It converts the index into a column, and converts the array into an indexed array<br/>
     * <b>Example:</b><br/>
     * ```php
     * $this->indexToCol('colnew'); // ['a'=>['col1'=>'b','col2'=>'c']] => [['colnew'=>'a','col1'=>'b','col2'=>'c']
     * ```
     * @param mixed $newColumn the name of the new column
     * @return $this
     */
    public function indexToCol($newColumn): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        foreach ($this->currentArray as $index => &$row) {
            $row[$newColumn] = $index;
        }
        unset($row);
        $this->currentArray = array_values($this->currentArray);
        return $this;
    }

    /**
     * it converts a column into an index<br/>
     * <b>Example:</b><br/>
     * ```php
     * $this->indexToField('colold'); //  [['colold'=>'a','col1'=>'b','col2'=>'c'] => ['a'=>['col1'=>'b','col2'=>'c']]
     * ```
     * @param mixed $oldColumn the old column. This column will be converted into an index
     * @return $this
     */
    public function columnToIndex($oldColumn): ArrayOne
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
        $this->currentArray = $result;
        return $this;
    }

    /**
     * It groups one column and return its column grouped and values aggregated<br/>
     * <b>Example:</b><br/>
     * ```php
     * // group in the same column using a predefined function:
     * $this->group('type',['c1'=>'sum','price'=>'sum']); // ['type1'=>['c1'=>20,'price'=>30]]
     * // group in a different column using a predefined function:
     * $this->group('type',['newcol'=>'sum(amount)','price'=>'sum(price)']);
     * // group using an indexed index:
     * $this->group('type',['c1'=>'sum','pri'=>'sum','grp'=>'group'],false); // [['c1'=>20,'pri'=>30,'grp'=>'type1']]
     * // group using a function:
     * $this->group('type',['c1'=>function($cumulate,$row) { return $cumulate+$row['c1'];}]);
     * // group using two functions, one per every row and the other at the end:
     * $this->group('type',['c1'=>[
     *              function($cumulate,$row) { return $cumulate+$row['c1'];},
     *              function($cumulate,$numrows) { return $cumulate/$numRows;}]); // obtain the average of c1
     * ```
     * @param mixed $columnToGroup the column to group.
     * @param array $funcAggreg    An associative array ['col-to-aggregate'=>'aggregation']<br/>
     *                             or ['new-col'=>'aggregation(col-to-agregate)']<br/>
     *                             or ['col-to-aggr'=>function($cumulate,$row) {}]<br/>
     *                             or ['col-to-aggr'=>[function($cumulate,$row){},function($cumulate,$numRows){}]<br/>
     *                             <b>stack</b>: It stack the rows grouped by the column<br/>
     *                             <b>count</b>: Count<br/>
     *                             <b>avg</b>: Average<br/>
     *                             <b>min</b>: Minimum<br/>
     *                             <b>max</b>: Maximum<br/>
     *                             <b>sum</b>: Sum<br/>
     *                             <b>first</b>: First<br/>
     *                             <b>last</b>: last<br/>
     *                             <b>group</b>: The grouped value<br/>
     *                             <b>function:$cumulate</b>: Is where the value will be accumulated,
     *                             initially is null<br/>
     *                             <b>function:$row</b>: The current value of the row<br/>
     * @param bool  $useGroupIndex (def true), if true, then the result will use the grouped value as index<br>
     *                             if false, then the result will return the values as an indexed array.
     * @return $this
     */
    public function group($columnToGroup, array $funcAggreg, bool $useGroupIndex = true): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        $groups = [];
        $preFunction = [];
        foreach ($funcAggreg as $colName => $fun) {
            if (is_string($fun)) {
                $fnPart = explode('(', rtrim($fun, ')'), 2);
                if (count($fnPart) === 2) { // col1=>sum(col2);
                    [$fun, $colOld] = $fnPart;
                } else { // col1=>sum
                    $colOld = $colName;
                }
            } else {
                $colOld = $colName;
            }
            $preFunction[] = [$colName, $colOld, $fun];
        }
        foreach ($this->currentArray as $row) {
            if (array_key_exists($row[$columnToGroup], $groups)) {
                $initial = $groups[$row[$columnToGroup]];
                $initial['__count']++;
            } else {
                $initial = ['__count' => 1];
            }
            foreach ($preFunction as $pf) {
                [$colName, $colOld, $fun] = $pf;
                if (is_callable($fun) && !is_string($fun)) {
                    $initial[$colName] = $fun($initial[$colName] ?? null, $row);
                } else if(is_array($fun)) {
                    $initial[$colName] = $fun[0]($initial[$colName] ?? null, $row);
                } else {
                    switch ($fun) {
                        case 'stack':
                            $initial[$colName][] = $row;
                            break;
                        case 'first':
                            if (!array_key_exists($colOld, $initial)) {
                                $initial[$colName] = $row[$colOld];
                            }
                            break;
                        case 'group':
                            $initial[$colName] = $row[$columnToGroup];
                            break;
                        case 'last':
                            $initial[$colName] = $row[$colOld];
                            break;
                        case 'count':
                            $preFunction[] = [$colName, $colOld, $fun];
                            break;
                        case 'avg':
                        case 'sum':
                            $initial[$colName] = $row[$colOld] + ($initial[$colName] ?? null);
                            break;
                        case 'min':
                            $initial[$colName] = min($row[$colOld], $initial[$colName] ?? PHP_INT_MAX);
                            break;
                        case 'max':
                            $initial[$colName] = max($row[$colOld], $initial[$colName] ?? null);
                            break;
                        default:
                            $initial[$colName] = "error:function [$fun] not defined!!";
                    }
                } // callable
            }
            $groups[$row[$columnToGroup]] = $initial;
        }
        foreach ($groups as $k => $v) {
            foreach ($preFunction as $pf) {
                /** @noinspection PhpUnusedLocalVariableInspection */
                [$colName, $colOld, $fun] = $pf;
                if(is_array($fun)) {
                    $groups[$k][$colName]= $fun[1]($groups[$k][$colName], $groups[$k]['__count']);
                } else {
                    switch ($fun) {
                        case 'avg':
                            $groups[$k][$colName] /= $groups[$k]['__count'];
                            break;
                        case 'count':
                            $groups[$k][$colName] = $groups[$k]['__count'];
                            break;
                    }
                }
            }
            unset($groups[$k]['__count']);
        }
        $this->currentArray = $useGroupIndex ? $groups : array_values($groups);
        return $this;
    }

    /**
     * Sort an array<br/>
     * <b>Example:</b><br/>
     * ```php
     * $this->sort('payment','desc'); // sort an array using the column paypent descending.
     * ```
     * @param mixed  $column    if column is null, then it sorts the row (instead of a column of the row)
     * @param string $direction =['asc','desc'][$i]  ascending or descending.
     * @return $this
     */
    public function sort($column, string $direction = 'asc'): ArrayOne
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
        return $this;
    }

    /**
     * This function removes duplicates of a table.<br/>
     * <b>Example:</b><br/>
     * ```php
     * $this->removeDuplicate('col');
     * ```
     * @param mixed $colName the column to compare if the rows are duplicated.
     * @return $this
     */
    public function removeDuplicate($colName): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        $exist = [];
        foreach ($this->currentArray as $numrow => $row) {
            if (isset($row[$colName]) && in_array($row[$colName], $exist, true)) {
                // duplicated.
                unset($this->currentArray[$numrow]);
            } else {
                $exist[] = $row[$colName] ?? null;
            }
        }
        return $this;
    }

    /**
     * It removes the row with the id $rowId. If the row does not exist, then it does nothing
     * <b>Example:</b><br/>
     * ```php
     * $this->removeRow(20);
     * ```
     * @param mixed $rowId    The id of the row to delete
     * @param bool  $renumber if true then it renumber the list<br/>
     *                        ex: if 1 is deleted then $renumber=true: [0=>0,1=>1,2=>2] =>  [0=>0,1=>2]<br/>
     *                        ex: if 1 is deleted then $renumber=false: [0=>0,1=>1,2=>2] =>  [0=>0,2=>2]<br/>
     * @return $this
     */
    public function removeRow($rowId, bool $renumber = false): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        unset($this->currentArray[$rowId]);
        if ($renumber) {
            $this->currentArray = array_values($this->currentArray);
        }
        return $this;
    }

    /**
     * It removes the first row or rows. Numeric index could be renumbered.
     * <b>Example:</b><br/>
     * ```php
     * $this->removeFirstRow(); // remove the first row
     * $this->removeFirstRow(3); // remove the first 3 rows
     * ```
     * @param int  $numberOfRows The number of rows to delete, the default is 1 (the first row)
     * @param bool $renumber     if true then it renumber the list<br/>
     *                           ex: if 1 is deleted then $renumber=true: [0=>0,1=>1,'x'=>2] =>  [0=>0,1=>2]<br/>
     *                           ex: if 1 is deleted then $renumber=false: [0=>0,1=>1,2=>2] =>  [0=>0,2=>2]<br/>
     * @return $this
     */
    public function removeFirstRow(int $numberOfRows = 1, bool $renumber = false): ArrayOne
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
        return $this;
    }

    /**
     * It removes the last row or rows
     * <b>Example:</b><br/>
     * ```php
     * $this->removeLastRow(3);
     * ```
     * @param int  $numberOfRows the number of rows to delete
     * @param bool $renumber     if true then it renumber the list (since we are deleting the last value then
     *                           usually we don't need it<br/>
     *                           ex: if 1 is deleted then $renumber=true: [0=>0,1=>1,2=>2] =>  [0=>0,1=>2]<br/>
     *                           ex: if 1 is deleted then $renumber=false: [0=>0,1=>1,2=>2] =>  [0=>0,2=>2]<br/>
     * @return $this
     */
    public function removeLastRow(int $numberOfRows = 1, bool $renumber = false): ArrayOne
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
        return $this;
    }

    /**
     * It generates a validate-array using an example array. It could be used by validation() and filter()<br/>
     * <b>Example:</b><br/>
     * ```php
     * $this->makeValidateArrayByExample(['1','a','f'=>3.3]); // ['int','string','f'=>'float'];
     * ```
     * @param array $array
     * @return array
     */
    public static function makeValidateArrayByExample(array $array): array
    {
        $result = [];
        self::makeValidateArrayByExampleRec($array, $result);
        return $result;
    }

    /**
     * It is a recursive function used by makeValidateArrayByExample()
     * @param $array
     * @param $result
     * @return void
     */
    protected static function makeValidateArrayByExampleRec($array, &$result): void
    {
        $v = null;
        $up = false;
        if (!is_array($array)) {
            $array = [$array];
            $up = true;
        }
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
                        self::makeValidateArrayByExampleRec($v2, $result[$rowid][$k2]);
                        /** @noinspection PhpConditionAlreadyCheckedInspection */
                        if (is_array($result[$rowid][$k2])) {
                            $keys = array_keys($result[$rowid][$k2]);
                            if ($keyPrevious !== null && $keys !== $keyPrevious) {
                                $table = false;
                            }
                            $keyPrevious = $keys;
                        }
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
        if ($up) {
            $result = $result[0];
        }
    }

    /**
     * It creates an associative array that could be used to be used by setRequest()<br/>
     * <b>Example:</b><br/>
     * ```php
     * $this->makeRequestArrayByExample(['a'=1,'b'=>2]); // ['a'='post','b'=>'post'];
     * ```
     *
     * @param array  $array An associative array with some values.
     * @param string $type  =['get','post','request','header','cookie'][$i] The default type
     * @return array
     */
    public static function makeRequestArrayByExample(array $array, string $type = 'post'): array
    {
        //$result = [];
        return self::makeWalk($array, static function() use ($type) {
            return $type;
        }, true);
    }

    /**
     * We call a method for every element of the array recursively.<br/>
     * <b>Example:</b><br/>
     * ```php
     * ArrayOne::makeWalk(['a'=>'hello','b'=>['c'=>'world'],function($row,$id) { return strotupper($row);});
     * ```
     * @param array    $array         Our initial array
     * @param callable $method        the method to call, example: function($row,$index) { return $row; }
     * @param bool     $identifyTable (def: false) if we want the array identify inside arrays as table
     * @return array
     */
    public static function makeWalk(array $array, callable $method, bool $identifyTable = false): array
    {
        $result = [];
        self::makeWalkRec($array, $result, $method, $identifyTable);
        return $result;
    }

    protected static function makeWalkRec($array, &$result, callable $method, bool $identifyTable): void
    {
        $lower = false;
        if (!is_array($array)) {
            $lower = true;
            $array = [$array];
        }
        foreach ($array as $rowid => $row) {
            if (is_array($row)) {
                $table = array_key_exists(0, $row) && $identifyTable;
                foreach ($row as $k2 => $v2) {
                    $result[$rowid][$k2] = [];
                    self::makeWalkRec($v2, $result[$rowid][$k2], $method, $identifyTable);
                }
                // [[a:1,b:2],[a:2,b:2]] is table
                // ['a','b','c'] is not table
                // ['x':[a:1,b:2],'y':[a:2,b:2]] is not table
                if ($table) {
                    $result[$rowid] = [$result[$rowid][0]];
                }
            }
            if (!is_array($row)) {
                $result[$rowid] = $method($row, $rowid);
            }
        }
        if ($lower) {
            $result = $result[0];
        }
    }

    /**
     * Validate the current array using a comparison table<br/>
     * <b>Example:</b><br/>
     * ```php
     * $this->validate([
     *          'id'=>'int',
     *          'table'=>[['col1'=>'int','col2'=>'string']],   // note the double [[ ]] to indicate a table of values
     *          'list'=>[['int']]
     *     ]);
     * ```
     * @param array $comparisonTable <br/>
     *                               <b>not-valid-</b> : negates an validation, example "notint"<br/>
     *                               <b>nullable</b> :the value can be a null. If the value is null, then it ignores
     *                               other validations<br/>
     *                               <b>f:-function-</b> :call a custom function defined in the service class<br/>
     *                               <b>contain like</b> :if a text is contained in<br/>
     *                               <b>alpha</b> :if the value is alphabetic<br/>
     *                               <b>alphanumunder</b> :if the value is alphanumeric or undercase<br/>
     *                               <b>alphanum</b> :if the value is alphanumeric<br/>
     *                               <b>text</b> :if the value is a text<br/>
     *                               <b>regexp</b> :if the value match a regular expression<br/>
     *                               <b>email</b> :if the value is an email<br/>
     *                               <b>url</b> :if the value is an url<br/>
     *                               <b>domain</b> :if the value is a domain<br/>
     *                               <b>minlen</b> :the value must have a minimum lenght<br/>
     *                               <b>maxlen</b> :the value must have a maximum lenght<br/>
     *                               <b>betweenlen</b> :if the value has a size between<br/>
     *                               <b>exist</b> :if the value exists<br/>
     *                               <b>missing notexist</b> :if the value not exist<br/>
     *                               <b>req required</b> :if the value is required<br/>
     *                               <b>eq ==</b> :if the value is equals to<br/>
     *                               <b>ne != <></b> :if the value is not equals to<br/>
     *                               <b>null</b> :if the value is null<br/>
     *                               <b>empty</b> :if the value is empty<br/>
     *                               <b>lt</b> :if the value is less than<br/>
     *                               <b>lte</b> :if the value is less or equals than<br/>
     *                               <b>gt</b> :if the value is great than<br/>
     *                               <b>gte</b> :if the value is great or equals than<br/>
     *                               <b>between</b> :if the value is between<br/>
     *                               <b>true</b> :if the value is true<br/>
     *                               <b>false</b> :if the value is false<br/>
     *                               <b>array</b> :if the value is an array<br/>
     *                               <b>int</b> :if the value is an integer<br/>
     *                               <b>string</b> :if the value is a string<br/>
     *                               <b>float</b> :if the value is a float<br/>
     *                               <b>object</b> :if the value is an object<br/>
     * @param bool  $extraFieldError if true and the current array has more values than comparison table, then
     *                               it returns an error.
     * @return $this
     */
    public function validate(array $comparisonTable, bool $extraFieldError = false): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        $this->currentArray = $this->validateRec($comparisonTable, $this->currentArray, $extraFieldError);
        return $this;
    }

    protected function validateRec($comparisonTable, $values, $extraFieldError = false, $type = 'object'): array
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

    protected function validateRecInside($comparisonTable, $values, $extraFieldError = false, $rowId = null)
    {
        $final = [];
        $up = false;
        if (!is_array($values)) {
            $values = [$values];
            $up = true;
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
        if ($up) {
            $final = $final[0];
        }
        return $final;
    }

    /**
     * It masks the current array using another array.<br/>
     * Masking deletes all field that are not part of our mask<br/>
     * The mask is smart to recognize a table, so it could mask multiples values by only specifying the first row.<br/>
     * <b>Example:</b><br/>
     * ```php
     * $array=['a'=>1,'b'=>2,'c'=>3,'items'=>[[a1'=>1,'a2'=>2,'a3'=3],[a1'=>1,'a2'=>2,'a3'=3]];
     * $mask=['a'=>1,'items'=>[[a1'=>1]]; // [[a1'=>1]] masks an entire table
     * $this->mask($mask); // $array=['a'=>1,'items'=>[[a1'=>1],[a1'=>1]];
     * ```
     * @param array $arrayMask An associative array with the mask. The mask could contain any value.
     * @return ArrayOne
     */
    public function mask(array $arrayMask): ArrayOne
    {
        if ($this->currentArray === null) {
            return $this;
        }
        $this->maskRec($arrayMask, $this->currentArray);
        return $this;
    }

    /**
     * It is a recursive function used by mask()
     * @param array $arrayMask
     * @param       $current
     * @return void
     */
    protected function maskRec(array $arrayMask, &$current): void
    {
        $up = false;
        if (!is_array($current)) {
            $current = [$current];
            $up = true;
        }
        foreach ($current as $k => $v) {
            if (!array_key_exists($k, $arrayMask)) {
                unset($current[$k]);
            } else if (is_array($v) && is_array($arrayMask[$k])) {
                $am = $arrayMask[$k];
                if (array_key_exists(0, $am) && count($am) === 1) {
                    foreach ($v as $k2 => $v2) {
                        $this->maskRec($am[0], $current[$k][$k2]);
                        if ($current[$k][$k2] === null) {
                            unset($current[$k][$k2]);
                        }
                    }
                }
            }
        }
        if ($up) {
            if (array_key_exists(0, $current)) {
                $current = $current[0];
            } else if ($current === []) {
                $current = null;
            }
        }
    }


    //region interface ArrayAccess
    public function offsetSet($offset, $value): void {
        if (is_null($offset)) {
            $this->array[] = $value;
        } else {
            $this->array[$offset] = $value;
        }
    }

    public function offsetExists($offset): bool {
        return isset($this->array[$offset]);
    }

    public function offsetUnset($offset): void {
        unset($this->array[$offset]);
    }

    /**
     * It gets a value of the array<br>
     * <b>Example:</b><br>
     * ```php
     * $this->offsetGet(1); // or $this[1];
     * ```
     * @param mixed $offset
     * @return mixed|null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset) {
        return $this->array[$offset] ?? null;
    }
    //endregion
}
