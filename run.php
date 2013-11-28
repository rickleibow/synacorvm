<?php

use igorw\synacorvm;

require 'src/vm.php';

// execute(implode('', array_map('igorw\synacorvm\pack_int16', [9,32768,32769,4,19,32768])));
synacorvm\execute(file_get_contents('data/challenge.bin'));
