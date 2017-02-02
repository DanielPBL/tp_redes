'use strict';

var args = process.argv.slice(2);

if (args.length < 2) {
    console.log('Parâmetros insuficientes!');
    console.log('nodejs rede_cl.js porta_escutada tabela');
    return;
}

require('timers');
var net     = require('net');
var hexdump = require('hexdump-nodejs');
var rts     = require('./rede.js');
var crc     = require('crc16-ccitt-node');

var porta_trans = Number(args[0]);

var tabela = new rts.Tabela(inicializa_interfaces, args[1]);

function inicializa_interfaces() {
    for (var rota of tabela.rotas) {
        var inter = rota.interface;

        var socket = net.connect({
            'host': '127.0.0.1',
            'port': inter.ij
        }, function() {
            socket.write('TMQ');
            socket.end();
        });
    }

    socket.on('close', function(had_error) {
        console.log('Inicializando interface(s).');
        main();
    })
}

function sendTMQ(socket) {
    if (tabela.rotas[0].interface.TMQ === null)
        setTimeout(sendTMQ, 200, socket);
    else
        socket.write(String(tabela.rotas[0].interface.TMQ));
}

function main() {
    var sock = net.connect({
        'host': '127.0.0.1',
        'port': porta_trans
    });

    tabela.socket_trans = sock;
    tabela.router();

    sock.on('data', function(chunk) {
        if (chunk.toString() === 'TMQ') {
            sendTMQ(sock);
            return;
        }

        console.log('Recebendo dados da camada de transporte...');

        var segmento = chunk.toString();
        var header   = Buffer.alloc(20);
        var confs    = tabela.rotas[0].interface;
        var protocol = (chunk.readUInt16BE(14) === confs.TMQ - 46) ? '6 (TCP)' : '17 (UDP)';

        var tamanho = chunk.length + 20;

        header.writeUInt8(0x45, 0); // Versão + IHL
        header.writeUInt8(0x00, 1); // Type of Service
        header.writeUInt16BE(tamanho, 2); // Total Length
        header.writeUInt16BE(0x0000, 4); // Identification
        header.writeUInt16BE(0x1000, 6); // Flags + Fragment Offset
        header.writeUInt8(64, 8); // Time to Live
        header.writeUInt8(Number(protocol.split(' ')[0]), 9);
        header.writeUInt16BE(0x0000, 10); // Header Checksum
        header.writeUInt32BE(Buffer.from(confs.ip.split('.')).readUInt32BE(), 12); // Source Address
        header.writeUInt32BE(Buffer.from(confs.cnn.split('.')).readUInt32BE(), 16); // Destination Address

        var checksum = crc.getCrc16(header);

        header.writeUInt16BE(checksum, 10); // Header Checksum (calculado)

        console.log('Endereço Origem : ' + confs.ip);
        console.log('Endereço Destino: ' + confs.cnn);
        console.log('Tamanho Total ..: ' + tamanho);
        console.log('Protocolo ......: ' + protocol);
        console.log('Checksum .......: 0x' + checksum.toString(16) + ' (' + checksum + ')');

        var datagrama = Buffer.concat([header, chunk], tamanho);

        console.log('\nDatagrama');
        console.log(hexdump(datagrama));

        var entrega = function() {
            var fisica = net.connect({
                'host': '127.0.0.1',
                'port': confs.cl
            }, function() {
                fisica.write(datagrama);
                fisica.end();
            });

            fisica.on('error', entrega);
        }
        entrega();
    });
}
