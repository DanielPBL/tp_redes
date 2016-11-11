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

if ($argc < 4) {
    echo "Parâmtros insuficientes!" . PHP_EOL;
    echo "php udp_cl.php porta_fscl porta_fssr porta_injecao" . PHP_EOL;
    die;
}

$fscl_port = (int)$argv[1];
$fssr_port = (int)$argv[2];
$porta_injecao = (int)$argv[3];

$tcp = new TCP(300);

try {
    echo "Perguntando o TMQ" . PHP_EOL;

    send_socket("TMQ", $porta_injecao);
    $tmq = (int)recv_socket($fssr_port);
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
    echo "Esperando dados da camada física..." . PHP_EOL;

    try {
        $segmento = TCP::recv_segment($fssr_port);

        echo "Segmento recebido." . PHP_EOL;

        TCP::dump_segment($segmento);

        if (!TCP::is_valid_segment($segmento)) {
            echo "Segmento corrompido." . PHP_EOL;
            continue;
        }

        $segmento = TCP::unpack_info($segmento);

        if (!TCP::is_flag_set($segmento['control'], TCP::SYN)) {
            echo "TCP Error: Nenhuma conexão ativa." . PHP_EOL;
            continue;
        }

        $tcp->setAckNumber($segmento['seq_num']);
        $tcp->calcNextAck($segmento['data'], true);
        $tcp->setDestinationPort($segmento['sr_port']);
        $tcp->setSourcePort($segmento['dt_port']);
        $resposta = $tcp->buildSegment('', TCP::SYN | TCP::ACK, true);
        TCP::dump_segment($resposta);
        TCP::send_segment($resposta, $fscl_port);

        $segmento = TCP::recv_segment($fssr_port);
        TCP::dump_segment($segmento);
        $infos    = TCP::unpack_info($segmento);

        if (!TCP::is_valid_segment($resposta) ||
            $infos['ack_num'] != $tcp->getSeqNumber() ||
            !TCP::is_flag_set($infos['control'], TCP::ACK)) {
            echo "Erro na confirmação da conexão." . PHP_EOL;
            $tcp->close();
            continue;
        }

        echo "Conexão estabelecida." . PHP_EOL;
        echo "Recebendo dados..." . PHP_EOL;

        $msg = $tcp->recvData($infos, $fscl_port, $fssr_port);

        echo "Pedido de PUSH recebido." . PHP_EOL;

        $tcp->calcNextAck($infos['data'], true);
        $resposta = $tcp->buildSegment('', TCP::ACK);
        TCP::dump_segment($resposta);
        TCP::send_segment($resposta, $fscl_port);

        echo "Enviando mensagem para a aplicação ({$tcp->getSourcePort()})..." . PHP_EOL;

        if (($socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
            echo "socket_create() falhou. Motivo: " . socket_strerror(socket_last_error()) . PHP_EOL;
            break;
        }

        $connection = socket_connect($socket, '127.0.0.1', $tcp->getSourcePort());
        if ($connection === false) {
            if (socket_last_error($socket) == 111) {
                echo "Porta de destino inválida ({$tcp->getSourcePort()})" . PHP_EOL;
                echo "Pacote ignorado." . PHP_EOL;
                socket_close($socket);
                continue;
            }
            echo "socket_connect() failed.\nReason: " . socket_strerror(socket_last_error($socket)) . PHP_EOL;
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

        echo "Enviando resposta para camada física..." . PHP_EOL;

        $tcp->sendData($msg, $infos, $fscl_port, $fssr_port);

        echo "Enviando pedido de PUSH..." . PHP_EOL;

        $tcp->calcNextAck($infos['data']);
        $resposta = $tcp->buildSegment('', TCP::PSH, true);
        TCP::dump_segment($resposta);
        TCP::send_segment($resposta, $fscl_port);

        $segmento = TCP::recv_segment($fssr_port);
        TCP::dump_segment($segmento);
        $infos    = TCP::unpack_info($segmento);

        if (!TCP::is_valid_segment($segmento) || $infos['ack_num'] != $tcp->getSeqNumber()) {
            echo "Falha na confirmação do pedido de PUSH." . PHP_EOL;
            continue;
        }

        echo "Finalizando conexão." . PHP_EOL;

        $segmento = TCP::recv_segment($fssr_port);
        TCP::dump_segment($segmento);
        $infos    = TCP::unpack_info($segmento);

        if (!TCP::is_valid_segment($segmento) ||
            $infos['ack_num'] != $tcp->getSeqNumber() ||
            !TCP::is_flag_set($infos['control'], TCP::FIN | TCP::ACK)) {
            echo "Erro no fechamento da conexão." . PHP_EOL;
            $tcp->close();
            continue;
        }

        $tcp->calcNextAck($infos['data'], true);
        $resposta = $tcp->buildSegment('', TCP::ACK);
        TCP::dump_segment($resposta);
        TCP::send_segment($resposta, $fscl_port);

        $tcp->calcNextAck($infos['data']);
        $resposta = $tcp->buildSegment('', TCP::FIN | TCP::ACK, true);
        TCP::dump_segment($resposta);
        TCP::send_segment($resposta, $fscl_port);

        $segmento = TCP::recv_segment($fssr_port);
        TCP::dump_segment($segmento);
        $infos    = TCP::unpack_info($segmento);

        if (!TCP::is_valid_segment($segmento) ||
            $infos['ack_num'] != $tcp->getSeqNumber() ||
            !TCP::is_flag_set($infos['control'], TCP::ACK)) {
            echo "Erro na confirmação do fechamento da conexão." . PHP_EOL;
            $tcp->close();
            continue;
        }

        echo "Conexão Fechada." . PHP_EOL;

        $tcp->close();
    } catch(Exception $e) {
        echo "Socket error ({$e->getCode()}): {$e->getMessage()}" . PHP_EOL;
        echo "Arquivo: '{$e->getFile()}'- Linha: {$e->getLine()}" . PHP_EOL;
        print_r($e->getTrace());
        break;
    }

} while(true);

?>
