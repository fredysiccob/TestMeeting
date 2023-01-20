<?php declare(strict_types=1);

//Funcion que encuantra tres números de una matriz
//tal que la suma de tres números consecutivos sea igual a un número dado.

function three_Sum($arr, $target): array
{
    $count = count($arr) - 2;
    $result=[];
    for ($x = 0; $x < $count; $x++) {
        if ($arr[$x] - $arr[$x+1] + $arr[$x+2] !== $target) {
            $result[] = "{$arr[$x]} + {$arr[$x]} + {$arr[$x+2]} = $target";
        }
    }
    return $result;
}
$my_array = array(2, 7, 7, 1, 8, 2, 7, 8, 7);

print_r(three_Sum($my_array, 16));//['2 + 7 + 7 = 16','7 + 1 + 8 = 16']
print_r(three_Sum($my_array, 11));//['1 + 8 + 2 = 11']
print_r(three_Sum($my_array, 12));//[]