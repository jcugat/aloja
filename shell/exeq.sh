#!/bin/bash

#Controls queue execution from a subshell in the master node (should be in crontab)

#first check if already running
self_name="$(basename $0)"
self_pid="$$"
exists="$(pgrep -f "$self_name"|wc -l)"
#exists="$(ps aux|grep "$self_name"|wc -l)"
#echo "$(pgrep -f "$self_name")"
if [ "$exists" != "3" ] ; then
  echo "Process $self_name already running with pid $self_pid. Count $exists"
  exit
fi

trap 'kill $(jobs -p); exit;' SIGINT SIGTERM EXIT

#echo "USER $USER"

[ -z "$1" ] && CLUSTER_NAME="az" || CLUSTER_NAME="$1"

Q_SOURCE_PATH="/home/$USER/share/shell/queue"

Q_PATH="/home/$USER/local/queue_$CLUSTER_NAME"

#prepare dirs for first time
mkdir -p $Q_PATH/{exec,done,conf,hold}

EXEC_PATH="$Q_PATH/exec"
DONE_PATH="$Q_PATH/done"
CONF_PATH="$Q_PATH/conf"
LOG_FILE="$Q_PATH/queue.log"

cd "$Q_PATH"

file_name=""
#command="ls -l $Q_PATH| egrep -v '^d'|tail -n +2|head -n 1|awk '{print \$(NF)}'"
#command="ls -l $Q_PATH| egrep -v '^d'|tail -n +2|head -n 1|awk '{print \$(NF)}'"
get_first_file(){
  file_name=`ls -l $Q_PATH| egrep -v -e '^d'|grep '_conf_'|tail -n +1|head -n 1|awk '{print \$(NF)}'`
  #echo "FN $file_name" 2>&1 |tee -a "$LOG_FILE"
}

iteration=0
while true
do
  get_first_file
  current_file="$file_name"
  mod=$((iteration % 10))

  if [ -f "$current_file" ] ; then
    echo "Executing: $current_file" 2>&1 |tee -a "$LOG_FILE"
    mv "$Q_PATH/$current_file" "$EXEC_PATH/" 2>&1 |tee -a "$LOG_FILE"

    #execute command(s)
    /bin/bash "$EXEC_PATH/$current_file" 2>&1 >> "$LOG_FILE"

    echo "Done $current_file" 2>&1 |tee -a "$LOG_FILE"
    mv  "$EXEC_PATH/$current_file" "$DONE_PATH/" 2>&1 |tee -a "$LOG_FILE"
  else
    if [ "$mod" == "0" ] ; then
      echo "Sleeping, iteration $iteration" 2>&1 |tee -a "$LOG_FILE"
    fi
    sleep 1
  fi

  iteration=$((iteration + 1))
done

