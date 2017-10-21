<?php
// mathematical expression example
// taken from: https://github.com/epsil/gll
namespace igorw\smaug;

require __DIR__ . '/../src/parser.php';
$expr = delay_parser(function () use(&$expr, &$term, &$factor, &$num) {
    return alt(red(seq($expr, string('+'), $term), function ($x, $_, $y) { return $x + $y; }), red(seq($expr, string('-'), $term), function ($x, $_, $y) { return $x - $y; }), $term);
});
$term = delay_parser(function () use(&$expr, &$term, &$factor, &$num) {
    return alt(red(seq($term, string('*'), $factor), function ($x, $_, $y) { return $x * $y; }), red(seq($term, string('/'), $factor), function ($x, $_, $y) { return $x / $y; }), $factor);
});
$factor = delay_parser(function () use(&$expr, &$term, &$factor, &$num) {
    return alt(red(seq(string('('), $expr, string(')')), function ($_, $x, $__) { return $x; }), $num);
});
$num = red(regexp('[0-9]+'), 'intval');
// var_dump(iterator_to_array(run_parser($num, '1')));
// var_dump(iterator_to_array(run_parser($num, '42')));

// var_dump(iterator_to_array(run_parser($factor, '42')));
// var_dump(iterator_to_array(run_parser($factor, '(42)')));

// var_dump(iterator_to_array(run_parser($expr, '42')));
// var_dump(iterator_to_array(run_parser($expr, '1*2+3*4')));
// var_dump(iterator_to_array(run_parser($expr, '9-(5+2)')));
