#/bin/bash
set -e
for file in examples/*.php; 
do 
    echo Running $file
    diff <(php $file) <(cat $file.expect)
done
