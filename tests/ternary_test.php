<?php
  $num = 10;
  print $num == 10 ? "num is 10\n" : "num is not 10";

  $num2 = 20;
  $result = ($num == 10 ? $num : ($num2==20 ? $num2 : $num + $num2));
  print $result;
?>