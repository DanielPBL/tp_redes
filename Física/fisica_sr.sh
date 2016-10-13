#!/bin/bash

#Porta do servidor
PORT=`echo $1`

#Informações da Entidade Par
IP_CLIENT=`echo $2`
PORT_CLIENT=`echo $3`

#TMQ
TMQ=`echo $4`

#Se não informar a porta
if [ -z "$PORT" ]; then
     echo "A porta deve ser informada"
     exit
fi

#Se não informar o IP_CLIENT
if [ -z "$IP_CLIENT" ]; then
     echo "O IP do servidor deve ser informado"
     exit
fi

#Se não informar o PORT_CLIENT
if [ -z "$PORT_CLIENT" ]; then
     echo "A porta do servidor deve ser informada"
     exit
fi

#Se não informar o TMQ
if [ -z "$TMQ" ]; then
     echo "O TMQ deve ser informado"
     exit
fi

#Faixa de valores para o TMQ
if [ "$TMQ" -lt "88" ] || [ "$TMQ" -gt "1542" ]; then
     echo "O TMQ deve estar entre 88 e 1542"
     exit
fi

while true; do
     #Espera a conexão do cliente da camada física
     echo "Esperando conexão..."
     nc -l $PORT > frame_r.txt

     #Armazena o pedido para verificar se é a mensagem de TMQ
     REQUEST=`cat frame_r.txt`

     #Só responde com o TMQ se a mensagem for exatamente "TMQ"
     if [ "$REQUEST" == "TMQ" ]; then
          echo "Repondendo com TMQ: $TMQ"
          echo $TMQ | nc $IP_CLIENT $PORT_CLIENT
     else
          #Caso contrário considera que é um quadro Ethernet em formato binário textual
          echo "Recebendo Frame Ethernet..."

          #Separa o arquivo em dígitos hexa (4 bits)
          cat frame_r.txt | sed "s/\([0-9]\{4\}\)/\1\n/g" > frame_r.dat

          #Convertendo cada dígito hexa de binário textual para hexa textual
          rm frame_r.hex &> /dev/null
          while read line; do
            echo "obase=16; ibase=2; $line" | bc | tr -d \\n >> frame_r.hex
          done < frame_r.dat

          #Conversão de hexa textual para binário
          xxd -r -p frame_r.hex > frame_r.dat

          #Exibe o quadro Ethernet no formato HEX Dump
          xxd frame_r.dat

          #sed "s/.\{44\}//" -> remove o cabeçalho (Preamble + MAC Dst + MAC Src + Ethertype = 22 bytes)
          #sed "s/.\{8\}$//" -> remove o CRC (4 bytes)
          cat frame_r.dat | xxd -p | tr -d \\n | sed "s/.\{44\}//" | sed "s/.\{8\}$//" > payload.hex

          #Converte o payload de hexa textual para binário
          xxd -r -p payload.hex | tr -d \\n > payload.bin

          #Entrega o pacote IP (PAYLOAD do quadro Ethernet) para a camada superior
          echo "Enviando para camada superior..."
          #nc $IP_CLIENT porta_a_definir < payload.bin

          rm frame_r.dat &> /dev/null
          rm frame_r.hex &> /dev/null
          rm payload.hex &> /dev/null
          rm payload.bin &> /dev/null
     fi

     rm frame_r.txt &> /dev/null
done
