<?php

namespace igorw\synacorvm;

const MACHINE_HALT = 0;
const MACHINE_CONTINUE = 0;

const MACHINE_MODULUS = 32768;

const REGION_REGISTER = 32768;
const REGION_STACK = 32776;

class Machine
{
    private $memory = [];
    private $ip = 0;
    private $sp = 0;
    private $input_buffer = '';

    function __construct(array $memory)
    {
        $this->memory = $memory;
        for ($i = REGION_REGISTER; $i < REGION_STACK; $i++) {
            $this->memory[$i] = 0;
        }

        $this->sp = REGION_STACK;
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
                $this->push($this->resolve($a));
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
            case 5:
                // gt a b c
                $a = $this->next();
                $b = $this->next();
                $c = $this->next();
                $this->set($a, (int) ($this->resolve($b) > $this->resolve($c)));
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
                $this->set($a, ($this->resolve($b) + $this->resolve($c)) % MACHINE_MODULUS);
                break;
            case 10:
                // mult a b c
                $a = $this->next();
                $b = $this->next();
                $c = $this->next();
                $this->set($a, ($this->resolve($b) * $this->resolve($c)) % MACHINE_MODULUS);
                break;
            case 11:
                // mod a b c
                $a = $this->next();
                $b = $this->next();
                $c = $this->next();
                $this->set($a, $this->resolve($b) % $this->resolve($c));
                break;
            case 12:
                // and a b c (bitwise)
                $a = $this->next();
                $b = $this->next();
                $c = $this->next();
                $this->set($a, $this->resolve($b) & $this->resolve($c));
                break;
            case 13:
                // or a b c (bitwise)
                $a = $this->next();
                $b = $this->next();
                $c = $this->next();
                $this->set($a, $this->resolve($b) | $this->resolve($c));
                break;
            case 14:
                // not a b (bitwise)
                $a = $this->next();
                $b = $this->next();
                $this->set($a, $this->resolve($b) ^ 0x7fff);
                break;
            case 15:
                // rmem a b
                $a = $this->next();
                $b = $this->next();
                $this->set($a, $this->memory[$this->resolve($b)]);
                break;
            case 16:
                // wmem a b
                $a = $this->next();
                $b = $this->next();
                $this->set($this->resolve($a), $this->resolve($b));
                break;
            case 17:
                // call a
                $a = $this->next();
                $this->push($this->ip);
                $this->ip = $this->resolve($a);
                break;
            case 18:
                // ret
                $ip = $this->pop();
                if (null === $ip) {
                    return MACHINE_HALT;
                }
                $this->ip = $ip;
                break;
            case 19:
                // out a
                $a = $this->next();
                echo chr($this->resolve($a));
                break;
            case 20:
                // in a
                $a = $this->next();
                $in = $this->read_char();
                echo $this->set($a, ord($in));
                break;
            case 21:
                // noop
                break;
            default:
                throw new \RuntimeException("Instruction $op not implemented.");
                break;
        }
    }

    private function read_char()
    {
        if (0 === strlen($this->input_buffer)) {
            $this->input_buffer = fgets(STDIN);
        }

        $in = $this->input_buffer[0];
        $this->input_buffer = substr($this->input_buffer, 1);
        return $in;
    }

    private function push($value)
    {
        $this->memory[++$this->sp] = $value;
    }

    private function pop()
    {
        if (REGION_STACK === $this->sp) {
            return null;
        }

        return $this->memory[$this->sp--];
    }

    private function set($i, $value)
    {
        $this->memory[$i] = $value;
    }

    private function resolve($n)
    {
        if ($n >= 0 && $n < REGION_REGISTER) {
            return $n;
        }

        if ($n >= REGION_REGISTER && $n < REGION_STACK) {
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
    $vm->execute();
}
