<?php

namespace igorw\synacorvm;

function format_line($index, $line)
{
    return str_pad($index, 5, ' ', STR_PAD_LEFT).":\t".implode(' ', $line)."\n";
}

require 'src/vm.php';

$types = [
    0  => ['name' => 'halt'],
    1  => ['name' => 'set',  'args' => 2],
    2  => ['name' => 'push', 'args' => 1],
    3  => ['name' => 'pop',  'args' => 1],
    4  => ['name' => 'eq',   'args' => 3],
    5  => ['name' => 'gt',   'args' => 3],
    6  => ['name' => 'jmp',  'args' => 1],
    7  => ['name' => 'jt',   'args' => 2],
    8  => ['name' => 'jf',   'args' => 2],
    9  => ['name' => 'add',  'args' => 3],
    10 => ['name' => 'mult', 'args' => 3],
    11 => ['name' => 'mod',  'args' => 3],
    12 => ['name' => 'and',  'args' => 3],
    13 => ['name' => 'or',   'args' => 3],
    14 => ['name' => 'not',  'args' => 3],
    15 => ['name' => 'rmem', 'args' => 2],
    16 => ['name' => 'wmem', 'args' => 2],
    17 => ['name' => 'call', 'args' => 1],
    18 => ['name' => 'ret'],
    19 => ['name' => 'out',  'args' => 1, 'fn' => function ($char) { return [ ($char < 255 ? json_encode(chr($char)) : $char) ]; }],
    20 => ['name' => 'in',   'args' => 1],
    21 => ['name' => 'nop'],
];

$ops = parse(file_get_contents('data/challenge.bin'));

$i = 0;
while ($i < count($ops)) {
    $index = $i;
    $op = $ops[$i++];

    if (!isset($types[$op])) {
        $line = ['data', $op];
        echo format_line($index, $line);
        continue;
    }

    $type = $types[$op];
    $num_args = isset($type['args']) ? $type['args'] : 0;

    $args = [];
    while ($num_args--) {
        $args[] = $ops[$i++];
    }

    if (isset($type['fn'])) {
        $args = call_user_func_array($type['fn'], $args);
    }

    $line = array_merge([$type['name']], $args);
    echo format_line($index, $line);
}
