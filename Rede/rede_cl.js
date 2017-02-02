'use strict';

var args = process.argv.slice(2);

if (args.length < 2) {
    console.log('Parâmetros insuficientes!');
    console.log('nodejs rede_cl.js porta_escutada tabela');
    return;
}

var net     = require('net');
var hexdump = require('hexdump-nodejs');
var rts     = require('./rota.js');
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

function main() {
    var server_trans = net.createServer();
    var socket_trans;
    var servers = [];

    server_trans.on('connection', function(sock) {
        socket_trans = sock;

        sock.on('data', function(chunk) {
            if (chunk.toString() === 'TMQ') {
                sock.write(tabela.rotas[0].interface.TMQ);
                return;
            }

            console.log('Recebendo dados da camada de transporte...');

            var segmento = chunk.toString();
            var header   = Buffer.alloc(20);
            var confs    = tabela.rotas[0].interface;
            var protocol = (chunk.readUInt16BE(18) === confs.TMQ - 46) ? '6 (TCP)' : '17 (UDP)';
            var ip_dest = segmento.indexOf('Host: ') + 6;
            ip_dest = segmento.substring(ip_dest, segmento.indexOf('\r\n', ip_dest));

            if (ip_dest.indexOf(':') !== -1)
                ip_dest = ip_dest.substr(0, ip_dest.indexOf(':'));

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
            header.writeUInt32BE(Buffer.from(ip_dest.split('.')).readUInt32BE(), 16); // Destination Address

            var checksum = crc.getCrc16(header);

            header.writeUInt16BE(checksum, 10); // Header Checksum (calculado)

            console.log('Endereço Origem : ' + confs.ip);
            console.log('Endereço Destino: ' + ip_dest);
            console.log('Tamanho Total ..: ' + tamanho);
            console.log('Protocolo ......: ' + protocol);
            console.log('Checksum .......: ' + checksum.toString(16) + '(' + checksum + ')');

            var datagrama = Buffer.concat([header, chunk], tamanho);

            console.log('\nDatagrama');
            console.log(hexdump(datagrama));

            var fisica = net.connect({
                'host': '127.0.0.1',
                'port': confs.cl
            }, function() {
                fisica.write(datagrama);
                fisica.end();
            });

            fisica.on('error', function(error) {
                console.log('Erro ao entregar para a interface (' + confs.sr + '/' + confs.cl +')!');
            });
        });
    });

    server_trans.listen(porta_trans, '127.0.0.1');

    for (var rota of tabela.rotas) {
        var server = net.createServer();

        server.on('connection', function(sock) {
            sock.on('data', function(chunk) {
                // Resposta recebida debido inicialização das interfaces
                if (chunk.toString().indexOf('TMQ:') === 0) {
                    var TMQ = Number(chunk.toString().split(':')[1]);

                    console.log('Interface (' + rota.interface.sr + '/' + rota.interface.cl +') => ' + TMQ);
                    rota.interface.TMQ = TMQ;
                    return;
                }

                var datagrama = Buffer.from(chunk);
                var header    = datagrama.slice(0, 20);

                var tamanho    = header.readUInt16BE(2);
                var protocol   = header.readUInt8(9);
                var checksum   = header.readUInt16BE(10);
                var ip_origem  = header.readUInt32BE(12);
                var ip_destino = header.readUInt32BE(16);

                protocol = protocol + (protocol === 6 ? ' (UDP)' : ' (TCP)');
                header.writeUInt16BE(0x0000, 10);

                var origem = String((ip_origem >> 0x18) & 0x000000FF) + '.' +
                             String((ip_origem >> 0x10) & 0x000000FF) + '.' +
                             String((ip_origem >> 0x08) & 0x000000FF) + '.' +
                             String((ip_origem >> 0x00) & 0x000000FF);
                var destino = String((ip_destino >> 0x18) & 0x000000FF) + '.' +
                              String((ip_destino >> 0x10) & 0x000000FF) + '.' +
                              String((ip_destino >> 0x08) & 0x000000FF) + '.' +
                              String((ip_destino >> 0x00) & 0x000000FF);

                console.log('Recebendo dados da interface (' + rota.interface.sr + '/' + rota.interface.cl +')...');
                console.log(hexdump(datagrama));
                console.log('Endereço Origem .: ' + origem);
                console.log('Endereço Destino : ' + destino);
                console.log('Tamanho Total ...: ' + tamanho);
                console.log('Protocolo .......: ' + protocol);
                console.log('Checksum ........: ' + checksum.toString(16) + '(' + checksum + ') - ' + (crc.getCrc16(header) === checksum ? 'OK' : 'FAIL'));

                //Encontrar rota
                var interfc = tabela.match(destino);

                if (interfc === false) {
                    console.log('Não foi encontrada uma rota para o pacote!');
                    return;
                }

                if (interfc.ip === destino) {
                    var segmento = datagrama.slice(20);
                    console.log('Entregando segmento para a camada de transporte...');
                    console.log(hexdump(segmento));
                    socket_trans.write(segmento);
                } else {
                    console.log('Encaminhando pacote para interface (' + interfc.sr + '/' + interfc.cl +')...');

                    var fisica = net.connect({
                        'host': '127.0.0.1',
                        'port': interfc.cl
                    }, function() {
                        fisica.write(datagrama);
                        fisica.end();
                    });
                }
            });
        });

        server.listen(rota.interface.sr, '127.0.0.1');
        servers.push(server);
    }
}

/*
var client = new net.Socket();

client.connect(1337, '127.0.0.1', function() {
    console.log('Connected');
    client.write('Hello, server! Love, Client.');
});

client.on('data', function(data) {
    console.log('Received: ' + data);
});

client.on('close', function() {
    console.log('Connection closed');
});
*/
