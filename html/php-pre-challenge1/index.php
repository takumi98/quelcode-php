<?php
for ($i = 1; $i <= 100; $i++) {
    //ここからコードを書く
    if($i % 3 === 0) {
        $result = '3の倍数';
        if($i % 5 === 0){
          $result = '3の倍数であり、5の倍数';
        }
    }elseif($i % 5 === 0){
        $result = '5の倍数';
    }else{
        $result = $i;
    }
    echo ($result.'<br>');
}
