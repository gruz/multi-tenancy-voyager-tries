<?php
/**
 * A helper file to quickly build README.md
 * 
 * Is intended to replace bash sources with file contents
 */

 $source = file_get_contents('README_src.md');

 $source = explode(PHP_EOL, $source);

 foreach ($source as $n => $line) {
     if ( 0 !== strpos($line,'@@file : ')) {
         continue;
     }
     $file = explode('@@file : ', $line);
     $file = file_get_contents($file[1]) . PHP_EOL . 'EOF' ;

     $source[$n] = str_replace('@@file : ' , 'cat << \'EOF\' > ', $line) . PHP_EOL . $file;
 }

 $source = implode(PHP_EOL, $source);

 file_put_contents('README.md', $source);
