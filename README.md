# tp_redes
Repo do TP de Redes de Computadores I

requisitos
php 7 e node > 6

Passos para testar

1- Lado Cliente
./fisica_sr.sh 5001 6020 128
./fisica_cl.sh 6002 172.16.17.141 5002
Dar o make na aplicação e então
~/tp_redes/Aplicação/build$ ./app_cl.out 8002
php trans_cl.php tcp 8002 7002
nodejs rede_cl.js 7002 tabela_c.txt 
 

 2 - Lado Servidor
 ./fisica_sr.sh 5002 6010 128
./fisica_cl.sh 6001 172.16.17.140 5001
./app_sr.out 8080
php trans_sr.php TCP 7000
nodejs rede_sr.js 7000 tabela_s.txt




