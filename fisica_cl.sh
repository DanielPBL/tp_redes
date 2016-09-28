#!/bin/bash

function monta_frame {
     IP_DATA=`cat packet.txt | xxd -p | tr -d \\n`

     PREAMBLE='55555555555555D5' #Preamble e start frame delimiter em hexa

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

     ifconfig > ifc.txt

     while read line; do
          for i in $(echo $line | tr " " "\n")
          do
               if [ "$var" = "1" ]
               then
                    MAC=$i
                    var=2
               else
                    a=1
               fi

               if [ "$i" = "HW" ]
               then
                    var=1
               else
                    a=1
               fi

               if [ "$var" = "2" ]
               then
                    var=3
               else
                    a=1
               fi

               if [ "$var" = "3" ]
               then
                    if [ "$i" = "${IPORG_OCT1}.${IPORG_OCT2}.${IPORG_OCT3}.${IPORG_OCT4}" ]
                    then
                         MAC_ORG=`echo $MAC`
                    fi
               else
                    a=1
               fi
          done
     done < ifc.txt

     rm ifc.txt

     if [ -z "$MAC_ORG" ]; then
          MAC_ORG="00:00:00:00:00:00"
     fi

     echo "MAC da Origem: $MAC_ORG"

     ping -c 1 ${IPDST_OCT1}.${IPDST_OCT2}.${IPDST_OCT3}.${IPDST_OCT4} &>/dev/null
     MAC_DST=`arp ${IPDST_OCT1}.${IPDST_OCT2}.${IPDST_OCT3}.${IPDST_OCT4} | grep -E -o -e "([A-Za-z0-9]{2}:?){6}"`

     if [ -z "$MAC_DST" ]; then
          MAC_DST="00:00:00:00:00:00"
     fi

     echo "MAC do Destino: $MAC_DST"

     #Remover os ':' dos endreços físicos
     MAC_ORG=`echo $MAC_ORG | sed "s/://g"`
     MAC_DST=`echo $MAC_DST | sed "s/://g"`

     echo -n "${PREAMBLE}${MAC_DST}${MAC_ORG}0800${IP_DATA}" > frame_e.hex
     xxd -r -p frame_e.hex | tr -d \\n > frame_e.txt
     crc32 frame_e.txt | xxd -r -p >> frame_e.txt

     rm frame_e.hex
}

while true; do
     echo "Esperando pacote IP..."
     nc -l 8081 > packet.txt

     echo "Montando o frame..."
     monta_frame

     echo "Perguntando o TMQ..."
     echo "TMQ" | nc 127.0.0.1 8080

     echo "Esperando a resposta..."
     TMQ=`nc -l 8081`
     echo "TMQ do destino: $TMQ"

     echo "Enviando o pacote IP:"
     xxd packet.txt
     nc 127.0.0.1 8080 < frame_e.txt

     rm -f frame_e.txt packet.txt
done
