#!/bin/bash

function monta_frame {
     IP_DATA=`cat packet.txt | xxd -p | tr -d \\n`

     #Preamble e start frame delimiter em hexa
     PREAMBLE='55555555555555D5'

     #Bytes 11-14 do pacote IP
     IPORG_OCT1=`echo $((16#$(echo $IP_DATA | cut -b25-26)))`
     IPORG_OCT2=`echo $((16#$(echo $IP_DATA | cut -b27-28)))`
     IPORG_OCT3=`echo $((16#$(echo $IP_DATA | cut -b29-30)))`
     IPORG_OCT4=`echo $((16#$(echo $IP_DATA | cut -b31-32)))`
     IPORG=`echo -n "${IPORG_OCT1}.${IPORG_OCT2}.${IPORG_OCT3}.${IPORG_OCT4}"`
     echo "IP de Origem: $IPORG"

     #Bytes 15-18 do pacote IP
     IPDST_OCT1=`echo $((16#$(echo $IP_DATA | cut -b33-34)))`
     IPDST_OCT2=`echo $((16#$(echo $IP_DATA | cut -b35-36)))`
     IPDST_OCT3=`echo $((16#$(echo $IP_DATA | cut -b37-38)))`
     IPDST_OCT4=`echo $((16#$(echo $IP_DATA | cut -b39-40)))`
     IPDST=`echo "${IPDST_OCT1}.${IPDST_OCT2}.${IPDST_OCT3}.${IPDST_OCT4}"`
     echo "IP de Destino: $IPDST"

     #Pegar o MAC da ORIGEM
     ifconfig > ifc.txt

     while read line; do
          for i in $(echo $line | tr " " "\n")
          do
               if [ "$var" = "1" ]; then
                    MAC=$i
                    var=2
               fi

               if [ "$i" = "HW" ]; then
                    var=1
               fi

               if [ "$var" = "2" ]; then
                    var=3

               if [ "$var" = "3" ] && [ "$i" = "${IPORG_OCT1}.${IPORG_OCT2}.${IPORG_OCT3}.${IPORG_OCT4}" ]; then
                         MAC_ORG=`echo $MAC`
                    fi
               fi
          done
     done < ifc.txt

     rm ifc.txt

     #Se não encontrar o MAC de origem
     if [ -z "$MAC_ORG" ]; then
          MAC_ORG="00:00:00:00:00:00"
     fi

     echo "MAC da Origem: $MAC_ORG"

     #Ping para poder fazer o ARP
     ping -c 1 ${IPDST_OCT1}.${IPDST_OCT2}.${IPDST_OCT3}.${IPDST_OCT4} &>/dev/null
     MAC_DST=`arp ${IPDST_OCT1}.${IPDST_OCT2}.${IPDST_OCT3}.${IPDST_OCT4} | grep -E -o -e "([A-Za-z0-9]{2}:?){6}"`

     #Se não encontrar o MAC de destino
     if [ -z "$MAC_DST" ]; then
          MAC_DST="00:00:00:00:00:00"
     fi

     echo "MAC do Destino: $MAC_DST"

     #Remover os ':' dos endreços físicos
     MAC_ORG=`echo $MAC_ORG | sed "s/://g"`
     MAC_DST=`echo $MAC_DST | sed "s/://g"`

     #Montar o quadro Ethernet
     echo -n "${PREAMBLE}${MAC_DST}${MAC_ORG}0800${IP_DATA}" > frame_e.hex

     #Transfroma o quadro de hexa textual para binário
     xxd -r -p frame_e.hex | tr -d \\n > frame_e.dat

     #Calcula o CRC e adiciona no final do quadro
     crc32 frame_e.dat | xxd -r -p >> frame_e.dat

     #Transforma o quadro de binário para binário textual
     xxd -b frame_e.dat | cut -d" " -f 2-7 | tr -d \\n | sed "s/ //g" > frame_e.txt

     rm frame_e.hex &> /dev/null
     rm frame_e.dat &> /dev/null
}

while true; do
     #Aguarda conexão da camada superior
     echo "Esperando pacote IP..."
     nc -l 8081 > packet.txt

     echo "Montando o frame..."
     monta_frame

     echo "Perguntando o TMQ..."
     echo "TMQ" | nc 127.0.0.1 8080

     echo "Esperando a resposta..."
     TMQ=`nc -l 8081`
     echo "TMQ do destino: $TMQ"

     #Exibe o pacote IP no formato HEX Dump
     echo "Enviando o pacote IP:"
     xxd packet.txt

     #Envia o quadro Ethernet no formato binário textual para o servidor da camada física
     nc 127.0.0.1 8080 < frame_e.txt

     rm frame_e.txt &> /dev/null
     rm packet.txt &> /dev/null
done
