<?php

namespace igorw\synacorvm;

function write_inputs($stdin, array $commands)
{
    write_input($stdin, implode("\n", $commands));
}

function write_input($stdin, $command)
{
    $start = ftell($stdin);
    fseek($stdin, 0, SEEK_END);
    fwrite($stdin, $command."\n");
    fseek($stdin, $start);
}

function clear_input($stdin)
{
    fseek($stdin, 0, SEEK_END);
}

function run_until_input_block($vm)
{
    try {
        $vm->execute();
    } catch (NoInputException $e) {
    }
}

function run_and_capture($vm, $stdout)
{
    $start = ftell($stdout);
    run_until_input_block($vm);
    fseek($stdout, $start);
    return stream_get_contents($stdout);
}

function parse_rooms($rooms_text)
{
    $regex = '#== (?P<title>.+?) ==\n(?<description>.+?)\nThere are \d exits:\n(?<exits>.+?)\nWhat do you do\?#s';

    preg_match_all($regex, $rooms_text, $matches, PREG_SET_ORDER);

    return array_map(function ($room) {
        $source = $room[0];

        $exits = trim($room['exits']);
        $exits = explode("\n", $exits);
        $exits = array_map(function ($line) { return preg_replace('#^- #', '', $line); }, $exits);

        return [
            'title'         => $room['title'],
            'description'   => $room['description'],
            'exits'         => $exits,
            // 'source'        => $source,
            'hash'          => sha1($source),
        ];
    }, $matches);
}

function parse_room($room_text)
{
    $rooms = parse_rooms($room_text);

    if (1 !== count($rooms)) {
        throw new \InvalidArgumentException(sprintf('Expected exactly one room, but got %s', count($rooms)));
    }

    return $rooms[0];
}

function write_maze_move($vm, $stdin, $stdout, $command)
{
    write_input($stdin, $command);
    $room_text = run_and_capture($vm, $stdout);
    $room = parse_room($room_text);
    return $room;
}

require 'src/vm.php';

$stdin = fopen('php://memory', 'w+');
$stdout = fopen('php://memory', 'w+');

$memory = parse(file_get_contents('data/challenge.bin'));
$vm = new Machine($memory, $stdin, $stdout);

// boot
run_and_capture($vm, $stdout);

// go to maze
write_inputs($stdin, [
    'doorway',
    'north',
    'north',
    'bridge',
    'continue',
    'down',
    'west',
    'passage',
]);
run_and_capture($vm, $stdout);

// enter maze
$init_room = write_maze_move($vm, $stdin, $stdout, 'ladder');

// start searching maze
search_init($vm, $stdin, $stdout, $init_room);

function search_init($vm, $stdin, $stdout, $init_room)
{
    $init_vm = clone $vm;
    $trail = [];

    $init_hash = $init_room['hash'];
    $init_exits = array_filter($init_room['exits'], function ($exit) { return $exit !== 'ladder'; });

    foreach ($init_exits as $exit) {
        $trail[] = $exit;
        $room = write_maze_move($vm, $stdin, $stdout, $exit);
        $found = search_room($vm, $stdin, $stdout, $exit, $init_hash, $room, $init_vm, $trail);

        // var_dump(['init', $found, $trail]);

        // backtrack
        if (!$found) {
            $vm = clone $init_vm;
            array_pop($trail);
            write_inputs($stdin, $trail);
        }
    }
}

function search_room($vm, $stdin, $stdout, $exit, $init_hash, $room, $init_vm, $trail)
{
    if (!preg_match('#^You are in a (.+?), all (.+?)\.$#', $room['description'])) {
        echo $room['description'];
        echo "\n";
        echo json_encode($trail)."\n";
        echo "\n";
    }

    foreach ($room['exits'] as $exit) {
        $trail[] = $exit;
        $room = write_maze_move($vm, $stdin, $stdout, $exit);

        if ($init_hash === $room['hash']) {
            return false;
        }

        // recur
        $found = search_room($vm, $stdin, $stdout, $exit, $init_hash, $room, $init_vm, $trail);

        // var_dump(['room', $found, $trail]);

        // backtrack
        if (!$found) {
            $vm = clone $init_vm;
            array_pop($trail);
            foreach ($trail as $t) {
                write_maze_move($vm, $stdin, $stdout, $t);
            }
        }
    }
}
