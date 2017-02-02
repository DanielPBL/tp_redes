<?php
require_once 'camadas.php';
/*
pack format
c	signed char
C	unsigned char
n	unsigned short (always 16 bit, big endian byte order)
v	unsigned short (always 16 bit, little endian byte order)
N	unsigned long (always 32 bit, big endian byte order)
V	unsigned long (always 32 bit, little endian byte order)

Cabeçalho UDP:
Source Port -> S
Destination Port -> S
Length -> S
Checksum -> S

pack("nnnn")
*/

if ($argc < 3) {
    echo "Parâmetros insuficientes!" . PHP_EOL;
    echo "php tcp_cl.php porta_escutada porta_rede" . PHP_EOL;
    die;
}

$app_port  = (int)$argv[1];
$porta_rede = (int)$argv[2];

$tcp = new TCP($porta_rede, true, 0);

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

try {
    echo "Perguntando o TMQ" . PHP_EOL;

    $tcp->send_segment("TMQ");
    $tmq = (int)$tcp->recv_segment();
    //MMS = TMQ - IP_HEADER - ETHERNET_HEADER
    $mms = $tmq - 20 - 26;
    $tcp->setMMS($mms);

    echo "TMQ: $tmq" . PHP_EOL;
    echo "MMS: $mms" . PHP_EOL;
} catch(Exception $e) {
    echo "Erro ao obter o TMQ." . PHP_EOL;
    echo "Socket error ({$e->getCode()}): {$e->getMessage()}" . PHP_EOL;
    echo "Arquivo: '{$e->getFile()}'- Linha: {$e->getLine()}" . PHP_EOL;
    print_r($e->getTrace());
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
        echo "socket_read() falhou. Motivo: " . socket_strerror(socket_last_error($connection)) . "\n";
        break;
    }

    $pos       = strpos($msg, 'Host: ') + 6;
    $host      = substr($msg, $pos, strpos($msg, "\r\n", $pos) - $pos);
    $arr_host  = explode(':', $host);
    $porta_sr  = $app_port;
    $porta_ds  = count($arr_host) < 2 ? 8080 : (int)$arr_host[1];
    $tcp->host = $arr_host[0];

    $tcp->setSourcePort($porta_sr);
    $tcp->setDestinationPort($porta_ds);

    try {
        echo "Iniciando conexão TCP..." . PHP_EOL;

        $segmento = $tcp->buildSegment('', TCP::SYN, true);
        TCP::dump_segment($segmento);
        $tcp->send_segment($segmento);

        $resposta = $tcp->recv_segment();
        TCP::dump_segment($resposta);
        $infos    = TCP::unpack_info($resposta);

        if (!TCP::is_valid_segment($resposta) ||
            $infos['ack_num'] != $tcp->getSeqNumber() ||
            !TCP::is_flag_set($infos['control'], TCP::SYN | TCP::ACK)) {
            echo "Erro no estabelecimento da conexão." . PHP_EOL;
            $tcp->close();
            continue;
        }

        $tcp->setAckNumber($infos['seq_num']);
        $tcp->calcNextAck($infos['data'], true);
        $segmento = $tcp->buildSegment('', TCP::ACK);
        TCP::dump_segment($segmento);
        $tcp->send_segment($segmento);

        echo "Conexão estabelecida." . PHP_EOL;
        echo "Transmitindo dados..." . PHP_EOL;
        usleep(500000);
        
        $tcp->sendData($msg, $infos);

        echo "Enviando pedido de PUSH..." . PHP_EOL;
        usleep(500000);

        $tcp->calcNextAck($infos['data']);
        $segmento = $tcp->buildSegment('', TCP::PSH, true);
        TCP::dump_segment($segmento);
        $tcp->send_segment($segmento);

        $resposta = $tcp->recv_segment();
        TCP::dump_segment($resposta);
        $infos    = TCP::unpack_info($resposta);

        if (!TCP::is_valid_segment($resposta) || $infos['ack_num'] != $tcp->getSeqNumber()) {
            echo "Falha na confirmação do pedido de PUSH." . PHP_EOL;
            continue;
        }

        echo "Recebendo resposta..." . PHP_EOL;

        $msg = $tcp->recvData($infos);

        echo "Pedido de PUSH recebido." . PHP_EOL;

        $tcp->calcNextAck($infos['data'], true);
        $segmento = $tcp->buildSegment('', TCP::ACK);
        TCP::dump_segment($segmento);
        $tcp->send_segment($segmento);

        echo "Enviando mensagem para a aplicação ({$tcp->getSourcePort()})..." . PHP_EOL;

        if (socket_write($connection, $msg, strlen($msg)) === false) {
            echo "socket_write() falhou. Motivo: " . socket_strerror(socket_last_error($socket)) . PHP_EOL;
        }

        echo "Finalizando conexão." . PHP_EOL;
        usleep(500000);

        $tcp->calcNextAck($infos['data']);
        $segmento = $tcp->buildSegment('', TCP::FIN | TCP::ACK, true);
        TCP::dump_segment($segmento);
        $tcp->send_segment($segmento);

        $resposta = $tcp->recv_segment();
        TCP::dump_segment($resposta);
        $infos    = TCP::unpack_info($resposta);

        if (!TCP::is_valid_segment($resposta) ||
            $infos['ack_num'] != $tcp->getSeqNumber() ||
            !TCP::is_flag_set($infos['control'], TCP::ACK)) {
            echo "Erro na confirmação do fechamento da conexão." . PHP_EOL;
            $tcp->close();
            continue;
        }

        $resposta = $tcp->recv_segment();
        TCP::dump_segment($resposta);
        $infos    = TCP::unpack_info($resposta);

        if (!TCP::is_valid_segment($resposta) ||
            $infos['ack_num'] != $tcp->getSeqNumber() ||
            !TCP::is_flag_set($infos['control'], TCP::FIN | TCP::ACK)) {
            echo "Erro no fechamento da conexão." . PHP_EOL;
            $tcp->close();
            continue;
        }

        $tcp->calcNextAck($infos['data'], true);
        $segmento = $tcp->buildSegment('', TCP::ACK, true);
        TCP::dump_segment($segmento);
        $tcp->send_segment($segmento);

        echo "Conexão Fechada." . PHP_EOL;

        $tcp->close();

    } catch(Exception $e) {
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
