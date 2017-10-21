<?php
// JSON
namespace igorw\smaug;

require __DIR__ . '/../src/parser.php';
$p = new \ArrayObject();
$p['value'] = delay_parser(function () use ($p) { return red(alt($p['string'], $p['number'], $p['object'], $p['array'], $p['true'], $p['false'], $p['null']), function ($v) { return ['value', func_get_args()]; }); });
$p['object'] = delay_parser(function () use ($p) { return alt(red(string('{}'), function () { return ['object', []]; }), red(seq(string('{'), $p['members'], string('}')), function ($l, $v, $r) { return ['object', $v]; })); });
$p['members'] = delay_parser(
    function () use ($p) {
        return alt(red($p['pair'], function ($v) { return ['members', $v]; }), red(seq($p['pair'], string(','), $p['members']), function ($p, $v) { return ['members', $p, $v]; })); });
$p['pair'] = delay_parser(function () use ($p) { return red(seq($p['string'], string(':'), $p['value']), function ($k, $_, $v) { return ['pair', $k, $v]; }); });
$p['array'] = delay_parser(function () use ($p) { return alt(red(string('[]'), function () { return ['array', []]; }), red(seq(string('['), $p['elements'], string(']')), function ($l, $v, $r) { return ['array', $v]; })); });
$p['elements'] = delay_parser(function () use ($p) { return alt(red($p['value'], function ($v) { return ['elements', $v]; }), red(seq($p['value'], string(','), $p['elements']), function ($v, $e) { return ['elements', $v, $e]; })); });
$p['string'] = delay_parser(function () use ($p) { return alt(red(string('""'), function () { return ['string', '']; }), red(seq(string('"'), $p['chars'], string('"')), function ($l, $v, $r) { return ['string', $v]; })); });
$p['chars'] = red(regexp('[^\\"\\/\\b\\f\\n\\r\\t]+'), function ($v) { return ['chars', $v]; });
$p['number'] = red(regexp('[0-9]+'), function ($v) { return ['number', $v]; });
$p['true'] = red(string('true'), function ($v) { return ['true', $v]; });
$p['false'] = red(string('false'), function ($v) { return ['false', $v]; });
$p['null'] = red(string('null'), function ($v) { return ['null', $v]; });
// var_dump(iterator_to_array(run_parser($p['number'], '1')));
// var_dump(iterator_to_array(run_parser($p['array'], '[]')));
// var_dump(iterator_to_array(run_parser($p['object'], '{}')));
var_dump(iterator_to_array(run_parser($p['value'], '[]')));
var_dump(iterator_to_array(run_parser($p['value'], '{}')));
var_dump(iterator_to_array(run_parser($p['value'], '"foo"')));
var_dump(iterator_to_array(run_parser($p['value'], '{"foo":"bar"}')));
var_dump(iterator_to_array(run_parser($p['value'], '{"foo":["bar","baz",42,{}]}')));
