"use strict";
var fs      = require('fs');
var rl      = require('readline');
var net     = require('net');
var hexdump = require('hexdump-nodejs');
var crc     = require('crc16-ccitt-node');

var Rota = function(net_ip, mask, gate) {
    net_ip  = Buffer.from(String(net_ip).split('.'));
    mask    = Buffer.from(String(mask).split('.'));
    gate    = String(gate).split(':');
    gate[1] = String(gate[1]).split('/');

    this.ipRede    = net_ip.readInt32BE();
    this.mascara   = mask.readInt32BE();
    this.interface = {
        'TMQ': null,
        'ip' : String(gate[0]),
        'sr' : Number(gate[1][0]),
        'cl' : Number(gate[1][1]),
        'ij' : Number(gate[1][2])
    };
};

Rota.prototype.match = function(ip_addr) {
    var endIP = Buffer.from(String(ip_addr).split('.')).readInt32BE();

    if ((endIP & this.mascara) === this.ipRede)
        return this.interface;
    else
        return false;
};

var Tabela = function(cb, path) {
    this.rotas   = [];
    this.servers = [];
    this.cb      = cb;

    if (typeof(path) === 'string')
        this.load(path);
};

Tabela.prototype.load = function(path) {
    var _this = this;

    var lineReader = rl.createInterface({
        input: fs.createReadStream(path)
    });

    lineReader.on('line', function(linha) {
        linha = String(linha).split(' ');

        if (linha.length === 3)
            _this.rotas.push(new Rota(linha[0], linha[1], linha[2]));
    });

    lineReader.on('close', function() {
        if (typeof(_this.cb) === 'function')
            _this.cb();
    });
};

Tabela.prototype.match = function(ip_addr) {
    var match = false;

    for (var rota of this.rotas) {
        match = rota.match(ip_addr);
        if (match !== false)
            break;
    }

    return match;
};

Tabela.prototype.router = function() {
    var _this = this;

    for (var rota of _this.rotas) {
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

                rota.interface.cnn = origem; //Armazena IP da conexão

                console.log('Recebendo dados da interface (' + rota.interface.sr + '/' + rota.interface.cl +')...');
                console.log(hexdump(datagrama));
                console.log('Endereço Origem .: ' + origem);
                console.log('Endereço Destino : ' + destino);
                console.log('Tamanho Total ...: ' + tamanho);
                console.log('Protocolo .......: ' + protocol);
                console.log('Checksum ........: 0x' + checksum.toString(16) + ' (' + checksum + ') - ' + (crc.getCrc16(header) === checksum ? 'OK' : 'FAIL'));

                //Encontrar rota
                var interfc = _this.match(destino);

                if (interfc === false) {
                    console.log('Não foi encontrada uma rota para o pacote!');
                    return;
                }

                if (interfc.ip === destino) {
                    var segmento = datagrama.slice(20);
                    console.log('Entregando segmento para a camada de transporte...');
                    _this.socket_trans.write(segmento);
                } else {
                    console.log('Encaminhando pacote para interface (' + interfc.sr + '/' + interfc.cl +')...');

                    var entrega = function() {
                        var fisica = net.connect({
                            'host': '127.0.0.1',
                            'port': interfc.cl
                        }, function() {
                            fisica.write(datagrama);
                            fisica.end();
                        });

                        fisica.on('error', entrega);
                    }
                    entrega();
                }
            });
        });

        server.listen(rota.interface.sr, '127.0.0.1');
        _this.servers.push(server);
    }
}

exports.Rota = Rota;
exports.Tabela = Tabela;
