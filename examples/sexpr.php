<?php
// wooh! s-expressions!
namespace igorw\smaug;

require __DIR__ . '/../src/parser.php';
$p = new \ArrayObject();
$p['expr'] = delay_parser(function () use ($p) { return alt($p['symbol'], $p['list']); });
$p['symbol'] = regexp('\\w+');
$p['list'] = delay_parser(function () use ($p) { return alt(red(string('()'), function () { return []; }), red(seq(string('('), $p['members'], string(')')), function ($l, $m, $r) { return $m; })); });
$p['members'] = delay_parser(function () use ($p) { return alt(red($p['expr'], function ($e) { return [$e]; }), red(seq($p['expr'], string(' '), $p['members']), function ($e, $_, $m) { return [$e, $m]; })); });
var_dump(iterator_to_array(run_parser($p['expr'], 'foo')));
var_dump(iterator_to_array(run_parser($p['expr'], '()')));
var_dump(iterator_to_array(run_parser($p['expr'], '(foo)')));
var_dump(iterator_to_array(run_parser($p['expr'], '(foo bar)')));
var_dump(iterator_to_array(run_parser($p['expr'], '(foo bar (baz (qux quux the great)))')));
