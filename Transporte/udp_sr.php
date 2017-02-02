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

if ($argc < 2) {
    echo "Parâmetros insuficientes!" . PHP_EOL;
    echo "php udp_sr.php porta_rede" . PHP_EOL;
    die;
}

$porta_rede = (int)$argv[1];

if (($socket_rd = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
    echo "socket_create() falhou. Motivo: " . socket_strerror(socket_last_error()) . PHP_EOL;
    die;
}

if (socket_bind($socket_rd, '127.0.0.1', $porta_rede) === false) {
    echo "socket_bind() falhou. Motivo: " . socket_strerror(socket_last_error($socket_rd)) . PHP_EOL;
    socket_close($socket_rd);
    die;
}

if (socket_listen($socket_rd) === false) {
    echo "socket_listen() falhou. Motivo: " . socket_strerror(socket_last_error($socket_rd)) . PHP_EOL;
    socket_close($socket_rd);
    die;
}

do {
    echo "Esperando dados da camada de rede..." . PHP_EOL;
    try {
        if (($connection_rd = socket_accept($socket_rd)) === false)
            throw_socket_exception($socket_rd);

        try {
            if (($segmento = socket_read($connection_rd, 8192, PHP_BINARY_READ)) === false)
                throw_socket_exception($connection_rd);

            echo "Segmento recebido..." . PHP_EOL;

            //Remover dados da camada de rede
            //$segmento      = substr($segmento, 20);

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

            echo "Enviando mensagem para a aplicação ({$dados['porta_ds']})..." . PHP_EOL;

            if (($socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
                echo "socket_create() falhou. Motivo: " . socket_strerror(socket_last_error()) . PHP_EOL;
                break;
            }

            $connection = socket_connect($socket, '127.0.0.1', $dados['porta_ds']);
            if ($connection === false) {
                if (socket_last_error($socket) == 111) {
                    echo "Porta de destino inválida ({$dados['porta_ds']})" . PHP_EOL;
                    echo "Pacote ignorado." . PHP_EOL;
                    socket_close($socket);
                    continue;
                }
                echo "socket_connect() falhou. Motivo: " . socket_strerror(socket_last_error($socket)) . PHP_EOL;
                break;
            }

            if (socket_write($socket, $msg, strlen($msg)) === false) {
                echo "socket_write() falhou. Motivo: " . socket_strerror(socket_last_error($socket)) . PHP_EOL;
            }

            echo "Esperando resposta..." . PHP_EOL;

            if (false === ($msg = socket_read($socket, 8192, PHP_BINARY_READ))) {
                echo "socket_read() falhou. Motivo: " . socket_strerror(socket_last_error($app_cl_cnn)) . PHP_EOL;
                break;
            }

            socket_close($socket);

            $temp      = $dados['porta_ds'];
            $porta_ds  = $dados['porta_sr'];
            $porta_sr  = $temp;

            $length    = strlen($msg) + 8;
            $checksum  = checksum(pack('nnn', $porta_sr, $porta_ds, $length) . $msg);
            $segmento  = pack('nnnn', $porta_sr, $porta_ds, $length, $checksum);
            $segmento .= $msg;

            echo "Porta de Origem : $porta_sr" . PHP_EOL;
            echo "Porta de Destino: $porta_ds" . PHP_EOL;
            echo "Tamanho ........: $length"   . PHP_EOL;
            echo "Checksum .......: $checksum" . PHP_EOL;

            echo "Segmento" . PHP_EOL;
            hex_dump($segmento);

            echo "Enviando dados para a camada de rede..." . PHP_EOL;
            /*$ip_header = IPHeader::build('192.168.1.7', '192.168.1.1');
            $pacote = $ip_header . $segmento;

            send_socket($pacote, $fscl_port);*/
            if (socket_write($connection_rd, $segmento, strlen($segmento)) === false)
                throw_socket_exception($connection_rd);
        } catch (Exception $e) {
            echo "Socket error ({$e->getCode()}): {$e->getMessage()}" . PHP_EOL;
            echo "Arquivo: '{$e->getFile()}'- Linha: {$e->getLine()}" . PHP_EOL;
            print_r($e->getTrace());
            break;
        } finally {
            socket_close($connection_rd);
        }
    } finally {
        socket_close($socket_rd);
    }
} while(true);
?>
