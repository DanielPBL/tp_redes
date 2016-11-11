<?php
    require_once './Transporte/camadas.php';
    error_reporting(E_ALL);

    $tcp_or = new TCP(100);
    $tcp_ds = new TCP(300);

    $tcp_or->setSourcePort(36522);
    $tcp_ds->setDestinationPort(36522);

    $tcp_ds->setSourcePort(80);
    $tcp_or->setDestinationPort(80);

    /* Iniciando a conexão (Three way handshake)*/
    $segmento = $tcp_or->buildSegment('', TCP::SYN, true);
    hex_dump($segmento);
    $infos    = TCP::unpackInfo($segmento);
    echo "{$infos['sr_port']} -> {$infos['dt_port']} Flags: " . TCP::flags_desc($infos['control']) . PHP_EOL;
    echo "{$infos['seq_num']} - {$infos['ack_num']}" . PHP_EOL;

    $tcp_ds->setAckNumber($infos['seq_num']);
    $tcp_ds->calcNextAck($infos['data'], true);
    $segmento = $tcp_ds->buildSegment('', TCP::SYN | TCP::ACK, true);
    hex_dump($segmento);
    $infos    = TCP::unpackInfo($segmento);
    echo "{$infos['sr_port']} -> {$infos['dt_port']} Flags: " . TCP::flags_desc($infos['control']) . PHP_EOL;
    echo "{$infos['seq_num']} - {$infos['ack_num']}" . PHP_EOL;

    $tcp_or->setAckNumber($infos['seq_num']);
    $tcp_or->calcNextAck($infos['data'], true);
    $segmento = $tcp_or->buildSegment('', TCP::ACK);
    hex_dump($segmento);
    $infos    = TCP::unpackInfo($segmento);
    echo "{$infos['sr_port']} -> {$infos['dt_port']} Flags: " . TCP::flags_desc($infos['control']) . PHP_EOL;
    echo "{$infos['seq_num']} - {$infos['ack_num']}" . PHP_EOL;

    /* Transmitindo dados */
    $http_get = 'GET /images/f/fc/Image2001.gif HTTP/1.1';
    $tcp_or->calcNextAck($infos['data']);
    $segmento = $tcp_or->buildSegment($http_get, TCP::ACK);
    hex_dump($segmento);
    $infos    = TCP::unpackInfo($segmento);
    echo "{$infos['sr_port']} -> {$infos['dt_port']} Flags: " . TCP::flags_desc($infos['control']) . PHP_EOL;
    echo "{$infos['seq_num']} - {$infos['ack_num']}" . PHP_EOL;

    $tcp_ds->calcNextAck($infos['data']);
    $segmento = $tcp_ds->buildSegment('', TCP::ACK);
    hex_dump($segmento);
    $infos    = TCP::unpackInfo($segmento);
    echo "{$infos['sr_port']} -> {$infos['dt_port']} Flags: " . TCP::flags_desc($infos['control']) . PHP_EOL;
    echo "{$infos['seq_num']} - {$infos['ack_num']}" . PHP_EOL;

    /* Enviar PUSH*/
    $tcp_or->calcNextAck($infos['data']);
    $segmento = $tcp_or->buildSegment('', TCP::PSH, true);
    hex_dump($segmento);
    $infos    = TCP::unpackInfo($segmento);
    echo "{$infos['sr_port']} -> {$infos['dt_port']} Flags: " . TCP::flags_desc($infos['control']) . PHP_EOL;
    echo "{$infos['seq_num']} - {$infos['ack_num']}" . PHP_EOL;

    $tcp_ds->calcNextAck($infos['data'], true);
    $segmento = $tcp_ds->buildSegment('', TCP::ACK);
    hex_dump($segmento);
    $infos    = TCP::unpackInfo($segmento);
    echo "{$infos['sr_port']} -> {$infos['dt_port']} Flags: " . TCP::flags_desc($infos['control']) . PHP_EOL;
    echo "{$infos['seq_num']} - {$infos['ack_num']}" . PHP_EOL;

    /* Finalizando a conexão*/
    $tcp_or->calcNextAck($infos['data']);
    $segmento = $tcp_or->buildSegment('', TCP::FIN | TCP::ACK, true);
    hex_dump($segmento);
    $infos    = TCP::unpackInfo($segmento);
    echo "{$infos['sr_port']} -> {$infos['dt_port']} Flags: " . TCP::flags_desc($infos['control']) . PHP_EOL;
    echo "{$infos['seq_num']} - {$infos['ack_num']}" . PHP_EOL;

    $tcp_ds->calcNextAck($infos['data'], true);
    $segmento = $tcp_ds->buildSegment('', TCP::ACK);
    hex_dump($segmento);
    $infos    = TCP::unpackInfo($segmento);
    echo "{$infos['sr_port']} -> {$infos['dt_port']} Flags: " . TCP::flags_desc($infos['control']) . PHP_EOL;
    echo "{$infos['seq_num']} - {$infos['ack_num']}" . PHP_EOL;

    $tcp_ds->calcNextAck($infos['data']);
    $segmento = $tcp_ds->buildSegment('', TCP::FIN | TCP::ACK);
    hex_dump($segmento);
    $infos    = TCP::unpackInfo($segmento);
    echo "{$infos['sr_port']} -> {$infos['dt_port']} Flags: " . TCP::flags_desc($infos['control']) . PHP_EOL;
    echo "{$infos['seq_num']} - {$infos['ack_num']}" . PHP_EOL;

    $tcp_or->calcNextAck($infos['data'], true);
    $segmento = $tcp_or->buildSegment('', TCP::ACK, true);
    hex_dump($segmento);
    $infos    = TCP::unpackInfo($segmento);
    echo "{$infos['sr_port']} -> {$infos['dt_port']} Flags: " . TCP::flags_desc($infos['control']) . PHP_EOL;
    echo "{$infos['seq_num']} - {$infos['ack_num']}" . PHP_EOL;

    $tcp_ds->close();
    $tcp_or->close();
?>
