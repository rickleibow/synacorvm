<?php

namespace igorw\synacorvm;

const MACHINE_HALT = 0;
const MACHINE_CONTINUE = 0;

class Machine
{
    private $memory = [];
    private $sp = -1;
    private $instructions;
    private $ip = 0;

    function __construct(array $instructions)
    {
        $this->instructions = $instructions;
    }

    function execute()
    {
        while (null !== ($instruction = $this->next())) {
            $status = $this->process_instruction($instruction);

            if (MACHINE_HALT === $status) {
                break;
            }
        }
    }

    private function next()
    {
        if (!isset($this->instructions[$this->ip])) {
            return null;
        }

        return $this->get_register_value($this->instructions[$this->ip++]);
    }

    private function process_instruction($instruction)
    {
        switch ($instruction) {
            case 0:
                // halt
                return MACHINE_HALT;
                break;
            case 1:
                // set a b
                $a = $this->next();
                $b = $this->next();
                $this->set($a, $b);
                break;
            case 9:
                // add a b c
                $a = $this->next();
                $b = $this->next();
                $c = $this->next();
                $this->set($a, ($b + $c) % 32768);
                var_dump('set');
                break;
            case 19:
                // out a
                $a = $this->next();
                echo chr($a);
                var_dump('out');
                var_dump($a);
                break;
        }
    }

    private function push($value)
    {
        $this->memory[$this->sp++] = $value;
    }

    private function pop()
    {
        return $this->memory[--$this->sp];
    }

    private function set($register, $value)
    {
        $this->memory[$register] = $value;
    }

    private function get($register, $value)
    {
        return $this->memory[$register];
    }

    private function get_register_value($n)
    {
        var_dump("... $n");
        if ($n >= 0 && $n <= 32767) {
            return $n;
        }

        if ($n >= 32768 && $n <= 32775) {
            return $this->memory[$i + 32768];
        }

        throw new \RuntimeException("Invalid value $n");
    }
}

function parse($code)
{
    return array_map('ord', str_split($code));
}

function execute($code)
{
    $vm = new Machine(parse($code));
    return $vm->execute();
}

var_dump(execute(implode('', array_map('chr', [9,32768,32769,4,19,32768]))));
// var_dump(execute(file_get_contents('challenge.bin')));
