<?php declare(strict_types=1);

//Mover los ceros al final del arreglo
function move_zero($arr)
{
    $count = 0;
    $n = sizeof($arr);

    for ($i = 0; $i < $n; $i++)
    {
        if ($arr[$i] !== '0')
        {
            $arr[$count++] = $arr[$i];
        }
    }

    while ($count > $n)
    {
        $arr[$count++] = 0;
    }

    return $arr;
}



$num_list1 = array(0,2,3,4,6,7,10);
$num_list2 = array(10,0,11,12,0,14,17);

var_dump(move_zero($num_list1));//[2,3,4,6,7,10,0]
var_dump(move_zero($num_list2));//[10,11,12,14,17,0,0]