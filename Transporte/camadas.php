<?php
error_reporting(E_ALL ^ E_WARNING);
/*
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
|  Data |           |U|A|P|R|S|F|                               |
| Offset| Reserved  |R|C|S|S|Y|I|            Window             |
|       |           |G|K|H|T|N|N|                               |
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
*/

class IPHeader {
    /*
    char ip_header[20]  = {0x45, 0x00, 0x01, 0xb9, 0xf7, 0x1c, 0x40, 0x00,
                           0x40, 0x06, 0x6a, 0xb2,
                           0x0a, 0x00, 0x7f, 0xda,  //IP de origem  [12..15]
                           0x0a, 0x00, 0x02, 0x69}; //IP de destino [16..19]
    */
    public static function build($ip_origem, $ip_destino) {
        $ip_origem  = explode('.', $ip_origem);
        $ip_destino = explode('.', $ip_destino);

        for ($i = 0; $i < 4; $i++) {
            $ip_origem[$i]  = (int)$ip_origem[$i];
            $ip_destino[$i] = (int)$ip_destino[$i];
        }

        $ip_header = pack("CCCCCCCCCCCCCCCCCCCC",
            0x45, 0x00, 0x01, 0xb9, 0xf7, 0x1c, 0x40, 0x00,
            0x40, 0x06, 0x6a, 0xb2,
            $ip_origem[0], $ip_origem[1], $ip_origem[2], $ip_origem[3],
            $ip_destino[0], $ip_destino[1], $ip_destino[2], $ip_destino[3]
        );

        return $ip_header;
    }
}

class TCP {
    const FIN = 0x0001;
    const SYN = 0x0002;
    const RST = 0x0004;
    const PSH = 0x0008;
    const ACK = 0x0010;
    const URG = 0x0020;

    const DATA_OFF = 5 << 12;

    private $sr_port;
    private $dt_port;
    private $seq_num;
    private $ack_num;
    private $control;
    private $mms;

    private $socket = null;

    function __construct($isn = null, $porta = null) {
        $isn = (int)$isn;

        if ($porta !== null) {
            if (($this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false)
                throw_socket_exception();
            while (socket_connect($this->socket, '127.0.0.1', $porta) === false)
                throw_socket_exception($this->socket);
        }

        $this->seq_num = empty($isn) ? TCP::ISN() : ($isn & 0xFFFFFFFF);
        $this->control = TCP::DATA_OFF;
        $this->mms     = 512;
    }

    function __destruct() {
        if ($this->socket !== null)
            socket_close($this->socket);
    }

    private function nextSeq($length) {
        $this->seq_num = ($this->seq_num + $length) % 0x100000000;

        return $this->seq_num;
    }

    public function getSeqNumber() {
        return $this->seq_num;
    }

    public function setFlag($flag) {
        $this->control = ($this->control | $flag);
    }

    public function setDestinationPort($port) {
        $this->dt_port = (int)$port;
    }

    public function setSourcePort($port) {
        $this->sr_port = (int)$port;
    }

    public function setMMS($mms) {
        $this->mms = (int)$mms;
    }

    public function setAckNumber($num) {
        $this->ack_num = (int)$num;
    }

    public function getAckNumber() {
        return $this->ack_num;
    }

    public function getDestinationPort() {
        return $this->dt_port;
    }

    public function getSourcePort() {
        return $this->sr_port;
    }

    public function calcNextAck($dados, $empty = false) {
        $length = $empty ? 1 : strlen($dados);
        $this->ack_num = ($this->ack_num + $length) % 0x100000000;

        return $this->ack_num;
    }

    /*
        Cabeçalho TCP:
        Source Port -> S
        Destination Port -> S
        Sequence Number -> L
        Acknowledgment Number -> L
        Data Offset + Reserved + Control Bits -> S
        Window -> S
        Checksum -> S
        Urgent Pointer -> S

        pack("nnNNnnnn");
    */

    public function buildSegment($data, $flags, $empty = false) {
        $this->setFlag($flags);
        $length  = $empty ? 1 : strlen($data);
        $header  = pack('nnNNnnn',
            $this->sr_port,
            $this->dt_port,
            $this->seq_num,
            $this->ack_num,
            $this->control,
            $this->mms,
            0 //Urgent Pointer
        );
        $segment = pack('nnNNnnnn',
            $this->sr_port,
            $this->dt_port,
            $this->seq_num,
            $this->ack_num,
            $this->control,
            $this->mms,
            checksum($header . $data),
            0 //Urgent Pointer
        );
        $this->nextSeq($length);
        $this->control = TCP::DATA_OFF;

        return $segment . $data;
    }

    public function sendData($msg, &$infos) {
        $length = strlen($msg);
        $pos    = 0;
        $mms    = $infos['mms'];
        $size   = $mms - (TCP::DATA_OFF >> 12) * 4;
        do {
            $pedaco   = substr($msg, $pos, $size);
            $this->calcNextAck($infos['data']);
            $segmento = $this->buildSegment($pedaco, TCP::ACK);
            TCP::dump_segment($segmento);
            $this->send_segment($segmento);
            $resposta = $this->recv_segment();
            TCP::dump_segment($resposta);
            $infos    = TCP::unpack_info($resposta);

            if (!TCP::is_valid_segment($resposta) ||
                $infos['ack_num'] != $this->getSeqNumber() ||
                !TCP::is_flag_set($infos['control'], TCP::ACK)) {
                echo "Falha na confirmação do segmento." . PHP_EOL;
                die;
            }

            $pos = $pos + $size;
        } while ($pos < $length);
    }

    public function recvData(&$infos) {
        $msg = '';
        do {
            $segmento = $this->recv_segment();
            TCP::dump_segment($segmento);
            $infos    = TCP::unpack_info($segmento);

            if (TCP::is_flag_set($infos['control'], TCP::PSH))
                break;

            if (!TCP::is_valid_segment($segmento) ||
                $infos['ack_num'] != $this->getSeqNumber() ||
                !TCP::is_flag_set($infos['control'], TCP::ACK)) {
                echo "Falha na confirmação do segmento." . PHP_EOL;
                die;
            }

            $msg .= $infos['data'];
            $this->calcNextAck($infos['data']);
            $resposta = $this->buildSegment('', TCP::ACK);
            TCP::dump_segment($resposta);
            $this->send_segment($resposta);
        } while (true);

        return $msg;
    }

    public function close() {
        $this->sr_port = 0;
        $this->dt_port = 0;
        $this->ack_num = 0;
        $this->control = TCP::DATA_OFF;
        $this->mms     = 512;
    }

    public static function ISN() {
        return rand();
    }

    public static function unpack_info($segment) {
        $header = substr($segment, 0, (TCP::DATA_OFF >> 12) * 4);
        $data   = substr($segment, (TCP::DATA_OFF >> 12) * 4);
        $info   = unpack('nsr_port/ndt_port/Nseq_num/Nack_num/ncontrol/nmms/nchecksum/nurgent', $header);

        $info['data'] = $data;

        return $info;
    }

    public static function is_flag_set($control, $flag) {
        return ($control & $flag) != 0;
    }

    public static function flags_desc($control) {
        $flags = '';

        if (TCP::is_flag_set($control, TCP::FIN))
            $flags .= 'FIN,';
        if (TCP::is_flag_set($control, TCP::SYN))
            $flags .= 'SYN,';
        if (TCP::is_flag_set($control, TCP::RST))
            $flags .= 'RST,';
        if (TCP::is_flag_set($control, TCP::PSH))
            $flags .= 'PSH,';
        if (TCP::is_flag_set($control, TCP::ACK))
            $flags .= 'ACK,';
        if (TCP::is_flag_set($control, TCP::URG))
            $flags .= 'URG,';

        if (!empty($flags))
            $flags = substr($flags, 0, -1);

        return $flags;
    }

    public static function dump_segment($segmento) {
        $info = TCP::unpack_info($segmento);

        echo "({$info['sr_port']}) -> ({$info['dt_port']}): <SEQ={$info['seq_num']}><ACK={$info['ack_num']}><CTL=" . TCP::flags_desc($info['control']) . ">" . PHP_EOL;
        hex_dump($segmento);
    }

    public function send_segment($msg) {
        if (socket_write($this->socket, $msg, strlen($msg)) === false)
            throw_socket_exception($this->socket);
    }

    public function recv_segment() {
        if (($msg = socket_read($this->socket, 8192, PHP_BINARY_READ)) === false)
            throw_socket_exception($this->socket);

        return $msg;
    }

    public static function is_valid_segment($segment) {
        $info    = TCP::unpack_info($segment);
        $header  = pack('nnNNnnn',
            $info['sr_port'],
            $info['dt_port'],
            $info['seq_num'],
            $info['ack_num'],
            $info['control'],
            $info['mms'],
            0 //Urgent Pointer
        );

        return checksum($header . $info['data']) == $info['checksum'];
    }
}

function throw_socket_exception($socket = null) {
    $error_code = socket_last_error($socket);

    throw new Exception(socket_strerror($error_code), $error_code);
}

function hex_dump($data, $newline = "\n") {
    static $from = '';
    static $to = '';

    static $width = 16; # number of bytes per line

    static $pad = '.'; # padding for non-visible characters

    if ($from === '') {
        for ($i = 0; $i <= 0xFF; $i++) {
            $from .= chr($i);
            $to .= ($i >= 0x20 && $i <= 0x7E) ? chr($i) : $pad;
        }
    }

    $hex = str_split(bin2hex($data), $width*2);
    $chars = str_split(strtr($data, $from, $to), $width);

    $offset = 0;
    foreach ($hex as $i => $line) {
        echo sprintf('%6X',$offset).' : '.implode(' ', str_split(str_pad($line, 2*$width, '  '),2)) . ' [' . str_pad($chars[$i], $width, ' ') . ']' . $newline;
        $offset += $width;
    }
}

function checksum($data) {
    $crc32        = crc32($data);
    $array_16bits = unpack('n2/', $crc32);
    $soma         = $array_16bits[1] + $array_16bits[2];

    if ($soma > 0xFFFF) {
        $termo_1 =  $soma & 0x0000FFFF;
        $termo_2 = ($soma & 0xFFFF0000) >> 16;
        $soma    = $termo_1 + $termo_2;
    }

    return (~$soma & 0xFFFF);
}

?>
