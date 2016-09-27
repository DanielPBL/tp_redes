#!/bin/bash

TMQ=`echo $1`

if [ "$TMQ" -lt "88" ] || [ "$TMQ" -gt "1542" ]; then
  echo "O TMQ deve estar entre 88 e 1542"
  exit
fi

while true; do
  echo "Esperando conexão..."
  REQUEST=`nc -l 8080`

  if [ ! "$REQUEST" == "TMQ" ]; then
    echo "Deve primeiro perguntar o TMQ"
    exit
  fi

  echo "Repondendo com TMQ: $TMQ"
  echo $TMQ | nc 127.0.0.1 8081

  echo "Recebendo Frame Ethernet..."
  FRAME=`nc -l 8080`
  echo $FRAME | xxd
  echo "Enviando para camada superior..."
done
