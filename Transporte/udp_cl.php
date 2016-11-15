<?php
require_once 'camadas.php';
/*
pack format
n	unsigned short (always 16 bit, big endian byte order)

Cabeçalho UDP:
Source Port -> S
Destination Port -> S
Length -> S
Checksum -> S

pack("nnnn")
*/

if ($argc < 4) {
    echo "Parâmetros insuficientes!" . PHP_EOL;
    echo "php udp_cl.php porta_escutada porta_fscl porta_fssr" . PHP_EOL;
    die;
}

$app_port  = (int)$argv[1];
$fscl_port = (int)$argv[2];
$fssr_port = (int)$argv[3];

if (($socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
    echo "socket_create() falhou. Motivo: " . socket_strerror(socket_last_error()) . PHP_EOL;
    die;
}

if (socket_bind($socket, '127.0.0.1', $app_port) === false) {
    echo "socket_bind() falhou. Motivo: " . socket_strerror(socket_last_error($socket)) . PHP_EOL;
    socket_close($socket);
    die;
}

if (socket_listen($socket) === false) {
    echo "socket_listen() falhou. Motivo: " . socket_strerror(socket_last_error($socket)) . PHP_EOL;
    socket_close($socket);
    die;
}

do {
    echo "Esperando dados da aplicação..." . PHP_EOL;

    if (($connection = socket_accept($socket)) === false) {
        echo "socket_accept() falhou. Motivo: " . socket_strerror(socket_last_error($socket)) . PHP_EOL;
        break;
    }

    if (false === ($msg = socket_read($connection, 8192, PHP_BINARY_READ))) {
        echo "socket_read() falhou. Motivo: " . socket_strerror(socket_last_error($connection)) . PHP_EOL;
        break;
    }

    $pos       = strpos($msg, 'Host: ') + 6;
    $host      = substr($msg, $pos, strpos($msg, "\r\n", $pos) - $pos);
    $porta_ds  = explode(':', $host);

    $porta_sr  = $app_port;
    $porta_ds  = count($porta_ds) < 2 ? 8080 : (int)$porta_ds[1];
    $length    = strlen($msg) + 8;
    $checksum  = checksum(pack('nnn', $porta_sr, $porta_ds, $length) . $msg);
    $segmento  = pack('nnnn', $porta_sr, $porta_ds, $length, $checksum);
    $segmento .= $msg;

    echo "Porta de Origem : $porta_sr" . PHP_EOL;
    echo "Porta de Destino: $porta_ds" . PHP_EOL;
    echo "Tamanho         : $length"   . PHP_EOL;
    echo "Checksum        : $checksum" . PHP_EOL;

    echo "Segmento" . PHP_EOL;
    hex_dump($segmento);

    try {
        echo "Enviando dados para a camada física..." . PHP_EOL;

        $ip_header = IPHeader::build('192.168.1.7', '192.168.1.1');
        $pacote = $ip_header . $segmento;
        send_socket($pacote, $fscl_port);

        echo "Esperando resposta..." . PHP_EOL;

        $segmento = recv_socket($fssr_port);

        echo "Segmento recebido..." . PHP_EOL;

        //Remover dados da camada de rede
        $segmento      = substr($segmento, 20);
        $cabecalho_udp = substr($segmento, 0, 8);
        $dados         = unpack('nporta_sr/nporta_ds/nlength/nchecksum', $cabecalho_udp);
        $msg           = substr($segmento, 8);

        echo "Validação do segmento... ";

        if (checksum(pack('nnn', $dados['porta_sr'], $dados['porta_ds'], $dados['length']). $msg) === $dados['checksum']) {
            echo "OK" . PHP_EOL;
        } else {
            echo "FALHA" . PHP_EOL;
            echo "Segmento ignorado." . PHP_EOL;
            continue;
        }

        echo "Enviando mensagem para a aplicação..." . PHP_EOL;

        if (socket_write($connection, $msg, strlen($msg)) === false) {
            echo "socket_write() failed.\nReason: " . socket_strerror(socket_last_error($connection)) . PHP_EOL;
            break;
        }
    } catch (Exception $e) {
        echo "Socket error ({$e->getCode()}): {$e->getMessage()}" . PHP_EOL;
        echo "Arquivo: '{$e->getFile()}'- Linha: {$e->getLine()}" . PHP_EOL;
        print_r($e->getTrace());
        break;
    }

    socket_close($connection);
} while(true);

socket_close($connection);
socket_close($socket);
?>
