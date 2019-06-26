#!/bin/bash

shellpath='/users/kano/home/amqp/shell';

#启动consumer1
source $shellpath/consumers_func.sh consumer3 2


#启动consumer2
source $shellpath/consumers_func.sh consumer4 2
