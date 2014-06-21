<?hh

// mathematical expression example
// taken from: https://github.com/epsil/gll

namespace igorw\gll;

require 'parser-combinator.php';

var_dump(iterator_to_array(run_parser(seq(string('foo'), string('bar')), 'foobar')));

$s = delay_parser(function () use (&$s) {
    return alt(seq($s, string('a')),
               string('a'));
});

var_dump(iterator_to_array(take(1, run_parser($s, 'a'))));
var_dump(iterator_to_array(take(1, run_parser($s, 'aa'))));
var_dump(iterator_to_array(take(2, run_parser($s, 'a'))));
var_dump(iterator_to_array(take(2, run_parser($s, 'aa'))));
var_dump(iterator_to_array(take(2, run_parser($s, 'aaa'))));
