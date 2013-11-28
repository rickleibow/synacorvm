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

function parse_list($text)
{
    $list = trim($text);
    $list = explode("\n", $list);
    $list = array_map(function ($line) { return preg_replace('#^- #', '', $line); }, $list);

    return $list;
}

function parse_rooms($rooms_text)
{
    $regex = '#== (?P<title>.+?) ==\n(?<description>.+?)(\nThings of interest here:\n(?<items>))?\nThere (are|is) \d exits?:\n(?<exits>.+?)\nWhat do you do\?#s';

    preg_match_all($regex, $rooms_text, $matches, PREG_SET_ORDER);

    return array_map(function ($room) {
        $source = $room[0];
        $items = $room['items'] ? parse_list($room['items']) : [];
        $exits = parse_list($room['exits']);

        if ($items) {
            var_dump($items);exit;
        }

        return [
            'title'         => $room['title'],
            'description'   => $room['description'],
            'items'         => $items,
            'exits'         => $exits,
            // 'source'        => $source,
            'hash'          => sha1($source),
        ];
    }, $matches);
}

function parse_room($room_text)
{
    // dead from being eaten by grue
    // throw exception so caller can backtrack
    if ('You have been eaten by a grue.' === trim($room_text)) {
        throw new GrueException();
    }

    if ("I don't understand; try 'help' for instructions.\n\nWhat do you do?" === trim($room_text)) {
        throw new \InvalidArgumentException('Invalid command given.');
    }

    // strip out code on wall
    $room_text = preg_replace('#^(.+?)Chiseled on the wall(.+?)and keep walking.#s', '', $room_text);

    $rooms = parse_rooms($room_text);

    if (1 !== count($rooms)) {
        throw new \InvalidArgumentException(sprintf('Expected exactly one room, but got %s. Input text was: %s.', count($rooms), $room_text));
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

class GrueException extends \RuntimeException {}

require 'src/vm.php';

$stdin = fopen('php://memory', 'w+');
$stdout = fopen('php://memory', 'w+');

$memory = parse(file_get_contents('data/challenge.bin'));
$vm = new Machine($memory, $stdin, $stdout);

// boot
run_and_capture($vm, $stdout);

// go to maze
write_inputs($stdin, [
    // go over bridge and down
    'doorway',
    'north',
    'north',
    'bridge',
    'continue',
    'down',
    // fetch lantern
    'east',
    'take empty lantern',
    'west',
    // fetch can
    'west',
    'passage',
    'ladder',
    'west',
    'south',
    'north',
    'take can',
    'west',
    'ladder',
]);
run_and_capture($vm, $stdout);

// enter maze
$init_room = write_maze_move($vm, $stdin, $stdout, 'ladder');

if (2 !== $argc || !in_array($argv[1], ['north', 'east', 'south', 'west'])) {
    echo "You must provide an initial exit, and it must be one of: ";
    echo "north, east, south, west.\n";
    exit(1);
}
$init_exit = $argv[1];

// start searching maze
search_init($vm, $stdin, $stdout, $init_room, $init_exit);

function search_init($vm, $stdin, $stdout, $init_room, $init_exit)
{
    $init_vm = clone $vm;
    $trail = [];

    $init_hash = $init_room['hash'];

    $trail[] = $init_exit;
    $room = write_maze_move($vm, $stdin, $stdout, $init_exit);
    $found = search_room($vm, $stdin, $stdout, $init_exit, [$init_hash], $room, $init_vm, $trail);

    var_dump(['init', $found, $trail]);
}

function search_room(Machine $vm, $stdin, $stdout, $exit, array $bt_hashes, array $room, Machine $init_vm, array $trail)
{
    if (in_array($room['hash'], $bt_hashes)) {
        return false;
    }

    $first = true;

    foreach ($room['exits'] as $exit) {
        // backtrack
        if (!$first) {
            echo "failed, backtracking...\n\n";

            $vm = clone $init_vm;
            foreach ($trail as $t) {
                write_maze_move($vm, $stdin, $stdout, $t);
            }
        }
        $first = false;

        if (!preg_match('#^You are in a (.+?), all (.+?)\.$#', $room['description'])) {
            echo $room['hash'];
            echo "\n\n";
            echo $room['description'];
            if ($room['items']) {
                echo "\n";
                echo 'Items: '.json_encode($room['items'])."\n";
            }
            echo "\n";
            echo 'Exits: '.json_encode($room['exits'])."\n";
            echo "\n";
            echo 'Trail: '.json_encode($trail)."\n";
            echo "\n";
            echo "Trying: $exit\n";
            echo "\n";
        }

        try {
            $next_room = write_maze_move($vm, $stdin, $stdout, $exit);
        } catch (GrueException $e) {
            // backtrack
            continue;
        }

        // recur
        $found = search_room($vm, $stdin, $stdout, $exit, array_merge($bt_hashes, [$room['hash']]), $next_room, $init_vm, array_merge($trail, [$exit]));

        if ($found) {
            // var_dump(['room', $found, array_merge($trail, [$exit])]);
            return true;
        }

        // var_dump(['room', $found]);
    }

    return false;
}
