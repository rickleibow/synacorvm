<?php

namespace igorw\synacorvm;

const MACHINE_HALT = 0;
const MACHINE_CONTINUE = 0;

class Machine
{
    private $memory = [];
    private $registers = [];
    private $stack = [];
    private $instructions;
    private $ip = 0;

    function __construct(array $instructions)
    {
        $this->instructions = $instructions;
        $this->registers = array_fill(0, 8, 0);
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

        return $this->instructions[$this->ip++];
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
                $this->set($a, $this->resolve($b));
                break;
            case 6:
                // jmp a
                $a = $this->next();
                $this->ip = $this->resolve($a) - 1;
            case 9:
                // add a b c
                $a = $this->next();
                $b = $this->next();
                $c = $this->next();
                $this->set($a, ($this->resolve($b) + $this->resolve($c)) % 32768);
                break;
            case 19:
                // out a
                $a = $this->next();
                echo chr($this->resolve($a));
                break;
            case 21:
                // noop
                break;
            default:
                throw new \RuntimeException("Instruction $instruction not implemented.");
                break;
        }
    }

    private function push($value)
    {
        array_push($this->stack, $value);
    }

    private function pop()
    {
        return array_pop($this->stack);
    }

    private function set($i, $value)
    {
        if ($i >= 0 && $i <= 32767) {
            $this->memory[$i] = $value;
            return;
        }

        if ($i >= 32768 && $i <= 32775) {
            $this->registers[$i - 32768] = $value;
            return;
        }

        throw new \RuntimeException("Unable to set invalid value $i");
    }

    private function resolve($n)
    {
        if ($n >= 0 && $n <= 32767) {
            return $n;
        }

        if ($n >= 32768 && $n <= 32775) {
            return $this->registers[$n - 32768];
        }

        throw new \RuntimeException("Unable to resolve invalid value $n");
    }
}

function pack_int16($x)
{
    return pack('v', $x);
}

function unpack_int16($data)
{
    return unpack('v', $data)[1];
}

function parse($code)
{
    return array_map('igorw\synacorvm\unpack_int16', str_split($code, 2));
}

function execute($code)
{
    $vm = new Machine(parse($code));
    return $vm->execute();
}

// execute(implode('', array_map('igorw\synacorvm\pack_int16', [9,32768,32769,4,19,32768])));
execute(file_get_contents('challenge.bin'));
