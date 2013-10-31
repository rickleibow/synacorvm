<?php

namespace igorw\synacorvm;

const MACHINE_HALT = 0;
const MACHINE_CONTINUE = 0;

class Machine
{
    private $memory = [];
    private $stack = [];
    private $ip = 0;

    function __construct(array $memory)
    {
        $this->memory = $memory;
        for ($i = 32768; $i <= 32775; $i++) {
            $this->memory[$i] = 0;
        }
    }

    function execute()
    {
        while (null !== ($op = $this->next())) {
            $status = $this->process_op($op);

            if (MACHINE_HALT === $status) {
                break;
            }
        }
    }

    private function next()
    {
        if (!isset($this->memory[$this->ip])) {
            return null;
        }

        return $this->memory[$this->ip++];
    }

    private function process_op($op)
    {
        switch ($op) {
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
            case 2:
                // push a
                $a = $this->next();
                $this->push($a);
                break;
            case 3:
                // pop a
                $a = $this->next();
                $this->set($a, $this->pop());
                break;
            case 4:
                // eq a b c
                $a = $this->next();
                $b = $this->next();
                $c = $this->next();
                $this->set($a, (int) ($this->resolve($b) === $this->resolve($c)));
                break;
            case 6:
                // jmp a
                $a = $this->next();
                $this->ip = $this->resolve($a);
                break;
            case 7:
                // jt a b
                // if a, jmp to b
                $a = $this->next();
                $b = $this->next();
                if ($this->resolve($a)) {
                    $this->ip = $this->resolve($b);
                }
                break;
            case 8:
                // jf a b
                // if not a, jmp to b
                $a = $this->next();
                $b = $this->next();
                if (!$this->resolve($a)) {
                    $this->ip = $this->resolve($b);
                }
                break;
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
                throw new \RuntimeException("Instruction $op not implemented.");
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
        $this->memory[$i] = $value;
    }

    private function resolve($n)
    {
        if ($n >= 0 && $n <= 32767) {
            return $n;
        }

        if ($n >= 32768 && $n <= 32775) {
            return $this->memory[$n];
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
