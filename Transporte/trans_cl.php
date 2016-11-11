<?php
if ($argc < 2) {
    echo "Parâmetros insuficientes!" . PHP_EOL;
    echo "php trans_cl.php protocolo" . PHP_EOL;
    die;
}

$protocolo = $argv[1];
$protocolo = strtoupper($protocolo);

//Shift dos argumentos
for ($i = 1; $i < $argc; $i++)
    $argv[$i - 1] = $argv[$i];
$argc = $argc - 1;

switch ($protocolo) {
    case 'UDP':
        require_once 'udp_cl.php';
        break;

    case 'TCP':
        require_once 'tcp_cl.php';
        break;

    default:
        echo "Protocolo inválido!" . PHP_EOL;
        echo "Opções: TCP ou UDP" . PHP_EOL;
        break;
}
?>
