#!/bin/bash

TMQ=`echo $1`

if [ -z "$TMQ" ]; then
     echo "O TMQ deve ser informado"
     exit
fi

if [ "$TMQ" -lt "88" ] || [ "$TMQ" -gt "1542" ]; then
     echo "O TMQ deve estar entre 88 e 1542"
     exit
fi

while true; do
     echo "Esperando conexão..."
     nc -l 8080 > frame_r.txt

     REQUEST=`cat frame_r.txt`

     if [ "$REQUEST" == "TMQ" ]; then
          echo "Repondendo com TMQ: $TMQ"
          echo $TMQ | nc 127.0.0.1 8081
     else
          echo "Recebendo Frame Ethernet..."
          xxd frame_r.txt
          echo "Enviando para camada superior..."
          #nc 127.0.0.1 porta_a_definir < frame_r.txt
     fi

     rm frame_r.txt
done
