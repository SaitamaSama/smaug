<?php
namespace igorw\smaug;

class Success
{
    public $value;
    public $rest;
    function __construct($value, $rest)
    {
        $this->value = $value;
        $this->rest = $rest;
    }
}
class Failure
{
    public $rest;
    function __construct($rest)
    {
        $this->rest = $rest;
    }
}
function success($value, $rest)
{
    return new Success($value, $rest);
}
function failure($rest)
{
    return new Failure($rest);
}
// ---
function memo(callable $fn)
{
    $alist = [];
    return function () use ($alist, $fn) {
        $args = func_get_args();
        foreach ($alist as list($a, $result)) {
            if ($a == $args) {
                return $result;
            }
        }
        $result = call_user_func_array($fn, $args);
        array_unshift($alist, [$args, $result]);
        return $result;
    };
}
function memofn(array $args, callable $fn)
{
    static $memos = [];
    $id = spl_object_hash($fn);
    $memo = isset($memos[$id]) ? $memos[$id] : memo($fn);
    return call_user_func_array($memo, $args);
}
// ---
function run_parser($parser, $str)
{
    $tramp = new Trampoline();
    $results = [];
    $parser($str, $tramp, function ($result) use(&$results) {
        if ($result instanceof Success && $result->rest === '') {
            $results[] = $result;
        }
    });
    $out = function () use($tramp, &$results) {
        do {
            $tramp->step();
            foreach ($results as $result) {
                (yield $result);
            }
            $results = [];
        } while ($tramp->has_next());
    };
    return $out();
}
// crappy linear time lookup table
class Table
{
    public $data = [];
    function put($key, $value)
    {
        $this->remove($key);
        $this->data[] = [$key, $value];
    }
    function lookup($key)
    {
        foreach ($this->data as list($k, $v)) {
            if ($k == $key) {
                return $v;
            }
        }
        return null;
    }
    function remove($key)
    {
        foreach ($this->data as $i => list($k, $v)) {
            if ($k == $key) {
                unset($this->data[$i]);
            }
        }
    }
}
class Entry
{
    public $continuations = [];
    public $results = [];
    function push_continuation($cont)
    {
        array_unshift($this->continuations, $cont);
    }
    function push_result($result)
    {
        array_unshift($this->results, $result);
    }
    function result_subsumed($result)
    {
        return in_array($result, $this->results);
    }
    function is_empty()
    {
        return $this->continuations === [] && $this->results === [];
    }
}
class Trampoline
{
    public $stack;
    public $table;
    function __construct(\SplStack $stack = null, Table $table = null)
    {
        $this->stack = $stack ?: new \SplStack();
        $this->table = $table ?: new Table();
    }
    function has_next()
    {
        return count($this->stack) > 0;
    }
    function step()
    {
        if ($this->has_next()) {
            list($fn, $args) = $this->stack->pop();
            call_user_func_array($fn, $args);
        }
    }
    function push_stack()
    {
        $args = func_get_args();
        $fn = array_shift($args);
        $this->stack->push([$fn, $args]);
    }
    function push($fn, $str, $cont)
    {
        $entry = $this->table_ref($fn, $str);
        if ($entry->is_empty()) {
            $entry->push_continuation($cont);
            // push the parser on the stack
            $this->push_stack($fn, $str, $this, function ($result) use ($entry, $cont) {
                if (!$entry->result_subsumed($result)) {
                    $entry->push_result($result);
                    foreach ($entry->continuations as $cont) {
                        $cont($result);
                    }
                }
            });
        } else {
            $entry->push_continuation($cont);
            foreach ($entry->results as $result) {
                $cont($result);
            }
        }
    }
    private function table_ref($fn, $str)
    {
        $memo = $this->table->lookup($fn);
        if ($memo) {
            $entry = $memo->lookup($str);
            if ($entry) {
                // parser has been called with str before
                return $entry;
            }
            // first time parser has been called with str
            $entry = new Entry();
            $memo->put($str, $entry);
            // this happens implicitly:
            // $this->table->put($fn, $memo);
            return $entry;
        }
        // first time parser has been called
        $entry = new Entry();
        $memo = new Table();
        $memo->put($str, $entry);
        $this->table->put($fn, $memo);
        return $entry;
    }
}
function succeed($val)
{
    static $fn;
    $fn = $fn ?: function ($val) { return function ($str, $tramp, $cont) use($val) {
        $cont(success($val, $str));
    }; };
    return memofn([$val], $fn);
}
function string($match)
{
    static $fn;
    $fn = $fn ?: function ($match) { return function ($str, $tramp, $cont) use($match) {
        $len = min(strlen($str), strlen($match));
        $head = (string) substr($str, 0, $len);
        $tail = (string) substr($str, $len);
        if ($head === $match) {
            $cont(success($head, $tail));
        } else {
            $cont(failure($tail));
        }
    }; };
    return memofn([$match], $fn);
}
function regexp($pattern)
{
    static $fn;
    $fn = $fn ?: function ($pattern) { return function ($str, $tramp, $cont) use($pattern) {
        preg_match('/^' . $pattern . '/', $str, $matches);
        if (count($matches) > 0) {
            $match = $matches[0];
            $end = strlen($match);
            $len = strlen($str);
            $head = (string) substr($str, 0, $end);
            $tail = (string) substr($str, $end, $len);
            $cont(success($head, $tail));
        } else {
            $cont(failure($str));
        }
    }; };
    return memofn([$pattern], $fn);
}
function bind($p, $fn)
{
    return function ($str, $tramp, $cont) use($p, $fn) { return $p($str, $tramp, function ($result) use ($tramp, $fn, $cont) {
        if ($result instanceof Success) {
            call_user_func($fn($result->value), $result->rest, $tramp, $cont);
        } else {
            // failure
            $cont($result);
        }
    }); };
}
function seq()
{
    static $fn;
    $fn = $fn ?: function () {
        $_parsers = func_get_args();
        $seq2 = function ($b, $a) {
            return bind($a, function ($x) use($b) {
                return bind($b, function ($y) use($x) {
                    return succeed(array_merge($x, [$y])); }); }); };
        // foldl
        $acc = succeed([]);
        foreach ($_parsers as $parser) {
            $acc = $seq2($parser, $acc);
        }
        return $acc;
    };
    $parsers = func_get_args();
    return memofn($parsers, $fn);
}
function alt()
{
    static $fn;
    $fn = $fn ?: function () {
        $_parsers = func_get_args();
        return function ($str, $tramp, $cont) use ($_parsers) {
            foreach ($_parsers as $fn) {
                $tramp->push($fn, $str, $cont);
            }
        };
    };
    $parsers = func_get_args();
    return memofn($parsers, $fn);
}
// @todo find a better solution than this messy is_array check
// possibly by introducing a tuple and only multi-applying tuples
function red($p, $rfn)
{
    static $fn;
    $fn = $fn ?: function ($p, $rfn) {
        return bind($p, function ($val) use($rfn) {
            return is_array($val) ? succeed(call_user_func_array($rfn, $val)) : succeed($rfn($val)); }); };
    return memofn([$p, $rfn], $fn);
}
function delay_parser($fn)
{
    return function ($str, $tramp, $cont) use($fn) { return call_user_func($fn(), $str, $tramp, $cont); };
}
function take($n, $iter)
{
    if ($n === 0) {
        return;
    }
    foreach ($iter as $v) {
        (yield $v);
        $n--;
        if ($n === 0) {
            break;
        }
    }
}
