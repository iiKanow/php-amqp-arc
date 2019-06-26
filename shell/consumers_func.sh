#!/bin/bash

func=$1;
num=$2;

basepath='/users/kano/home/amqp/';
path=$basepath'index.php consumers';
cd $basepath;
printf '\n\n启动程序:'${path}' '${func}'\n';


ID=`ps -ef | grep "$path $func" | grep -v "$0" | grep -v "grep" | awk '{print $2}'`
#echo $ID
for loop in $ID
do
    kill -9 $loop;
    printf '中止进程:'$loop'\n';
done

for i in `seq 1 $num`;
do
    nohup php $path $1 >>${basepath}/nohup.out&
done

sleep 1;

ID=`ps -ef | grep "$path $func" | grep -v "$0" | grep -v "grep" | awk '{print $2}'`
printf '实际启动：%s %s %s %s %s\n' $ID;
