<?php

namespace igorw\synacorvm;

function write_input($stream, array $commands)
{
    $start = ftell($stream);
    fwrite($stream, implode("\n", $commands)."\n");
    fseek($stream, $start);
}

function run_until_input_block($vm)
{
    try {
        $vm->execute();
    } catch (NoInputException $e) {
    }
}

require 'src/vm.php';

$stdin = fopen('php://memory', 'w+');
$stdout = STDOUT;
// $stdout = fopen('php://memory');

$states = [];

$memory = parse(file_get_contents('data/challenge.bin'));
$vm = new Machine($memory, $stdin, $stdout);

// boot
run_until_input_block($vm);

// go to maze
write_input($stdin, [
    'doorway',
    'north',
    'north',
    'bridge',
    'continue',
    'down',
    'west',
    'passage',
    'ladder',
]);
run_until_input_block($vm);
