#!/bin/bash

function monta_frame {
  IP_DATA=`xxd -p pacote.txt | tr -d \\n`

  PREAMBLE='55555555555555D5' #Preamble e start frame delimiter em hexa

  #Bytes 11-14 do pacote IP
  IPORG_OCT1=`echo $((16#$(echo $IP_DATA | cut -b25-26)))`
  IPORG_OCT2=`echo $((16#$(echo $IP_DATA | cut -b27-28)))`
  IPORG_OCT3=`echo $((16#$(echo $IP_DATA | cut -b29-30)))`
  IPORG_OCT4=`echo $((16#$(echo $IP_DATA | cut -b31-32)))`

  #Bytes 15-18 do pacote IP
  IPDST_OCT1=`echo $((16#$(echo $IP_DATA | cut -b33-34)))`
  IPDST_OCT2=`echo $((16#$(echo $IP_DATA | cut -b35-36)))`
  IPDST_OCT3=`echo $((16#$(echo $IP_DATA | cut -b37-38)))`
  IPDST_OCT4=`echo $((16#$(echo $IP_DATA | cut -b39-40)))`

  ping -c 1 ${IPDST_OCT1}.${IPDST_OCT2}.${IPDST_OCT3}.${IPDST_OCT4} &>/dev/null
  MAC_DST=`arp -a ${IPDST_OCT1}.${IPDST_OCT2}.${IPDST_OCT3}.${IPDST_OCT4} | grep -E -o -e "([A-Za-z0-9]{2}:?){6}" | sed "s/://g"`

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
          MAC_ORG=`echo $MAC | sed "s/://g"`
        fi
      else
        a=1
      fi
    done
  done < ifc.txt

  rm ifc.txt

  echo "${PREAMBLE}${MAC_DST}${MAC_ORG}0800${IP_DATA}" > frame.hex
  CRC=`crc32 frame.hex`

  echo $CRC >> frame.hex
  xxd -r -p frame.hex | tr -d \\n > frame.txt

  rm frame.hex
}

echo $(monta_frame)
